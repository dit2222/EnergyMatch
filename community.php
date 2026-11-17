<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once "db.php";
require_once __DIR__ . "/community_events.php";
require_once "lang.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
$currentUserId =
    (int) $_SESSION["user_id"];
$isGreek =
    ($_SESSION["language"] ?? "en") === "gr";

/*
|--------------------------------------------------------------------------
| Fair Share Algorithm
|--------------------------------------------------------------------------
*/
define(
    "ENERGYMATCH_ALGORITHM_LIBRARY_ONLY",
    true
);
require_once "community_algorithm.php";

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function safe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function moneyValue($value)
{
    return "€" . number_format(
        (float) $value,
        2,
        ".",
        ","
    );
}

function percentValue($value)
{
    return number_format(
        (float) $value,
        2
    ) . "%";
}

function getInitials($name, $surname)
{
    $first =
        mb_substr(
            trim((string) $name),
            0,
            1,
            "UTF-8"
        );
    $second =
        mb_substr(
            trim((string) $surname),
            0,
            1,
            "UTF-8"
        );
    return mb_strtoupper(
        $first . $second,
        "UTF-8"
    );
}

function contributionLabel(
    $type,
    $isGreek
) {
    $labels = [
        "capital" => $isGreek
            ? "Κεφάλαιο"
            : "Capital",
        "land" => $isGreek
            ? "Γη"
            : "Land",
        "manual_labor" => $isGreek
            ? "Χειρωνακτική Εργασία"
            : "Manual Labor",
        "skilled_labor" => $isGreek
            ? "Εξειδικευμένη Εργασία"
            : "Skilled Labor",
        "equipment" => $isGreek
            ? "Εξοπλισμός"
            : "Equipment"
    ];
    return $labels[$type]
        ?? ucwords(
            str_replace("_", " ", $type)
        );
}

function invitationLabel(
    $status,
    $isGreek
) {
    if ($status === "accepted") {
        return $isGreek
            ? "Αποδέχτηκε"
            : "Accepted";
    }
    if ($status === "rejected") {
        return $isGreek
            ? "Απορρίφθηκε"
            : "Rejected";
    }
    return $isGreek
        ? "Αναμονή"
        : "Pending";
}

function communityStatusLabel(
    $status,
    $isGreek
) {
    $labels = [
        "pending" => $isGreek
            ? "Αναμονή Αποδοχής"
            : "Pending Acceptance",
        "active" => $isGreek
            ? "Ενεργή Κοινότητα"
            : "Active Community",
        "completed" => $isGreek
            ? "Ολοκληρωμένη"
            : "Completed",
        "cancelled" => $isGreek
            ? "Ακυρωμένη"
            : "Cancelled",
        "rejected" => $isGreek
            ? "Απορρίφθηκε"
            : "Rejected"
    ];
    return $labels[$status]
        ?? ucfirst($status);
}

