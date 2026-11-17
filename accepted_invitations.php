<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once "db.php";
require_once "lang.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
$currentUserId =
    (int) $_SESSION["user_id"];

function safe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| MY PROPOSALS
|--------------------------------------------------------------------------
| 1. Sent by me      proposals I created that wait for the others
| 2. Accepted by me  proposals of others that I accepted, still waiting
| 3. History         proposals that were declined or cancelled, or that
|                    I declined or left, with the reason
| The active community itself is on community.php ("My community").
|--------------------------------------------------------------------------
*/
/* History: 10 per page, newest first */
$historyPerPage = 10;

$communityStatement = $conn->prepare(
    "
    SELECT
        c.id,
        c.name,
        c.created_at,
        c.created_by,
        c.status,
        me.invitation_status AS my_status,
        me.responded_at AS my_responded_at
    FROM communities c
    INNER JOIN community_members me
        ON me.community_id = c.id
       AND me.user_id = ?
    WHERE c.status IN ('pending', 'rejected', 'cancelled')
       OR me.invitation_status IN ('rejected', 'left')
    ORDER BY c.created_at DESC, c.id DESC
    "
);
$communityStatement->bind_param("i", $currentUserId);
$communityStatement->execute();
$communityRows = $communityStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$communityStatement->close();

$sentProposals = [];
$acceptedProposals = [];
$historyProposals = [];

foreach ($communityRows as $row) {
    $row["id"] = (int) $row["id"];
    $row["created_by"] = (int) $row["created_by"];
    $isMine = $row["created_by"] === $currentUserId;

    if ($row["status"] === "pending" && $row["my_status"] === "accepted") {
        if ($isMine) {
            $sentProposals[] = $row;
        } else {
            $acceptedProposals[] = $row;
        }
    } elseif ($row["status"] !== "pending" || in_array($row["my_status"], ["rejected", "left"], true)) {
        if ($row["status"] !== "active" || $row["my_status"] === "left") {
            $historyProposals[] = $row;
        }
    }
}

$historyTotal = count($historyProposals);
$historyPages = max(1, (int) ceil($historyTotal / $historyPerPage));
$historyPage = min($historyPages, max(1, (int) ($_GET["history_page"] ?? 1)));
$historyProposals = array_slice($historyProposals, ($historyPage - 1) * $historyPerPage, $historyPerPage);

/* Members of every community shown on the page (one query) */
$shownIds = array_map(
    function ($row) { return $row["id"]; },
    array_merge($sentProposals, $acceptedProposals, $historyProposals)
);
$membersByCommunity = [];
$conflictActorByCommunity = [];

/* Am I in an active community now? (then my other proposals were cancelled because of me) */
$activeCheck = $conn->prepare(
    "SELECT 1 FROM community_members cm
     INNER JOIN communities c ON c.id = cm.community_id AND c.status = 'active'
     WHERE cm.user_id = ? AND cm.invitation_status = 'accepted' LIMIT 1"
);
$activeCheck->bind_param("i", $currentUserId);
$activeCheck->execute();
$iAmInActiveCommunity = $activeCheck->get_result()->num_rows > 0;
$activeCheck->close();

