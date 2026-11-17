<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
require_once "db.php";
require_once "lang.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
$userId = (int) $_SESSION["user_id"];

function safe($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function invitationStatusLabel($status)
{
    if ($status === "accepted") {
        return t("invitation_status_accepted");
    }
    if ($status === "rejected") {
        return t("invitation_status_rejected");
    }
    return t("invitation_status_pending");
}

// =====================================================
// LOAD PENDING INVITATIONS
// =====================================================
$sql = "
    SELECT
        c.id AS community_id,
        c.name AS community_name,
        c.created_at,
        c.created_by,
        c.status AS community_status,
        cm.invitation_status,
        u.name AS creator_name,
        u.surname AS creator_surname
    FROM community_members cm
    INNER JOIN communities c
        ON c.id = cm.community_id
    INNER JOIN users u
        ON u.id = c.created_by
    WHERE cm.user_id = ?
      AND cm.invitation_status = 'pending'
      AND c.status = 'pending'
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$invitations = [];
while ($row = $result->fetch_assoc()) {
    $invitations[] = $row;
}
$stmt->close();

// =====================================================
// LOAD FULL DETAILS FOR EACH INVITED COMMUNITY
// =====================================================
$communityDetails = [];
$memberSql = "
    SELECT
        cm.user_id,
        cm.invitation_status,
        u.name,
        u.surname,
        ep.location,
        COALESCE(ep.minimum_share, 0) AS minimum_share,
        COALESCE(SUM(uc.estimated_value), 0) AS contribution_value
    FROM community_members cm
    INNER JOIN users u
        ON u.id = cm.user_id
    LEFT JOIN energy_profiles ep
        ON ep.user_id = cm.user_id
    LEFT JOIN user_contributions uc
        ON uc.user_id = cm.user_id
    WHERE cm.community_id = ?
    GROUP BY
        cm.user_id,
        cm.invitation_status,
        u.name,
        u.surname,
        ep.location,
        ep.minimum_share
    ORDER BY cm.id ASC
";
$memberStmt = $conn->prepare($memberSql);
if (!$memberStmt) {
    die("Database error: " . $conn->error);
}
foreach ($invitations as $invitation) {
    $communityId = (int) $invitation["community_id"];
    $memberStmt->bind_param("i", $communityId);
    $memberStmt->execute();
    $memberResult = $memberStmt->get_result();
    $members = [];
    $totalGroupValue = 0.0;
    $acceptedCount = 0;
    $pendingCount = 0;
    while ($member = $memberResult->fetch_assoc()) {
        $member["user_id"] = (int) $member["user_id"];
        $member["minimum_share"] = (float) $member["minimum_share"];
        $member["contribution_value"] = (float) $member["contribution_value"];
        $totalGroupValue += $member["contribution_value"];
        if ($member["invitation_status"] === "accepted") {
            $acceptedCount++;
        } elseif ($member["invitation_status"] === "pending") {
            $pendingCount++;
        }
        $members[] = $member;
    }
    foreach ($members as &$member) {
        $fairShare = $totalGroupValue > 0
            ? ($member["contribution_value"] / $totalGroupValue) * 100
            : 0;
        $member["fair_share"] = $fairShare;
        $member["fairness_margin"] = $fairShare - $member["minimum_share"];
    }
    unset($member);
    $communityDetails[$communityId] = [
        "members" => $members,
        "total_group_value" => $totalGroupValue,
        "accepted_count" => $acceptedCount,
        "pending_count" => $pendingCount
    ];
}
$memberStmt->close();

// =====================================================
// LOAD NOTIFICATIONS, GROUPED IN CATEGORIES
// =====================================================
// Categories (tabs at the top of the page):
//   invitations  pending invitations (the cards with Accept / Decline)
//   community    accepted, declined, activated, cancelled, member left
//   chat         new messages in the community chat
//   support      report decisions, answers to contact messages
// =====================================================
$notificationCategories = [
    "community" => ["invitation_accepted", "invitation_rejected", "member_left", "community_activated", "community_cancelled_conflict", "member_removed", "member_account_deleted"],
    "chat" => ["chat_message"],
    "support" => ["report_reviewed", "report_dismissed", "contact_answered", "account_warning"],
];
$allNotificationTypes = array_merge(...array_values($notificationCategories));

$currentCategory = (string) ($_GET["cat"] ?? "all");
if (!in_array($currentCategory, ["all", "invitations", "community", "chat", "support"], true)) {
    $currentCategory = "all";
}

$typesToShow = $currentCategory === "all"
    ? $allNotificationTypes
    : ($notificationCategories[$currentCategory] ?? []);

/* Unread per category (badges on the tabs) */
$unreadByCategory = ["community" => 0, "chat" => 0, "support" => 0];
$unreadStmt = $conn->prepare(
    "SELECT notification_type, COUNT(*) AS total
     FROM community_notifications
     WHERE recipient_user_id = ? AND is_read = 0
     GROUP BY notification_type"
);
if ($unreadStmt) {
    $unreadStmt->bind_param("i", $userId);
    $unreadStmt->execute();
    $unreadResult = $unreadStmt->get_result();
    while ($unreadRow = $unreadResult->fetch_assoc()) {
        foreach ($notificationCategories as $categoryKey => $categoryTypes) {
            if (in_array($unreadRow["notification_type"], $categoryTypes, true)) {
                $unreadByCategory[$categoryKey] += (int) $unreadRow["total"];
            }
        }
    }
    $unreadStmt->close();
}

/* Warnings exist only after migration_moderation.sql */
$hasReportColumn = false;
$reportColumnCheck = $conn->query("SHOW COLUMNS FROM community_notifications LIKE 'report_id'");
if ($reportColumnCheck) {
    $hasReportColumn = $reportColumnCheck->num_rows > 0;
    $reportColumnCheck->free();
}

/* My approved reports (warnings x/5), see moderation.php */
require_once __DIR__ . "/moderation.php";
$myWarnings = 0;
try {
    $myWarnings = emApprovedReportCount($conn, $userId);
} catch (Throwable $error) {
    $myWarnings = 0;
}

/* Contact answers exist only after migration_notifications.sql */
$hasContactColumn = false;
$columnCheck = $conn->query("SHOW COLUMNS FROM community_notifications LIKE 'contact_message_id'");
if ($columnCheck) {
    $hasContactColumn = $columnCheck->num_rows > 0;
    $columnCheck->free();
}

$activityNotifications = [];
$activityPerPage = 8;
$activityPage = max(1, (int) ($_GET["page"] ?? 1));
$activityTotal = 0;
$activityTotalPages = 1;

if ($typesToShow) {
    $typeList = "'" . implode("','", $typesToShow) . "'";

    $countStmt = $conn->prepare(
        "SELECT COUNT(*) FROM community_notifications
         WHERE recipient_user_id = ? AND notification_type IN ($typeList)"
    );
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $activityTotal = (int) $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $activityTotalPages = max(1, (int) ceil($activityTotal / $activityPerPage));
    $activityPage = min($activityPage, $activityTotalPages);
    $activityOffset = ($activityPage - 1) * $activityPerPage;

    $contactSelect = $hasContactColumn
        ? "cmsg.subject AS contact_subject, cmsg.admin_reply AS contact_reply"
        : "NULL AS contact_subject, NULL AS contact_reply";
    $contactJoin = $hasContactColumn
        ? "LEFT JOIN contact_messages cmsg ON cmsg.id = cn.contact_message_id"
        : "";
    /* warning number = the order in which the warnings were given (1/5, 2/5, ...) */
    $reportSelect = $hasReportColumn
        ? "rep.reason AS report_reason,
           (SELECT COUNT(*) FROM community_notifications earlier
            WHERE earlier.recipient_user_id = cn.recipient_user_id
              AND earlier.notification_type = 'account_warning'
              AND earlier.id <= cn.id) AS warning_number"
        : "NULL AS report_reason, NULL AS warning_number";
    $reportJoin = $hasReportColumn ? "LEFT JOIN user_reports rep ON rep.id = cn.report_id" : "";

    $activityStmt = $conn->prepare(
        "SELECT
             cn.id,
             cn.community_id,
             cn.notification_type,
             cn.is_read,
             cn.created_at,
             c.name AS community_name,
             c.status AS community_status,
             (SELECT COUNT(*) FROM community_members acc
              WHERE acc.community_id = cn.community_id
                AND acc.invitation_status = 'accepted') AS accepted_now,
             u.name AS actor_name,
             u.surname AS actor_surname,
             $contactSelect,
             $reportSelect
         FROM community_notifications cn
         LEFT JOIN communities c ON c.id = cn.community_id
         LEFT JOIN users u ON u.id = cn.actor_user_id
         $contactJoin
         $reportJoin
         WHERE cn.recipient_user_id = ?
           AND cn.notification_type IN ($typeList)
         ORDER BY cn.created_at DESC, cn.id DESC
         LIMIT ? OFFSET ?"
    );
    $activityStmt->bind_param("iii", $userId, $activityPerPage, $activityOffset);
    $activityStmt->execute();
    $activityResult = $activityStmt->get_result();
    while ($activityRow = $activityResult->fetch_assoc()) {
        $activityNotifications[] = $activityRow;
    }
    $activityStmt->close();

    /* The notifications shown on this page are now read */
    $shownUnread = array_map(
        function ($row) { return (int) $row["id"]; },
        array_filter($activityNotifications, function ($row) { return (int) $row["is_read"] === 0; })
    );
    /* tab badges: the notifications on this page are no longer unread */
    foreach ($activityNotifications as $shownRow) {
        if ((int) $shownRow["is_read"] !== 0) {
            continue;
        }
        foreach ($notificationCategories as $categoryKey => $categoryTypes) {
            if (in_array($shownRow["notification_type"], $categoryTypes, true)) {
                $unreadByCategory[$categoryKey] = max(0, $unreadByCategory[$categoryKey] - 1);
            }
        }
    }
    if ($shownUnread) {
        $conn->query(
            "UPDATE community_notifications SET is_read = 1
             WHERE recipient_user_id = " . (int) $userId . "
               AND id IN (" . implode(",", $shownUnread) . ")"
        );
    }
}

/* Text with {actor}, {community}, {count}, {subject} filled in (escaped, bold) */
function notificationText(string $key, array $values): string
{
    $text = t($key);
    foreach ($values as $name => $value) {
        $text = str_replace("{" . $name . "}", "<strong>" . safe($value) . "</strong>", $text);
    }
    return $text;
}

/* Icon, colour, message and link of one notification */
function notificationView(array $row): array
{
    $actor = trim(($row["actor_name"] ?? "") . " " . ($row["actor_surname"] ?? ""));
    $community = (string) ($row["community_name"] ?? "");
    $communityId = (int) ($row["community_id"] ?? 0);
    $values = ["actor" => $actor, "community" => $community];
    $view = ["icon" => "bell", "tone" => "", "text" => "", "extra" => "", "link" => null];

    switch ($row["notification_type"]) {
        case "invitation_accepted":
            $view["icon"] = "user-check";
            $view["text"] = notificationText("notif_accepted", $values);
            if (($row["community_status"] ?? "") === "pending") {
                $view["text"] .= " " . notificationText("notif_accepted_count", ["count" => (int) $row["accepted_now"]]);
                $view["link"] = ["accepted_invitations.php", t("notif_link_accepted")];
            } elseif (($row["community_status"] ?? "") === "active") {
                $view["link"] = ["community.php?id=" . $communityId, t("notif_link_community")];
            }
            break;
        case "invitation_rejected":
            $view["icon"] = "user-x";
            $view["tone"] = "rejected";
            $view["text"] = notificationText("notif_rejected", $values);
            $view["link"] = ["matches.php?view=community", t("notif_link_matches")];
            break;
        case "community_activated":
            $view["icon"] = "party-popper";
            $view["tone"] = "success";
            $view["text"] = notificationText("notif_activated", $values);
            $view["link"] = ["community.php?id=" . $communityId, t("notif_link_community")];
            break;
        case "community_cancelled_conflict":
            $view["icon"] = "circle-slash";
            $view["tone"] = "rejected";
            $view["text"] = notificationText("notif_conflict", $values);
            $view["link"] = ["matches.php?view=community", t("notif_link_matches")];
            break;
        case "member_left":
            $view["icon"] = "log-out";
            $view["tone"] = "left";
            $view["text"] = notificationText("notif_left", $values);
            $view["link"] = ["matches.php?view=community", t("notif_link_matches")];
            break;
        case "chat_message":
            $view["icon"] = "message-circle";
            $view["text"] = notificationText("notif_chat", $values);
            $view["link"] = ["community.php?id=" . $communityId . "#community-chat", t("notif_link_chat")];
            break;
        case "report_reviewed":
            $view["icon"] = "shield-check";
            $view["text"] = notificationText("notif_report_reviewed", $values);
            break;
        case "report_dismissed":
            $view["icon"] = "shield-x";
            $view["tone"] = "rejected";
            $view["text"] = notificationText("notif_report_dismissed", $values);
            break;
        case "account_warning":
            $view["icon"] = "triangle-alert";
            $view["tone"] = "rejected";
            $reasonKey = "reason_" . (string) ($row["report_reason"] ?? "other");
            $reasonText = t($reasonKey) !== $reasonKey ? t($reasonKey) : t("reason_other");
            $view["text"] = notificationText("notif_account_warning", [
                "reason" => $reasonText,
                "count" => max(1, (int) ($row["warning_number"] ?? $GLOBALS["myWarnings"])) . "/" . EM_WARNINGS_LIMIT,
            ]);
            $view["link"] = ["conduct.php", t("notif_link_conduct")];
            break;
        case "member_removed":
            $view["icon"] = "user-x";
            $view["tone"] = "rejected";
            $view["text"] = notificationText("notif_member_removed", $values);
            $view["link"] = ["matches.php?view=community", t("notif_link_matches")];
            break;
        case "member_account_deleted":
            $view["icon"] = "user-x";
            $view["tone"] = "left";
            $view["text"] = notificationText("notif_member_account_deleted", $values);
            $view["link"] = ["matches.php?view=community", t("notif_link_matches")];
            break;
        case "contact_answered":
            $view["icon"] = "mail-check";
            $view["tone"] = "success";
            $view["text"] = notificationText("notif_contact_answered", ["subject" => (string) ($row["contact_subject"] ?? "")]);
            if (trim((string) ($row["contact_reply"] ?? "")) !== "") {
                $view["extra"] = nl2br(safe($row["contact_reply"]));
            }
            break;
    }

    return $view;
}
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EnergyMatch - Notifications</title>
    <link rel="stylesheet" href="css/notifications.css?v=<?php echo ASSET_VERSION; ?>">
</head>
<body>

<section class="hero-section">
<?php include "navbar.php"; ?>
<div class="notifications-wrapper">
    <h1 class="page-title">
        <i data-lucide="bell"></i> <?php echo t("notifications_title"); ?>
    </h1>
    <p class="page-description">
        <?php
        echo t("notifications_description");
        ?>
    </p>

    <?php if (isset($_GET["success"])): ?>
        <div class="message">
            <?php
            if ($_GET["success"] === "accepted") {
                echo t("invitation_accepted_message");
            }
            if ($_GET["success"] === "rejected") {
                echo t("invitation_rejected_message");
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (($_GET["warning"] ?? "") === "active_community"): ?>
        <?php
        $warningActiveCommunityId =
            (int) ($_GET["active_community_id"] ?? 0);
        ?>
        <div class="warning-message">
            <strong>
                <i data-lucide="triangle-alert"></i> <?php
                echo t("cannot_accept_invitation_yet");
                ?>
            </strong>
            <br>
            <?php
            echo t("already_active_community_warning");
            ?>

            <?php if ($warningActiveCommunityId > 0): ?>
                <br>
                <a href="community.php?id=<?php echo $warningActiveCommunityId; ?>">
                    <?php
                    echo t("view_current_community");
                    ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- CATEGORIES -->
    <?php
    $categoryTabs = [
        "all" => ["icon" => "bell", "label" => t("notif_cat_all"), "badge" => count($invitations) + array_sum($unreadByCategory)],
        "invitations" => ["icon" => "mail", "label" => t("notif_cat_invitations"), "badge" => count($invitations)],
        "community" => ["icon" => "users-round", "label" => t("notif_cat_community"), "badge" => $unreadByCategory["community"]],
        "chat" => ["icon" => "message-circle", "label" => t("notif_cat_chat"), "badge" => $unreadByCategory["chat"]],
        "support" => ["icon" => "life-buoy", "label" => t("notif_cat_support"), "badge" => $unreadByCategory["support"]],
    ];
    ?>
    <nav class="notif-tabs" aria-label="<?php echo t("notif_categories"); ?>">
        <?php foreach ($categoryTabs as $tabKey => $tab): ?>
            <a
                href="notifications.php?cat=<?php echo $tabKey; ?>"
                class="notif-tab <?php echo $currentCategory === $tabKey ? "is-active" : ""; ?>"
                <?php echo $currentCategory === $tabKey ? 'aria-current="page"' : ""; ?>
            >
                <i data-lucide="<?php echo $tab["icon"]; ?>"></i>
                <?php echo $tab["label"]; ?>
                <?php if ($tab["badge"] > 0): ?>
                    <span class="notif-tab-badge"><?php echo (int) $tab["badge"]; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (in_array($currentCategory, ["all", "invitations"], true) && count($invitations) > 0): ?>
        <h2 class="invitations-section-title">
            <?php echo t("community_invitations"); ?>
        </h2>

        <?php foreach ($invitations as $invitation): ?>
            <?php
            $communityId = (int) $invitation["community_id"];
            $details = $communityDetails[$communityId] ?? [
                "members" => [],
                "total_group_value" => 0,
                "accepted_count" => 0,
                "pending_count" => 0
            ];
            $currentUserDetails = null;
            foreach ($details["members"] as $detailMember) {
                if ((int) $detailMember["user_id"] === $userId) {
                    $currentUserDetails = $detailMember;
                    break;
                }
            }
            ?>
            <div class="notification-card">
                <div class="notification-main">
                    <div class="notification-header"><div class="notification-icon"><i data-lucide="mail"></i></div>
                        <div>
                            <h3 class="notification-title">
                                <?php echo t("new_community_invitation"); ?>
                            </h3>
                        </div>
                    </div>
                    <div class="community-name">
                        <?php echo safe($invitation["community_name"]); ?>
                    </div>
                    <div class="notification-info">
                        <strong><?php echo safe($invitation["creator_name"] . " " . $invitation["creator_surname"]); ?></strong> <?php echo t("invited_to_community_suffix"); ?>
                    </div>
                    <div class="community-summary">
                        <div class="summary-box">
                            <span><?php echo t("members_label"); ?></span>
                            <strong><?php echo count($details["members"]); ?> / 5</strong>
                        </div>
                        <div class="summary-box">
                            <span><?php echo t("total_group_value"); ?></span>
                            <strong>€<?php echo number_format((float) $details["total_group_value"], 2); ?></strong>
                        </div>
                        <div class="summary-box">
                            <span><?php echo t("accepted_label"); ?></span>
                            <strong><?php echo (int) $details["accepted_count"]; ?> / 5</strong>
                        </div>
                        <div class="summary-box">
                            <span><?php echo t("your_fair_share"); ?></span>
                            <strong>
                                <?php
                                echo $currentUserDetails !== null
                                    ? number_format((float) $currentUserDetails["fair_share"], 2) . "%"
                                    : "-";
                                ?>
                            </strong>
                        </div>
                    </div>
                </div>
                <details class="community-details" open>
                    <summary>
                        <?php echo t("view_community_details"); ?>
                    </summary>
                    <div class="details-content">
                        <p class="details-intro">
                            <?php
                            echo t("community_details_intro");
                            ?>
                        </p>
                        <div class="members-table-wrap">
                            <table class="members-table">
                                <thead>
                                    <tr>
                                        <th><?php echo t("member_label"); ?></th>
                                        <th><?php echo t("contribution_label"); ?></th>
                                        <th><?php echo t("summary_minimum_share"); ?></th>
                                        <th>Fair Share</th>
                                        <th><?php echo t("margin_label"); ?></th>
                                        <th><?php echo t("status_label"); ?></th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php foreach ($details["members"] as $member): ?>
                                        <?php
                                        $isCurrentUser = (int) $member["user_id"] === $userId;
                                        $isCreator = (int) $member["user_id"] === (int) $invitation["created_by"];
                                        $statusClass = "status-" . safe($member["invitation_status"]);
                                        $marginClass = (float) $member["fairness_margin"] >= 0
                                            ? "positive-margin"
                                            : "negative-margin";
                                        ?>
                                        <tr class="<?php echo $isCurrentUser ? "current-user-row" : ""; ?>">
                                            <td>
                                                <span class="member-name">
                                                    <?php echo safe($member["name"] . " " . $member["surname"]); ?>
                                                </span>

                                                <?php if ($isCurrentUser): ?>
                                                    <span class="badge you-badge">
                                                        <?php echo t("you_label"); ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ($isCreator): ?>
                                                    <span class="badge creator-badge">
                                                        <?php echo t("creator_label"); ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (trim((string) $member["location"]) !== ""): ?>
                                                    <span class="small-location">
                                                        <i data-lucide="map-pin"></i> <?php echo safe($member["location"]); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                €<?php echo number_format((float) $member["contribution_value"], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo number_format((float) $member["minimum_share"], 2); ?>%
                                            </td>
                                            <td>
                                                <strong><?php echo number_format((float) $member["fair_share"], 2); ?>%</strong>
                                            </td>
                                            <td>
                                                <span class="<?php echo $marginClass; ?>">
                                                    <?php echo (float) $member["fairness_margin"] >= 0 ? "+" : ""; ?><?php echo number_format((float) $member["fairness_margin"], 2); ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo safe(invitationStatusLabel($member["invitation_status"])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="decision-note">
                            <i data-lucide="lightbulb"></i>
                            <?php
                            echo t("fair_share_decision_note");
                            ?>
                        </div>
                    </div>
                </details>
                <div class="invitation-actions">

                    <form action="respond_invitation.php" method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="community_id" value="<?php echo $communityId; ?>">
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="accept-btn">
                            <i data-lucide="check"></i> <?php echo t("accept_invitation"); ?>
                        </button>
                    </form>

                    <form
                        action="respond_invitation.php"
                        method="POST"
                        onsubmit="return confirm('<?php echo t("reject_invitation_confirm"); ?>');"
                    >
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="community_id" value="<?php echo $communityId; ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="reject-btn">
                            <i data-lucide="x"></i> <?php echo t("reject_invitation"); ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($currentCategory !== "invitations" && count($activityNotifications) > 0): ?>
        <section class="activity-section" id="community-updates">
            <?php foreach ($activityNotifications as $activity): ?>
                <?php
                $view = notificationView($activity);
                $activityWasUnread = (int) $activity["is_read"] === 0;
                ?>
                <div class="activity-card <?php echo $activityWasUnread ? "unread " : ""; ?><?php echo $view["tone"]; ?>">
                    <div class="activity-icon">
                        <i data-lucide="<?php echo $view["icon"]; ?>"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-message">
                            <?php echo $view["text"]; ?>
                            <?php if ($activityWasUnread): ?>
                                <span class="activity-new-badge"><?php echo t("new_badge"); ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if ($view["extra"] !== ""): ?>
                            <div class="activity-reply">
                                <span><?php echo t("notif_admin_reply"); ?></span>
                                <p><?php echo $view["extra"]; ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="activity-meta">
                            <?php echo safe(date("d/m/Y H:i", strtotime($activity["created_at"]))); ?>
                            <?php if ($view["link"] !== null): ?>
                                <a class="activity-link" href="<?php echo safe($view["link"][0]); ?>">
                                    <?php echo safe($view["link"][1]); ?>
                                    <i data-lucide="arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($activityTotalPages > 1): ?>
                <nav class="activity-pagination" aria-label="<?php echo t("community_updates_pages"); ?>">
                    <?php for ($pageNumber = 1; $pageNumber <= $activityTotalPages; $pageNumber++): ?>
                        <a
                            class="activity-page-link <?php echo $pageNumber === $activityPage ? "active" : ""; ?>"
                            href="notifications.php?cat=<?php echo $currentCategory; ?>&page=<?php echo $pageNumber; ?>#community-updates"
                            <?php echo $pageNumber === $activityPage ? 'aria-current="page"' : ''; ?>
                        >
                            <?php echo $pageNumber; ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php
    $categoryIsEmpty = $currentCategory === "invitations"
        ? count($invitations) === 0
        : (count($activityNotifications) === 0 && ($currentCategory !== "all" || count($invitations) === 0));
    ?>
    <?php if ($categoryIsEmpty): ?>
        <div class="empty-state">
            <div class="empty-icon"><i data-lucide="bell"></i></div>
            <h2>
                <?php echo $currentCategory === "all" ? t("no_new_notifications") : t("notif_empty_category"); ?>
            </h2>
            <p>
                <?php echo t("notifications_empty_text"); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
</section>
</body>
</html>