/*
|--------------------------------------------------------------------------
| CSRF token για το Community Chat
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION["community_chat_csrf"])
    ||
    !is_string(
        $_SESSION["community_chat_csrf"]
    )
) {
    $_SESSION["community_chat_csrf"] =
        bin2hex(random_bytes(32));
}
$chatCsrfToken =
    $_SESSION["community_chat_csrf"];

/*
|--------------------------------------------------------------------------
| Find Community
|--------------------------------------------------------------------------
|
| Αν έχουμε ?id=17 χρησιμοποιούμε αυτή την κοινότητα.
|
| Αν ανοίξει community.php χωρίς id,
| βρίσκουμε αυτόματα την τελευταία κοινότητα
| που έχει αποδεχτεί ο χρήστης.
|
*/
$communityId = 0;
if (
    isset($_GET["id"])
    &&
    is_numeric($_GET["id"])
) {
    $communityId =
        (int) $_GET["id"];
}
else {
    $findCommunityStatement =
        $conn->prepare(
            "
            SELECT
                c.id
            FROM community_members cm
            INNER JOIN communities c
                ON c.id = cm.community_id
            WHERE cm.user_id = ?
              AND cm.invitation_status = 'accepted'
              AND c.status = 'active'
            ORDER BY c.created_at DESC
            LIMIT 1
            "
        );
    if (!$findCommunityStatement) {
        die(
            "Database error: "
            . $conn->error
        );
    }
    $findCommunityStatement->bind_param(
        "i",
        $currentUserId
    );
    $findCommunityStatement->execute();
    $findCommunityResult =
        $findCommunityStatement
            ->get_result();
    if (
    $findCommunityResult->num_rows === 0
) {
    $findCommunityStatement->close();

    // If the user had an active community that was cancelled because
    // another member left, show a contextual warning in My Community.
    // It is shown ONCE: only while the "member left" notification of that
    // community is unread; showing it marks the notification as read.
    $cancelledByDeparture = null;
    $departureWarningStatement = $conn->prepare(
        "
        SELECT
            c.id AS community_id,
            c.name AS community_name,
            departed.user_id AS departed_user_id,
            u.name AS departed_name,
            u.surname AS departed_surname,
            clr.left_at
        FROM community_members mine
        INNER JOIN communities c
            ON c.id = mine.community_id
        INNER JOIN community_members departed
            ON departed.community_id = c.id
           AND departed.invitation_status = 'left'
           AND departed.user_id <> ?
        INNER JOIN users u
            ON u.id = departed.user_id
        LEFT JOIN community_leave_requests clr
            ON clr.community_id = c.id
           AND clr.user_id = departed.user_id
        INNER JOIN community_notifications unread_left
            ON unread_left.community_id = c.id
           AND unread_left.recipient_user_id = mine.user_id
           AND unread_left.actor_user_id = departed.user_id
           AND unread_left.notification_type = 'member_left'
           AND unread_left.is_read = 0
        WHERE mine.user_id = ?
          AND mine.invitation_status = 'accepted'
          AND c.status = 'cancelled'
        ORDER BY
            CASE WHEN clr.left_at IS NULL THEN 1 ELSE 0 END ASC,
            clr.left_at DESC,
            c.id DESC
        LIMIT 1
        "
    );
    if ($departureWarningStatement) {
        $departureWarningStatement->bind_param(
            "ii",
            $currentUserId,
            $currentUserId
        );
        $departureWarningStatement->execute();
        $departureWarningResult = $departureWarningStatement->get_result();
        $cancelledByDeparture = $departureWarningResult->fetch_assoc();
        $departureWarningStatement->close();
    }

    // Shown now: the "member left" notification is read, so the warning
    // does not appear again (also not after signing in again).
    if ($cancelledByDeparture) {
        $markLeftRead = $conn->prepare(
            "UPDATE community_notifications
             SET is_read = 1
             WHERE recipient_user_id = ?
               AND community_id = ?
               AND notification_type = 'member_left'"
        );
        if ($markLeftRead) {
            $leftCommunityId = (int) $cancelledByDeparture["community_id"];
            $markLeftRead->bind_param("ii", $currentUserId, $leftCommunityId);
            $markLeftRead->execute();
            $markLeftRead->close();
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $isGreek ? "el" : "en"; ?>">
    <head>
    <?php include "head_assets.php"; ?>
        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >
        <link rel="stylesheet" href="css/community.css?v=<?php echo ASSET_VERSION; ?>">
        <link
            rel="stylesheet"
            href="css/chatbot.css?v=<?php echo ASSET_VERSION; ?>"
        >
        <title>
            EnergyMatch -
            <?php
            echo $isGreek
                ? "Η Κοινότητά μου"
                : "My Community";
            ?>
        </title>

        <style>

            .community-cancelled-warning {
                margin: 0 0 24px;
                padding: 18px 20px;
                border-radius: 16px;
                background: #fff8e1;
                border: 1px solid #f0cf75;
                border-left: 5px solid #e0a800;
                color: #5d4a12;
                text-align: left;
                line-height: 1.6;
            }

            .community-cancelled-warning strong {
                display: block;
                margin-bottom: 5px;
                color: #6d5310;
                font-size: 16px;
            }
        </style>
    </head>
    <body>

    <section class="hero-section">

        <!-- =========================================
             NAVBAR
        ========================================== -->
        <?php include "navbar.php"; ?>

        <!-- =========================================
             NO COMMUNITY
        ========================================== -->

        <main class="no-community-wrapper">
            <?php if (($_GET["report_access"] ?? "") === "denied"): ?>
                <div class="report-denied-notice">
                    <i data-lucide="shield-alert"></i>
                    <?php echo t("report_access_denied"); ?>
                </div>
            <?php endif; ?>
                <div class="no-community-card">

                <?php if ($cancelledByDeparture): ?>
                    <?php
                    $departedFullName = trim(
                        (string) $cancelledByDeparture["departed_name"]
                        . " "
                        . (string) $cancelledByDeparture["departed_surname"]
                    );
                    ?>
                    <div class="community-cancelled-warning">
                        <strong>
                            <i data-lucide="triangle-alert"></i> <?php echo $isGreek
                                ? "Η κοινότητά σου ακυρώθηκε"
                                : "Your community was cancelled"; ?>
                        </strong>

                        <?php if ($isGreek): ?>
                            Ο χρήστης <strong style="display:inline; margin:0; font-size:inherit;">
                                <?php echo safe($departedFullName); ?>
                            </strong>
                            αποχώρησε από την κοινότητα
                            <strong style="display:inline; margin:0; font-size:inherit;">
                                <?php echo safe($cancelledByDeparture["community_name"]); ?>
                            </strong>.
                            Επειδή η EnergyMatch κοινότητα πρέπει να αποτελείται από 5 μέλη, η ομάδα ακυρώθηκε.
                        <?php else: ?>
                            <strong style="display:inline; margin:0; font-size:inherit;">
                                <?php echo safe($departedFullName); ?>
                            </strong>
                            left the community
                            <strong style="display:inline; margin:0; font-size:inherit;">
                                <?php echo safe($cancelledByDeparture["community_name"]); ?>
                            </strong>.
                            Because an EnergyMatch community must consist of 5 members, the group was cancelled.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="no-community-icon">
                    <i data-lucide="land-plot"></i>
                </div>
                <p class="section-label">
                    <?php
                    echo $isGreek
                        ? "ENERGYMATCH COMMUNITY"
                        : "ENERGYMATCH COMMUNITY";
                    ?>
                </p>
                <h1>
                    <?php
                    echo $isGreek
                        ? "Δεν έχεις ακόμη Ενεργειακή Κοινότητα"
                        : "No Energy Community Yet";
                    ?>
                </h1>
                <p class="no-community-description">
                    <?php
                    echo $isGreek
                        ? "Δεν συμμετέχεις αυτή τη στιγμή σε κάποια ενεργειακή κοινότητα. Βρες συμβατά μέλη ή δημιούργησε την πρώτη σου κοινότητα."
                        : "You are not currently a member of an Energy Community. Find compatible members or create your first community.";
                    ?>
                </p>
                <div class="no-community-actions">
                    <a
                        href="matches.php"
                        class="secondary-btn"
                    >
                        <i data-lucide="users"></i>
                        <?php
                        echo $isGreek
                            ? "Βρες Matches"
                            : "Find Matches";
                        ?>
                    </a>
                    <a
                        href="create_community.php"
                        class="primary-btn"
                    >
                        ＋
                        <?php
                        echo $isGreek
                            ? "Δημιουργία Κοινότητας"
                            : "Create Community";
                        ?>
                    </a>
                </div>
                <div class="no-community-info">
                    <span><i data-lucide="lightbulb"></i></span>
                    <p>
                        <?php
                        echo $isGreek
                            ? "Μια EnergyMatch κοινότητα αποτελείται από 5 μέλη που ικανοποιούν τους κανόνες Fair Share και Minimum Share."
                            : "An EnergyMatch community consists of 5 members who satisfy the Fair Share and Minimum Share rules.";
                        ?>
                    </p>
                </div>
            </div>
        </main>
    </section>

    <!-- CHATBOT -->
    <?php include "chatbot.php"; ?>

    <script src="js/chatbot.js?v=<?php echo ASSET_VERSION; ?>"></script>
    </body>
    </html>
    <?php
    exit();
}
    $communityRow =
        $findCommunityResult
            ->fetch_assoc();
    $communityId =
        (int) $communityRow["id"];
    $findCommunityStatement->close();
}

/*
|--------------------------------------------------------------------------
| Load Community
|--------------------------------------------------------------------------
*/
$communityStatement =
    $conn->prepare(
        "
        SELECT
            c.id,
            c.name,
            c.created_by,
            c.status,
            c.created_at,
            u.name AS creator_name,
            u.surname AS creator_surname
        FROM communities c
        INNER JOIN users u
            ON u.id = c.created_by
        WHERE c.id = ?
        LIMIT 1
        "
    );
if (!$communityStatement) {
    die(
        "Database error: "
        . $conn->error
    );
}
$communityStatement->bind_param(
    "i",
    $communityId
);
$communityStatement->execute();
$communityResult =
    $communityStatement->get_result();
if (
    $communityResult->num_rows === 0
) {
    $communityStatement->close();
    header(
        "Location: matches.php?community_not_found=1"
    );
    exit();
}
$community =
    $communityResult->fetch_assoc();
$communityStatement->close();

/*
|--------------------------------------------------------------------------
| Access Check
|--------------------------------------------------------------------------
|
| Ο χρήστης πρέπει να ανήκει στην κοινότητα.
| Rejected μέλος δεν μπορεί να μπει.
|
*/
$accessStatement =
    $conn->prepare(
        "
        SELECT
            invitation_status
        FROM community_members
        WHERE community_id = ?
          AND user_id = ?
          AND invitation_status IN ('pending', 'accepted')
        LIMIT 1
        "
    );
if (!$accessStatement) {
    die(
        "Database error: "
        . $conn->error
    );
}
$accessStatement->bind_param(
    "ii",
    $communityId,
    $currentUserId
);
$accessStatement->execute();
$accessResult =
    $accessStatement->get_result();
if ($accessResult->num_rows === 0) {
    $accessStatement->close();
    header(
        "Location: matches.php?community_access=denied"
    );
    exit();
}
$currentMembership =
    $accessResult->fetch_assoc();
$currentInvitationStatus =
    $currentMembership["invitation_status"];
$accessStatement->close();

/*
|--------------------------------------------------------------------------
| Load Community Members
|--------------------------------------------------------------------------
*/
$membersStatement =
    $conn->prepare(
        "
        SELECT
            u.id AS user_id,
            u.name,
            u.surname,
            ep.location,
            ep.minimum_share,
            cm.invitation_status,
            cm.responded_at,
            cm.joined_at
        FROM community_members cm
        INNER JOIN users u
            ON u.id = cm.user_id
        LEFT JOIN energy_profiles ep
            ON ep.user_id = u.id
        WHERE cm.community_id = ?
        ORDER BY
            CASE
                WHEN u.id = ? THEN 0
                ELSE 1
            END,
            cm.joined_at ASC
        "
    );
if (!$membersStatement) {
    die(
        "Database error: "
        . $conn->error
    );
}
$membersStatement->bind_param(
    "ii",
    $communityId,
    $community["created_by"]
);
$membersStatement->execute();
$membersResult =
    $membersStatement->get_result();
$members = [];
$memberIds = [];
while (
    $member =
        $membersResult->fetch_assoc()
) {
    $member["user_id"] =
        (int) $member["user_id"];
    $member["minimum_share"] =
        (float) (
            $member["minimum_share"]
            ?? 0
        );
    $members[] = $member;
    $memberIds[] =
        (int) $member["user_id"];
}
$membersStatement->close();

/*
|--------------------------------------------------------------------------
| Load Fair Share Data
|--------------------------------------------------------------------------
|
| Χρησιμοποιούμε τον ΙΔΙΟ αλγόριθμο
| που χρησιμοποιούν τα Matches.
|
*/
$fairShareByUser = [];
$totalGroupValue = 0.0;
try {
    $eligibleMembers =
        emLoadEligibleMembers($conn);
    $membersById =
        emIndexMembersById(
            $eligibleMembers
        );
    if (count($memberIds) === 5) {
        $evaluation =
            emEvaluateCommunityByIds(
                $memberIds,
                $membersById
            );
        $totalGroupValue =
            (float) (
                $evaluation[
                    "total_group_value"
                ]
                ?? 0
            );
        foreach (
            $evaluation["members"] ?? []
            as $evaluatedMember
        ) {
            $fairShareByUser[
                (int)
                $evaluatedMember["user_id"]
            ] = $evaluatedMember;
        }
    }
}
catch (Throwable $error) {
    /*
     * Δεν σταματάμε ολόκληρη τη σελίδα
     * αν υπάρχει πρόβλημα με τα metrics.
     */
    $fairShareByUser = [];
}

/*
|--------------------------------------------------------------------------
| Merge Fair Share Data
|--------------------------------------------------------------------------
*/
foreach (
    $members as &$member
) {
    $memberId =
        (int) $member["user_id"];
    if (
        isset(
            $fairShareByUser[$memberId]
        )
    ) {
        $fairMember =
            $fairShareByUser[$memberId];
        $member["contribution_value"] =
            (float) (
                $fairMember[
                    "contribution_value"
                ]
                ?? 0
            );
        $member["fair_share"] =
            (float) (
                $fairMember[
                    "fair_share"
                ]
                ?? 0
            );
        $member["minimum_share"] =
            (float) (
                $fairMember[
                    "minimum_share"
                ]
                ?? $member["minimum_share"]
            );
        $member["fairness_margin"] =
            (float) (
                $fairMember[
                    "fairness_margin"
                ]
                ?? 0
            );
        $member["contribution_types"] =
            $fairMember[
                "contribution_types"
            ]
            ?? [];
        $member["contribution_breakdown"] =
            $fairMember[
                "contribution_breakdown"
            ]
            ?? [];
    }
    else {
        $member["contribution_value"] = 0;
        $member["fair_share"] = 0;
        $member["fairness_margin"] = 0;
        $member["contribution_types"] = [];
        $member["contribution_breakdown"] = [];
    }
}
unset($member);

/*
|--------------------------------------------------------------------------
| Can User Chat?
|--------------------------------------------------------------------------
*/
/*
 * The group chat opens only when the community is active, i.e. all 5
 * members have accepted. While it waits for acceptances it is locked.
 */
$chatWaitingForMembers =
    $currentInvitationStatus === "accepted"
    && $community["status"] === "pending";

$canChat =
    $currentInvitationStatus
        === "accepted"
    &&
    in_array(
        $community["status"],
        [
            "active",
            "completed"
        ],
        true
    );

// Opening the community page shows the chat: its notifications are read
if ($canChat) {
    emMarkChatRead($conn, (int) $communityId, $currentUserId);
}

/*
|--------------------------------------------------------------------------
| Send Message
|--------------------------------------------------------------------------
*/
$chatError = "";
if (
    $_SERVER["REQUEST_METHOD"]
        === "POST"
    &&
    isset($_POST["send_message"])
) {
    $postedToken =
        (string) (
            $_POST["csrf_token"]
            ?? ""
        );
    if (
        !hash_equals(
            $chatCsrfToken,
            $postedToken
        )
    ) {
        $chatError =
            $isGreek
                ? "Μη έγκυρο αίτημα."
                : "Invalid request.";
    }
    elseif (!$canChat) {
        $chatError =
            $isGreek
                ? "Δεν μπορείς να στείλεις μήνυμα σε αυτή την κοινότητα."
                : "You cannot send messages in this community.";
    }
    else {
        $message =
            trim(
                (string) (
                    $_POST["message"]
                    ?? ""
                )
            );
        if ($message === "") {
            $chatError =
                $isGreek
                    ? "Γράψε πρώτα ένα μήνυμα."
                    : "Please write a message.";
        }
        elseif (
            mb_strlen(
                $message,
                "UTF-8"
            ) > 1000
        ) {
            $chatError =
                $isGreek
                    ? "Το μήνυμα είναι πολύ μεγάλο."
                    : "The message is too long.";
        }
        else {
            $messageStatement =
                $conn->prepare(
                    "
                    INSERT INTO community_messages
                    (
                        community_id,
                        user_id,
                        message
                    )
                    VALUES (?, ?, ?)
                    "
                );
            if (!$messageStatement) {
                $chatError =
                    $isGreek
                        ? "Δεν ήταν δυνατή η αποστολή του μηνύματος."
                        : "The message could not be sent.";
            }
            else {
                $messageStatement
                    ->bind_param(
                        "iis",
                        $communityId,
                        $currentUserId,
                        $message
                    );
                if (
                    $messageStatement
                        ->execute()
                ) {
                    $messageStatement
                        ->close();

                    // One "new messages" notification for the other members
                    emNotifyChatMessage($conn, (int) $communityId, $currentUserId);

                    /*
                     * Redirect ώστε το refresh
                     * να μην ξαναστείλει το μήνυμα.
                     */
                    header(
                        "Location: community.php?id="
                        . $communityId
                        . "&sent=1#community-chat"
                    );
                    exit();
                }
                else {
                    $chatError =
                        $isGreek
                            ? "Δεν ήταν δυνατή η αποστολή του μηνύματος."
                            : "The message could not be sent.";
                    $messageStatement
                        ->close();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Chat Messages
|--------------------------------------------------------------------------
|
| Φορτώνουμε τα τελευταία 100.
|
*/
$messages = [];
$messagesStatement =
    $conn->prepare(
        "
        SELECT
            cm.id,
            cm.user_id,
            cm.message,
            cm.created_at,
            u.name,
            u.surname
        FROM community_messages cm
        INNER JOIN users u
            ON u.id = cm.user_id
        WHERE cm.community_id = ?
        ORDER BY
            cm.created_at DESC,
            cm.id DESC
        LIMIT 100
        "
    );
if ($messagesStatement) {
    $messagesStatement->bind_param(
        "i",
        $communityId
    );
    $messagesStatement->execute();
    $messagesResult =
        $messagesStatement->get_result();
    while (
        $chatMessage =
            $messagesResult->fetch_assoc()
    ) {
        $messages[] =
            $chatMessage;
    }

    /*
     * Το query έφερε newest -> oldest.
     * Στο chat θέλουμε oldest -> newest.
     */
    $messages =
        array_reverse($messages);
    $messagesStatement->close();
}

/*
|--------------------------------------------------------------------------
| Page Information
|--------------------------------------------------------------------------
*/
$communityDate =
    date(
        "d/m/Y",
        strtotime(
            $community["created_at"]
        )
    );
$creatorFullName =
    trim(
        $community["creator_name"]
        . " "
        . $community["creator_surname"]
    );
$statusText =
    communityStatusLabel(
        $community["status"],
        $isGreek
    );
$acceptedCount = 0;
$pendingCount = 0;
foreach ($members as $member) {
    if (
        $member["invitation_status"]
        === "accepted"
    ) {
        $acceptedCount++;
    }
    elseif (
        $member["invitation_status"]
        === "pending"
    ) {
        $pendingCount++;
    }
}
$activationConflictCount = 0;
if (
    $community["status"] === "pending"
    && $acceptedCount === 5
    && $pendingCount === 0
) {
    $activationConflictStatement = $conn->prepare(
        "
        SELECT COUNT(DISTINCT current_member.user_id) AS conflict_count
        FROM community_members current_member
        WHERE current_member.community_id = ?
          AND current_member.invitation_status = 'accepted'
          AND EXISTS (
              SELECT 1
              FROM community_members other_member
              INNER JOIN communities other_community
                  ON other_community.id = other_member.community_id
                 AND other_community.status = 'active'
              WHERE other_member.user_id = current_member.user_id
                AND other_member.community_id <> current_member.community_id
                AND other_member.invitation_status = 'accepted'
          )
        "
    );
    if (!$activationConflictStatement) {
        die("Database error: " . $conn->error);
    }
    $activationConflictStatement->bind_param("i", $communityId);
    $activationConflictStatement->execute();
    $activationConflictResult = $activationConflictStatement->get_result();
    $activationConflictRow = $activationConflictResult->fetch_assoc();
    $activationConflictCount = (int) ($activationConflictRow["conflict_count"] ?? 0);
    $activationConflictStatement->close();
    if ($activationConflictCount > 0) {
        $statusText = $isGreek
            ? "Αναμονή Ενεργοποίησης"
            : "Waiting for Activation";
    }
}
?>
<!DOCTYPE html>
<html
    lang="<?php
        echo $isGreek
            ? "el"
            : "en";
    ?>"
>
<head>
    <?php include "head_assets.php"; ?>
<meta charset="UTF-8">
<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>
        <link rel="stylesheet" href="css/community.css?v=<?php echo ASSET_VERSION; ?>">
<link
    rel="stylesheet"
    href="css/chatbot.css?v=<?php echo ASSET_VERSION; ?>"
>
<title>
<?php echo safe($community["name"]); ?>
- EnergyMatch
</title>
</head>
<body>

<section class="hero-section">

    <!-- =========================================
    NAVBAR
    ========================================= -->
    <?php include "navbar.php"; ?>

    <main class="community-container">

        <?php if (($_GET["report_access"] ?? "") === "denied"): ?>
            <div class="report-denied-notice">
                <i data-lucide="shield-alert"></i>
                <?php echo t("report_access_denied"); ?>
            </div>
        <?php endif; ?>

        <!-- =========================================
        COMMUNITY HEADER
        ========================================= -->

        <section class="community-header">
            <div>
                <p class="section-label">
                    <?php
                    echo $isGreek
                        ? "Η Ενεργειακή μου Κοινότητα"
                        : "My Energy Community";
                    ?>
                </p>
                <h1>
                    <?php echo safe($community["name"]); ?>
                </h1>
                <p class="community-subtitle">
                    <?php
                    echo $isGreek
                        ? "Ο χώρος της ομάδας σου στο EnergyMatch."
                        : "Your team's shared space on EnergyMatch.";
                    ?>
                </p>
            </div>
            <div
                style="
                display:flex;
                flex-direction:column;
                align-items:flex-end;
                gap:12px;
                "
            >
                <div
                    class="community-status status-<?php
                    echo safe($community["status"]);
                    ?>"
                >
                    <span class="status-dot"></span>
                    <?php echo safe($statusText); ?>
                </div>

                <?php if (
                    $community["status"] === "active"
                    &&
                    $currentInvitationStatus === "accepted"
                ): ?>
                    <a
                        href="leave_community.php?community_id=<?php echo (int) $communityId; ?>"
                        style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        gap:7px;
                        padding:8px 13px;
                        background:#fff5f5;
                        color:#c62828;
                        border:1px solid #ef9a9a;
                        border-radius:10px;
                        font-size:13px;
                        font-weight:700;
                        text-decoration:none;
                        transition:0.2s;
                        "
                    >
                        <i data-lucide="log-out"></i>
                        <?php
                        echo $isGreek
                            ? "Αποχώρηση από την Κοινότητα"
                            : "Leave Community";
                        ?>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- =========================================
        SUMMARY
        ========================================= -->

        <section class="community-summary">
            <div class="summary-item">
                <span class="summary-icon">
                    <i data-lucide="users"></i>
                </span>
                <div>
                    <p>
                        <?php
                        echo $isGreek
                            ? "Μέλη"
                            : "Members";
                        ?>
                    </p>
                    <h2>
                        <?php echo count($members); ?>/5
                    </h2>
                </div>
            </div>
            <div class="summary-item">
                <span class="summary-icon">
                    <i data-lucide="circle-check"></i>
                </span>
                <div>
                    <p>
                        <?php
                        echo $isGreek
                            ? "Αποδέχτηκαν"
                            : "Accepted";
                        ?>
                    </p>
                    <h2>
                        <?php echo $acceptedCount; ?>/5
                    </h2>
                </div>
            </div>
            <div class="summary-item">
                <span class="summary-icon">
                    <i data-lucide="coins"></i>
                </span>
                <div>
                    <p>
                        <?php
                        echo $isGreek
                            ? "Συνολική Αξία"
                            : "Total Value";
                        ?>
                    </p>
                    <h2>
                        <?php echo moneyValue($totalGroupValue); ?>
                    </h2>
                </div>
            </div>
            <div class="summary-item">
                <span class="summary-icon">
                    <i data-lucide="calendar"></i>
                </span>
                <div>
                    <p>
                        <?php
                        echo $isGreek
                            ? "Δημιουργήθηκε"
                            : "Created";
                        ?>
                    </p>
                    <h2>
                        <?php echo safe($communityDate); ?>
                    </h2>
                </div>
            </div>
        </section>

        <?php if (
            $community["status"] === "pending"
        ): ?>
            <div class="pending-community-message">
                <span><?php echo ($acceptedCount === 5 && $pendingCount === 0) ? '<i data-lucide="lock"></i>' : '<i data-lucide="hourglass"></i>'; ?></span>
                <div>
                    <strong>
                        <?php
                        if ($acceptedCount === 5 && $pendingCount === 0 && $activationConflictCount > 0) {
                            echo $isGreek
                                ? "Η κοινότητα έχει 5/5 αποδοχές αλλά δεν μπορεί ακόμη να ενεργοποιηθεί."
                                : "The community has 5/5 acceptances but cannot be activated yet.";
                        } else {
                            echo $isGreek
                                ? "Η κοινότητα περιμένει τις απαντήσεις των μελών."
                                : "The community is waiting for member responses.";
                        }
                        ?>
                    </strong>
                    <p>
                        <?php
                        if ($acceptedCount === 5 && $pendingCount === 0 && $activationConflictCount > 0) {
                            echo $isGreek
                                ? "Έχουν αποδεχτεί και τα 5 μέλη. "
                                    . $activationConflictCount
                                    . ($activationConflictCount === 1
                                        ? " μέλος συμμετέχει ήδη σε άλλη ενεργή κοινότητα. "
                                        : " μέλη συμμετέχουν ήδη σε άλλη ενεργή κοινότητα. ")
                                    . "Μόλις δεν υπάρχει σύγκρουση ενεργής συμμετοχής, η κοινότητα θα ενεργοποιηθεί αυτόματα."
                                : "All 5 members have accepted. "
                                    . $activationConflictCount
                                    . ($activationConflictCount === 1
                                        ? " member already belongs to another active community. "
                                        : " members already belong to another active community. ")
                                    . "As soon as there is no active-membership conflict, the community will activate automatically.";
                        } else {
                            echo $isGreek
                                ? "Έχουν αποδεχτεί "
                                    . $acceptedCount
                                    . " από τα 5 μέλη. "
                                    . $pendingCount
                                    . " προσκλήσεις βρίσκονται ακόμη σε αναμονή."
                                : $acceptedCount
                                    . " of 5 members have accepted. "
                                    . $pendingCount
                                    . " invitations are still pending.";
                        }
                        ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- =========================================
        MEMBERS
        ========================================= -->

        <section class="members-section">
            <div class="section-heading">
                <div>
                    <p class="section-label">
                        <?php
                        echo $isGreek
                            ? "Η Ομάδα"
                            : "Community Team";
                        ?>
                    </p>
                    <h2>
                        <?php
                        echo $isGreek
                            ? "Μέλη Κοινότητας"
                            : "Community Members";
                        ?>
                    </h2>
                </div>
                <span class="member-counter">
                    <?php echo count($members); ?> / 5
                </span>
            </div>
            <div class="members-grid">

                <?php foreach ($members as $member): ?>
                    <?php
                    $memberId =
                        (int) $member["user_id"];
                    $memberFullName =
                        trim(
                            $member["name"]
                            . " "
                            . $member["surname"]
                        );
                    $initials =
                        getInitials(
                            $member["name"],
                            $member["surname"]
                        );
                    $location =
                        trim(
                            (string) (
                                $member["location"]
                                ?? ""
                            )
                        );
                    if ($location === "") {
                        $location =
                            $isGreek
                                ? "Δεν έχει οριστεί τοποθεσία"
                                : "Location not specified";
                    }
                    $isCreator =
                        $memberId
                        ===
                        (int) $community["created_by"];
                    $isCurrentUser =
                        $memberId
                        ===
                        $currentUserId;
                    $invitationStatus =
                        $member["invitation_status"];
                    ?>
                    <article class="member-card">
                        <div class="member-header">
                            <div class="member-avatar">
                                <?php echo safe($initials); ?>
                            </div>
                            <div class="member-main-info">
                                <div class="member-name-line">
                                    <h3>
                                        <?php echo safe($memberFullName); ?>
                                    </h3>

                                    <?php if ($isCreator): ?>
                                        <span class="creator-badge">
                                            <?php
                                            echo $isGreek
                                                ? "Δημιουργός"
                                                : "Creator";
                                            ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($isCurrentUser): ?>
                                        <span class="you-badge">
                                            <?php
                                            echo $isGreek
                                                ? "Εσύ"
                                                : "You";
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="member-location">
                                    <i data-lucide="map-pin"></i> <?php echo safe($location); ?>
                                </p>
                            </div>
                            <span
                                class="
                                invitation-badge
                                invitation-<?php
                                echo safe(
                                $invitationStatus
                                );
                                ?>
                                "
                                >
                                <?php
                                echo safe(
                                    invitationLabel(
                                        $invitationStatus,
                                        $isGreek
                                    )
                                );
                                ?>
                            </span>
                        </div>
                        <div class="member-divider"></div>

                        <!-- CONTRIBUTIONS -->
                        <div class="member-section">
                            <h4>
                                <?php
                                echo $isGreek
                                    ? "Συνεισφορές"
                                    : "Contributions";
                                ?>
                            </h4>
                            <div class="contribution-tags">

                                <?php if (
                                    !empty(
                                        $member["contribution_types"]
                                    )
                                ): ?>

                                    <?php foreach (
                                        $member["contribution_types"]
                                        as $type
                                    ): ?>
                                        <span class="contribution-tag">
                                            <?php
                                            echo safe(
                                                contributionLabel(
                                                    $type,
                                                    $isGreek
                                                )
                                            );
                                            ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="empty-text">
                                        <?php
                                        echo $isGreek
                                            ? "Δεν υπάρχουν διαθέσιμα στοιχεία."
                                            : "No contribution data available.";
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- VALUES -->
                        <div class="member-values">
                            <div class="member-value-box">
                                <span>
                                    <?php
                                    echo $isGreek
                                        ? "Αξία Συνεισφοράς"
                                        : "Contribution Value";
                                    ?>
                                </span>
                                <strong>
                                    <?php
                                    echo moneyValue(
                                        $member["contribution_value"]
                                    );
                                    ?>
                                </strong>
                            </div>
                            <div class="member-value-box fair-share-box">
                                <span>
                                    Fair Share
                                </span>
                                <strong>
                                    <?php
                                    echo percentValue(
                                        $member["fair_share"]
                                    );
                                    ?>
                                </strong>
                            </div>
                            <div class="member-value-box">
                                <span>
                                    Minimum Share
                                </span>
                                <strong>
                                    <?php
                                    echo percentValue(
                                        $member["minimum_share"]
                                    );
                                    ?>
                                </strong>
                            </div>
                        </div>

                        <?php if (!$isCurrentUser): ?>
                            <div
                                style="
                                display:flex;
                                gap:10px;
                                align-items:center;
                                flex-wrap:wrap;
                                margin-top:16px;
                                "
                            >
                                <a
                                    href="view_profile.php?id=<?php echo $memberId; ?>"
                                    class="view-member-btn"
                                    style="
                                    margin:0;
                                    height:48px;
                                    box-sizing:border-box;
                                    display:inline-flex;
                                    align-items:center;
                                    justify-content:center;
                                    "
                                >
                                    <?php
                                    echo $isGreek
                                        ? "Προβολή Προφίλ"
                                        : "View Profile";
                                    ?>
                                </a>

                                <?php if (
                                    $community["status"] === "active"
                                    &&
                                    $currentInvitationStatus === "accepted"
                                    &&
                                    $invitationStatus === "accepted"
                                ): ?>
                                    <a
                                        href="report_user.php?community_id=<?php
                                        echo (int) $communityId;
                                        ?>&user_id=<?php
                                        echo (int) $memberId;
                                        ?>"
                                        style="
                                        margin:0;
                                        height:48px;
                                        box-sizing:border-box;
                                        display:inline-flex;
                                        align-items:center;
                                        justify-content:center;
                                        gap:6px;
                                        padding:0 14px;
                                        background:#fff5f5;
                                        color:#c62828;
                                        border:1px solid #ef9a9a;
                                        border-radius:10px;
                                        font-size:13px;
                                        font-weight:700;
                                        text-decoration:none;
                                        "
                                    >
                                        <i data-lucide="flag"></i>
                                        <?php
                                        echo $isGreek
                                            ? "Αναφορά χρήστη"
                                            : "Report User";
                                        ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <a
                                href="profile.php"
                                class="view-member-btn current-user-btn"
                            >
                                <?php
                                echo $isGreek
                                    ? "Το Προφίλ μου"
                                    : "My Profile";
                                ?>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- =========================================
        COMMUNITY CHAT
        ========================================= -->

        <section
            class="chat-section"
            id="community-chat"
        >
            <div class="section-heading chat-heading">
                <div>
                    <p class="section-label">
                        EnergyMatch Community
                    </p>
                    <h2>
                        <i data-lucide="message-circle"></i>
                        <?php
                        echo $isGreek
                            ? "Συνομιλία Ομάδας"
                            : "Community Chat";
                        ?>
                    </h2>
                </div>
                <span class="chat-member-count">
                    <i data-lucide="users"></i> <?php echo $acceptedCount; ?>
                </span>
            </div>

            <?php if ($chatError !== ""): ?>
                <div class="chat-error">
                    <?php echo safe($chatError); ?>
                </div>
            <?php endif; ?>
            <div
                class="chat-messages"
                id="chatMessages"
            >

                <?php if (empty($messages)): ?>
                    <div class="empty-chat">
                        <div class="empty-chat-icon">
                            <i data-lucide="message-circle"></i>
                        </div>
                        <h3>
                            <?php
                            echo $isGreek
                                ? "Δεν υπάρχουν μηνύματα ακόμη"
                                : "No messages yet";
                            ?>
                        </h3>
                        <p>
                            <?php
                            if ($chatWaitingForMembers) {
                                echo $isGreek
                                    ? "Τα μηνύματα θα εμφανίζονται εδώ μόλις ενεργοποιηθεί η κοινότητα."
                                    : "Messages will appear here once the community is active.";
                            } else {
                                echo $isGreek
                                    ? "Ξεκίνα τη συζήτηση με τα μέλη της ενεργειακής σου κοινότητας."
                                    : "Start the conversation with your energy community members.";
                            }
                            ?>
                        </p>
                    </div>
                <?php else: ?>

                    <?php foreach (
                        $messages as $chatMessage
                    ): ?>
                        <?php
                        $isOwnMessage =
                            (int) $chatMessage["user_id"]
                            ===
                            $currentUserId;
                        $messageName =
                            trim(
                                $chatMessage["name"]
                                . " "
                                . $chatMessage["surname"]
                            );
                        $messageTime =
                            date(
                                "H:i",
                                strtotime(
                                    $chatMessage["created_at"]
                                )
                            );
                        $messageDate =
                            date(
                                "d/m/Y",
                                strtotime(
                                    $chatMessage["created_at"]
                                )
                            );
                        ?>
                        <div
                            class="
                            chat-message-row
                            <?php
                            echo $isOwnMessage
                            ? "own-message-row"
                            : "";
                            ?>
                            "
                            >
                            <div class="chat-message-meta">

                                <?php if ($isOwnMessage): ?>
                                    <span>
                                        <?php
                                        echo $isGreek
                                            ? "Εσύ"
                                            : "You";
                                        ?>
                                    </span>
                                <?php else: ?>
                                    <span>
                                        <?php echo safe($messageName); ?>
                                    </span>
                                <?php endif; ?>
                                <small>
                                    <?php
                                    echo safe(
                                        $messageDate
                                        . " • "
                                        . $messageTime
                                    );
                                    ?>
                                </small>
                            </div>
                            <div
                                class="
                                message-bubble
                                <?php
                                echo $isOwnMessage
                                ? "own-message"
                                : "";
                                ?>
                                "
                                >
                                <?php
                                echo nl2br(
                                    safe(
                                        $chatMessage["message"]
                                    )
                                );
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($canChat): ?>

                <form
                    method="POST"
                    action="community.php?id=<?php echo $communityId; ?>#community-chat"
                    class="chat-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo safe($chatCsrfToken); ?>"
                    >
                    <textarea
                        name="message"
                        id="communityMessage"
                        maxlength="1000"
                        placeholder="<?php
                            echo $isGreek
                                ? "Γράψε ένα μήνυμα στην ομάδα..."
                                : "Write a message to your community...";
                        ?>"
                        required
                    ></textarea>
                    <button
                        type="submit"
                        name="send_message"
                        value="1"
                    >
                        <span>
                            <?php
                            echo $isGreek
                                ? "Αποστολή"
                                : "Send";
                            ?>
                        </span>
                        <i data-lucide="send-horizontal"></i>
                    </button>
                </form>
                <p class="chat-help">
                    <?php
                    echo $isGreek
                        ? "Τα μηνύματα είναι ορατά μόνο στα μέλη αυτής της κοινότητας."
                        : "Messages are visible only to members of this community.";
                    ?>
                </p>
            <?php else: ?>
                <div class="chat-disabled">
                    <i data-lucide="lock"></i>
                    <?php
                    if ($chatWaitingForMembers) {
                        echo $isGreek
                            ? "Η συνομιλία ανοίγει όταν αποδεχτούν και τα 5 μέλη την πρόταση (τώρα: " . (int) $acceptedCount . "/5)."
                            : "The chat opens when all 5 members have accepted the proposal (now: " . (int) $acceptedCount . "/5).";
                    } else {
                        echo $isGreek
                            ? "Η συνομιλία δεν είναι διαθέσιμη για αυτόν τον λογαριασμό."
                            : "Community chat is not available for this account.";
                    }
                    ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- =========================================
        BUTTONS
        ========================================= -->
        <div class="community-buttons">
            <a
                href="matches.php"
                class="secondary-btn"
            >
                <i data-lucide="arrow-left"></i>
                <?php
                echo $isGreek
                    ? "Πίσω στα Matches"
                    : "Back to Matches";
                ?>
            </a>

            <?php if (
                (int) $community["created_by"]
                ===
                $currentUserId
            ): ?>
                <a
                    href="create_community.php"
                    class="primary-btn"
                >
                    ＋
                    <?php
                    echo $isGreek
                        ? "Δημιουργία Νέας Κοινότητας"
                        : "Create New Community";
                    ?>
                </a>
            <?php endif; ?>
        </div>
    </main>
</section>

<!-- =========================================
CHATBOT
========================================= -->
<?php
include "chatbot.php";
?>

<script src="js/chatbot.js?v=<?php echo ASSET_VERSION; ?>"></script>

<script>

/*
|--------------------------------------------------------------------------
| Scroll chat automatically to latest message
|--------------------------------------------------------------------------
*/
const chatMessages =
    document.getElementById(
        "chatMessages"
    );
if (chatMessages) {
    chatMessages.scrollTop =
        chatMessages.scrollHeight;
}

/*
|--------------------------------------------------------------------------
| Send without reloading the page
|--------------------------------------------------------------------------
| The form is posted in the background (same fields as before) and only the
| message list is refreshed, so the page does not jump.
| If anything goes wrong, the form is sent the normal way.
|--------------------------------------------------------------------------
*/
const chatForm =
    document.querySelector(".chat-form");
const communityMessage =
    document.getElementById(
        "communityMessage"
    );
let chatSending = false;

function sendChatNormally() {
    const flag = document.createElement("input");
    flag.type = "hidden";
    flag.name = "send_message";
    flag.value = "1";
    chatForm.appendChild(flag);
    chatForm.submit();
}

async function sendChatMessage() {
    if (chatSending || communityMessage.value.trim() === "") {
        return;
    }

    chatSending = true;

    const sendButton = chatForm.querySelector('[name="send_message"]');
    const data = new FormData(chatForm);
    data.append("send_message", "1");
    sendButton.disabled = true;

    try {
        const response = await fetch(chatForm.action, {
            method: "POST",
            body: data,
            credentials: "same-origin"
        });
        const page = new DOMParser().parseFromString(
            await response.text(),
            "text/html"
        );
        const newMessages = page.getElementById("chatMessages");

        if (!response.ok || !newMessages) {
            throw new Error("unexpected response");
        }

        /* error box (e.g. message too long) */
        const oldError = document.querySelector(".chat-error");
        const newError = page.querySelector(".chat-error");

        if (oldError) {
            oldError.remove();
        }

        if (newError) {
            chatMessages.before(newError);
        } else {
            communityMessage.value = "";
        }

        chatMessages.innerHTML = newMessages.innerHTML;
        chatMessages.scrollTop = chatMessages.scrollHeight;

        if (window.emRefreshIcons) {
            window.emRefreshIcons();
        }
    } catch (error) {
        sendChatNormally();
        return;
    } finally {
        chatSending = false;
        sendButton.disabled = false;
    }

    communityMessage.focus();
}

if (chatForm && communityMessage && chatMessages) {
    chatForm.addEventListener("submit", function (event) {
        event.preventDefault();
        sendChatMessage();
    });

    /* Enter = send, Shift + Enter = new line */
    communityMessage.addEventListener("keydown", function (event) {
        if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            sendChatMessage();
        }
    });
}
</script>
</body>
</html>