if ($shownIds) {
    $idList = implode(",", array_map("intval", $shownIds));

    $memberResult = $conn->query(
        "SELECT cm.community_id, cm.user_id, cm.invitation_status, u.name, u.surname
         FROM community_members cm
         INNER JOIN users u ON u.id = cm.user_id
         WHERE cm.community_id IN ($idList)
         ORDER BY cm.id ASC"
    );
    while ($member = $memberResult->fetch_assoc()) {
        $membersByCommunity[(int) $member["community_id"]][] = $member;
    }
    $memberResult->free();

    /* "cancelled because X joined another community" (notifications.php) */
    try {
        /*
         * The members who were in the community that became active did not
         * get this notification; everyone else in the proposal did.
         * So: I got it -> show who joined; I did not -> I was the one.
         */
        $conflictResult = $conn->query(
            "SELECT cn.community_id, cn.recipient_user_id, u.name, u.surname
             FROM community_notifications cn
             INNER JOIN users u ON u.id = cn.actor_user_id
             WHERE cn.notification_type = 'community_cancelled_conflict'
               AND cn.community_id IN ($idList)"
        );
        while ($conflict = $conflictResult->fetch_assoc()) {
            $conflictCommunityId = (int) $conflict["community_id"];
            if (!isset($conflictActorByCommunity[$conflictCommunityId])) {
                $conflictActorByCommunity[$conflictCommunityId] = "@me";
            }
            if ((int) $conflict["recipient_user_id"] === $currentUserId) {
                $conflictActorByCommunity[$conflictCommunityId] = trim($conflict["name"] . " " . $conflict["surname"]);
            }
        }
        $conflictResult->free();
    } catch (Throwable $error) {
        /* older database without this notification type */
    }

    /* "cancelled because a member deleted their account / was removed" */
    try {
        $removedResult = $conn->query(
            "SELECT community_id, notification_type
             FROM community_notifications
             WHERE notification_type IN ('member_removed', 'member_account_deleted')
               AND recipient_user_id = " . (int) $currentUserId . "
               AND community_id IN ($idList)"
        );
        while ($removed = $removedResult->fetch_assoc()) {
            $conflictActorByCommunity[(int) $removed["community_id"]] = "@" . $removed["notification_type"];
        }
        $removedResult->free();
    } catch (Throwable $error) {
        /* older database without these notification types */
    }
}

function proposalMemberName(array $member): string
{
    return trim($member["name"] . " " . $member["surname"]);
}

/* Why a proposal is in the history (text with the name in bold) */
function proposalHistoryReason(array $community, array $members, array $conflictActors, int $currentUserId, bool $iAmInActiveCommunity): array
{
    $find = function ($status) use ($members, $currentUserId) {
        foreach ($members as $member) {
            if ($member["invitation_status"] === $status && (int) $member["user_id"] !== $currentUserId) {
                return proposalMemberName($member);
            }
        }
        return "";
    };
    $fill = function ($key, $name) {
        return str_replace("{name}", "<strong>" . safe($name) . "</strong>", t($key));
    };

    if ($community["my_status"] === "rejected") {
        return ["icon" => "user-x", "tone" => "rejected", "text" => t("history_you_declined")];
    }
    if ($community["my_status"] === "left") {
        return ["icon" => "log-out", "tone" => "left", "text" => t("history_you_left")];
    }
    if ($community["status"] === "rejected") {
        $name = $find("rejected");
        return ["icon" => "user-x", "tone" => "rejected", "text" => $name !== "" ? $fill("history_declined_by", $name) : t("history_declined")];
    }
    if ($community["status"] === "cancelled") {
        $leftName = $find("left");
        if ($leftName !== "") {
            return ["icon" => "log-out", "tone" => "left", "text" => $fill("history_member_left", $leftName)];
        }
        $conflictActor = $conflictActors[(int) $community["id"]] ?? null;
        if ($conflictActor === "@member_account_deleted") {
            return ["icon" => "user-x", "tone" => "left", "text" => t("history_member_deleted")];
        }
        if ($conflictActor === "@member_removed") {
            return ["icon" => "user-x", "tone" => "rejected", "text" => t("history_member_removed")];
        }
        if ($conflictActor === "@me" || ($conflictActor === null && $iAmInActiveCommunity)) {
            return ["icon" => "circle-slash", "tone" => "", "text" => t("history_you_joined")];
        }
        if ($conflictActor !== null) {
            return ["icon" => "circle-slash", "tone" => "rejected", "text" => $fill("history_conflict", $conflictActors[(int) $community["id"]])];
        }
        return ["icon" => "circle-slash", "tone" => "rejected", "text" => t("history_cancelled")];
    }
    return ["icon" => "info", "tone" => "", "text" => ""];
}

