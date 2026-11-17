<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - WHAT THE ASSISTANT KNOWS ABOUT THE SIGNED-IN USER
|--------------------------------------------------------------------------
| chatbot.php puts this in the page (window.EM_CHATBOT) and js/chatbot.js
| answers from it: "Where am I?", "Why don't I find a group?", "Explain my
| numbers", and the suggestion buttons that change with the user's state.
|
| Only the user's OWN data plus a few totals (how many members are free,
| typical contribution values) - never another member's details.
| A few small queries; community_algorithm.php is NOT loaded, because it
| runs the whole matching when included.
*/

function emChatbotState(mysqli $conn, int $userId): array
{
    $state = [
        "name" => (string) ($_SESSION["name"] ?? ""),
        "hasProfile" => false,
        "minShare" => 0.0,
        "contributions" => [],      /* type => value in EUR */
        "totalValue" => 0.0,
        "pendingInvites" => 0,      /* proposals waiting for MY answer */
        "waiting" => null,          /* I accepted, waiting for the others */
        "active" => null,           /* my active community */
        "unread" => 0,
        "warnings" => 0,
        "freeMembers" => 0,         /* other members available for a new community */
        "shareRange" => null,       /* what my Fair Share can be in a group of 5 */
    ];

    /* first name for the greeting (from the database, the session may not have it) */
    $statement = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $statement->bind_param("i", $userId);
    $statement->execute();
    $userRow = $statement->get_result()->fetch_assoc();
    $statement->close();
    if ($userRow) {
        $state["name"] = (string) $userRow["name"];
    }

    /* profile + minimum share */
    $statement = $conn->prepare("SELECT minimum_share FROM energy_profiles WHERE user_id = ?");
    $statement->bind_param("i", $userId);
    $statement->execute();
    $profile = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($profile) {
        $state["hasProfile"] = true;
        $state["minShare"] = (float) $profile["minimum_share"];
    }

    /* my contributions by type */
    $statement = $conn->prepare(
        "SELECT contribution_type, COALESCE(SUM(estimated_value), 0) AS total
         FROM user_contributions WHERE user_id = ? GROUP BY contribution_type"
    );
    $statement->bind_param("i", $userId);
    $statement->execute();
    $result = $statement->get_result();
    while ($row = $result->fetch_assoc()) {
        $state["contributions"][$row["contribution_type"]] = round((float) $row["total"], 2);
    }
    $statement->close();
    $state["totalValue"] = round(array_sum($state["contributions"]), 2);

    /* my communities: proposals to answer, waiting, active */
    $statement = $conn->prepare(
        "SELECT c.id, c.name, c.status, my.invitation_status AS my_status,
                SUM(cm.invitation_status = 'accepted') AS accepted_count
         FROM community_members my
         INNER JOIN communities c ON c.id = my.community_id
         INNER JOIN community_members cm ON cm.community_id = c.id
         WHERE my.user_id = ?
           AND c.status IN ('pending', 'active')
           AND my.invitation_status IN ('pending', 'accepted')
         GROUP BY c.id, c.name, c.status, my.invitation_status
         ORDER BY c.id DESC"
    );
    $statement->bind_param("i", $userId);
    $statement->execute();
    $result = $statement->get_result();
    while ($row = $result->fetch_assoc()) {
        $community = ["id" => (int) $row["id"], "name" => (string) $row["name"], "accepted" => (int) $row["accepted_count"]];

        if ($row["status"] === "active" && $row["my_status"] === "accepted") {
            $state["active"] = $community;
        } elseif ($row["my_status"] === "pending") {
            $state["pendingInvites"]++;
        } elseif ($state["waiting"] === null) {
            $state["waiting"] = $community;
        }
    }
    $statement->close();

    /* unread notifications */
    $statement = $conn->prepare("SELECT COUNT(*) FROM community_notifications WHERE recipient_user_id = ? AND is_read = 0");
    $statement->bind_param("i", $userId);
    $statement->execute();
    $state["unread"] = (int) $statement->get_result()->fetch_row()[0];
    $statement->close();

    /* warnings (approved reports, one per reporter - same rule as moderation.php) */
    try {
        $statement = $conn->prepare(
            "SELECT COUNT(DISTINCT reporter_user_id) FROM user_reports
             WHERE reported_user_id = ? AND status = 'reviewed'"
        );
        $statement->bind_param("i", $userId);
        $statement->execute();
        $state["warnings"] = (int) $statement->get_result()->fetch_row()[0];
        $statement->close();
    } catch (Throwable $error) {
        /* reports table missing: no warnings */
    }

    /*
     * Other members free for a new community (same rule as the matching:
     * a profile, contributions worth more than 0, not in an active community).
     * Only their total values are read, to estimate my possible Fair Share.
     */
    $statement = $conn->prepare(
        "SELECT SUM(uc.estimated_value) AS total, MAX(ep.minimum_share) AS min_share
         FROM users u
         INNER JOIN energy_profiles ep ON ep.user_id = u.id
         INNER JOIN user_contributions uc ON uc.user_id = u.id
         WHERE u.id <> ?
           AND NOT EXISTS (
               SELECT 1 FROM community_members acm
               INNER JOIN communities ac ON ac.id = acm.community_id
               WHERE acm.user_id = u.id AND acm.invitation_status = 'accepted' AND ac.status = 'active'
           )
         GROUP BY u.id
         HAVING total > 0"
    );
    $statement->bind_param("i", $userId);
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_NUM);
    $statement->close();
    $values = array_map(function ($row) { return (float) $row[0]; }, $rows);
    $otherMins = array_map(function ($row) { return (float) $row[1]; }, $rows);

    $state["freeMembers"] = count($values);

    /*
     * Fair Share = my value / (my value + the 4 others) x 100.
     *   highest: with the 4 SMALLEST contributions
     *   lowest:  with the 4 LARGEST contributions
     *   typical: with 4 members of the median value
     */
    $myValue = $state["totalValue"];
    if ($myValue > 0 && count($values) >= 4) {
        sort($values);
        $count = count($values);
        $median = $count % 2 ? $values[intdiv($count, 2)] : ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        $smallest = array_sum(array_slice($values, 0, 4));
        $largest = array_sum(array_slice($values, -4));

        $state["shareRange"] = [
            "low" => round($myValue / ($myValue + $largest) * 100, 1),
            "typical" => round($myValue / ($myValue + 4 * $median) * 100, 1),
            "high" => round($myValue / ($myValue + $smallest) * 100, 1),
            "median" => round($median, 2),
            /*
             * The other side: what a typical other member would get next to
             * me, against the minimum most of them ask for. A very large
             * contribution leaves the other 4 with tiny shares.
             */
            "othersTypical" => round($median / ($myValue + 4 * $median) * 100, 1),
            "othersMinMedian" => round(emChatbotMedian($otherMins), 1),
        ];
    }

    return $state;
}

function emChatbotMedian(array $numbers): float
{
    if (!$numbers) {
        return 0.0;
    }
    sort($numbers);
    $count = count($numbers);
    return $count % 2 ? $numbers[intdiv($count, 2)] : ($numbers[$count / 2 - 1] + $numbers[$count / 2]) / 2;
}