/* Small chips with each member and their answer */
function renderProposalMembers(array $members, int $creatorId, int $currentUserId): string
{
    $icons = ["accepted" => "check", "pending" => "hourglass", "rejected" => "x", "left" => "log-out"];
    $html = '<ul class="proposal-members">';

    foreach ($members as $member) {
        $status = $member["invitation_status"];
        $isYou = (int) $member["user_id"] === $currentUserId;
        $html .= '<li class="proposal-member is-' . safe($status) . '" title="' . safe(t("member_status_" . $status)) . '">';
        $html .= '<i data-lucide="' . ($icons[$status] ?? "circle") . '"></i>';
        $html .= '<span>' . safe($isYou ? t("you_label") : proposalMemberName($member)) . '</span>';
        if ((int) $member["user_id"] === $creatorId) {
            $html .= '<small>' . safe(t("creator_label")) . '</small>';
        }
        $html .= '</li>';
    }

    return $html . '</ul>';
}

function countMembers(array $members, string $status): int
{
    return count(array_filter($members, function ($member) use ($status) {
        return $member["invitation_status"] === $status;
    }));
}
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/community.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="css/chatbot.css?v=<?php echo ASSET_VERSION; ?>">

    <title>EnergyMatch - <?php echo t("my_proposals_title"); ?></title>
</head>
<body>

<section class="hero-section">

<?php include "navbar.php"; ?>

<main class="accepted-page-container">

    <section class="accepted-page-header">
        <p class="section-label">ENERGYMATCH COMMUNITY</p>
        <h1><?php echo t("my_proposals_title"); ?></h1>
        <p><?php echo t("my_proposals_intro"); ?></p>
    </section>

    <?php
    $waitingSections = [
        ["key" => "sent", "title" => t("proposals_sent_title"), "text" => t("proposals_sent_text"), "icon" => "send", "items" => $sentProposals, "empty" => t("proposals_sent_empty"), "waiting" => t("proposals_sent_waiting")],
        ["key" => "accepted", "title" => t("proposals_accepted_title"), "text" => t("proposals_accepted_text"), "icon" => "check", "items" => $acceptedProposals, "empty" => t("proposals_accepted_empty"), "waiting" => t("accepted_invitation_waiting_message")],
    ];
    ?>

    <?php foreach ($waitingSections as $section): ?>
        <section class="proposal-section" id="<?php echo $section["key"]; ?>">
            <h2 class="proposal-section-title">
                <i data-lucide="<?php echo $section["icon"]; ?>"></i>
                <?php echo $section["title"]; ?>
                <span class="proposal-section-count"><?php echo count($section["items"]); ?></span>
            </h2>
            <p class="proposal-section-text"><?php echo $section["text"]; ?></p>

            <?php if (empty($section["items"])): ?>
                <div class="proposal-empty"><?php echo $section["empty"]; ?></div>
            <?php else: ?>
                <div class="accepted-community-list">
                    <?php foreach ($section["items"] as $community): ?>
                        <?php $members = $membersByCommunity[$community["id"]] ?? []; ?>
                        <article class="accepted-community-card">
                            <div class="accepted-community-card-top">
                                <div>
                                    <h2><?php echo safe($community["name"]); ?></h2>
                                    <p class="accepted-community-date">
                                        <i data-lucide="calendar"></i>
                                        <?php echo t("community_created"); ?> <?php echo safe(date("d/m/Y H:i", strtotime($community["created_at"]))); ?>
                                    </p>
                                </div>
                                <span class="waiting-status">
                                    <i data-lucide="hourglass"></i>
                                    <?php echo countMembers($members, "accepted"); ?>/5 <?php echo t("proposals_accepted_short"); ?>
                                </span>
                            </div>

                            <?php echo renderProposalMembers($members, $community["created_by"], $currentUserId); ?>

                            <div class="accepted-community-footer">
                                <p class="accepted-community-message">
                                    <?php echo $section["waiting"]; ?>
                                </p>
                                <a href="community.php?id=<?php echo (int) $community["id"]; ?>" class="accepted-community-open">
                                    <?php echo t("view_community"); ?>
                                    <i data-lucide="arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <!-- HISTORY -->
    <section class="proposal-section" id="history">
        <h2 class="proposal-section-title">
            <i data-lucide="history"></i>
            <?php echo t("proposals_history_title"); ?>
            <span class="proposal-section-count"><?php echo $historyTotal; ?></span>
        </h2>
        <p class="proposal-section-text"><?php echo t("proposals_history_text"); ?></p>

        <?php if (empty($historyProposals)): ?>
            <div class="proposal-empty"><?php echo t("proposals_history_empty"); ?></div>
        <?php else: ?>
            <div class="proposal-history-list">
                <?php foreach ($historyProposals as $community): ?>
                    <?php
                    $members = $membersByCommunity[$community["id"]] ?? [];
                    $reason = proposalHistoryReason($community, $members, $conflictActorByCommunity, $currentUserId, $iAmInActiveCommunity);
                    ?>
                    <article class="proposal-history-card <?php echo $reason["tone"]; ?>">
                        <div class="proposal-history-icon"><i data-lucide="<?php echo $reason["icon"]; ?>"></i></div>
                        <div class="proposal-history-body">
                            <div class="proposal-history-top">
                                <strong><?php echo safe($community["name"]); ?></strong>
                                <?php if ($community["created_by"] === $currentUserId): ?>
                                    <span class="proposal-own-badge"><?php echo t("proposal_own_badge"); ?></span>
                                <?php endif; ?>
                                <span class="proposal-history-date"><?php echo safe(date("d/m/Y", strtotime($community["created_at"]))); ?></span>
                            </div>
                            <p class="proposal-history-reason"><?php echo $reason["text"]; ?></p>
                            <?php echo renderProposalMembers($members, $community["created_by"], $currentUserId); ?>
                            <?php
                            /* "Propose this group again": cancelled or declined proposals (not the
                               community I left), only while I am not in an active community.
                               create_community.php checks who is still available. */
                            if (
                                !$iAmInActiveCommunity
                                && in_array($community["status"], ["cancelled", "rejected"], true)
                                && $community["my_status"] !== "left"
                            ):
                            ?>
                                <div class="proposal-history-actions">
                                    <a class="ui-btn ui-btn-ghost proposal-reuse-btn" href="create_community.php?reuse=<?php echo (int) $community["id"]; ?>" title="<?php echo safe(t("reuse_button_hint")); ?>">
                                        <i data-lucide="rotate-ccw"></i>
                                        <?php echo t("reuse_button"); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($historyPages > 1): ?>
                <nav class="proposal-pagination" aria-label="<?php echo t("proposals_history_title"); ?>">
                    <?php if ($historyPage > 1): ?>
                        <a href="accepted_invitations.php?history_page=<?php echo $historyPage - 1; ?>#history" aria-label="<?php echo t("previous_page"); ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php for ($pageNumber = 1; $pageNumber <= $historyPages; $pageNumber++): ?>
                        <a
                            href="accepted_invitations.php?history_page=<?php echo $pageNumber; ?>#history"
                            class="<?php echo $pageNumber === $historyPage ? "is-active" : ""; ?>"
                            <?php echo $pageNumber === $historyPage ? 'aria-current="page"' : ""; ?>
                        ><?php echo $pageNumber; ?></a>
                    <?php endfor; ?>
                    <?php if ($historyPage < $historyPages): ?>
                        <a href="accepted_invitations.php?history_page=<?php echo $historyPage + 1; ?>#history" aria-label="<?php echo t("next_page"); ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
                <p class="proposal-history-more">
                    <?php echo str_replace(["{from}", "{to}", "{total}"], [($historyPage - 1) * $historyPerPage + 1, min($historyTotal, $historyPage * $historyPerPage), $historyTotal], t("proposals_history_range")); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

</main>
</section>

<!-- CHATBOT -->
<?php include "chatbot.php"; ?>

<script src="js/chatbot.js?v=<?php echo ASSET_VERSION; ?>"></script>
</body>
</html>
