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
$currentUserId = (int) $_SESSION["user_id"];

function safe($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
if (empty($_SESSION["leave_community_csrf"])) {
    $_SESSION["leave_community_csrf"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["leave_community_csrf"];
$communityId = (int) ($_GET["community_id"] ?? $_POST["community_id"] ?? 0);
$errorMessage = "";
$success = false;
$community = null;

$allowedReasons = [
    "not_interested" => "leave_reason_not_interested",
    "group_composition" => "leave_reason_group_composition",
    "cannot_contribute" => "leave_reason_cannot_contribute",
    "personal_reason" => "leave_reason_personal",
    "other" => "leave_reason_other"
];
if ($communityId <= 0) {
    header("Location: community.php");
    exit();
}

// Load the requested community and verify that the current user is
// an accepted member of an active Energy Community.
$communityStmt = $conn->prepare(
    "
    SELECT
        c.id,
        c.name,
        c.status,
        c.created_by,
        cm.invitation_status
    FROM communities c
    INNER JOIN community_members cm
        ON cm.community_id = c.id
    WHERE c.id = ?
      AND cm.user_id = ?
    LIMIT 1
    "
);
if (!$communityStmt) {
    die("Database error: " . $conn->error);
}
$communityStmt->bind_param("ii", $communityId, $currentUserId);
$communityStmt->execute();
$communityResult = $communityStmt->get_result();
$community = $communityResult->fetch_assoc();
$communityStmt->close();
if (!$community) {
    header("Location: community.php?leave_access=denied");
    exit();
}
if ($community["status"] !== "active" || $community["invitation_status"] !== "accepted") {
    header("Location: community.php?leave_access=denied");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = (string) ($_POST["csrf_token"] ?? "");
    $reason = trim((string) ($_POST["reason"] ?? ""));
    $description = trim((string) ($_POST["description"] ?? ""));
    if (!hash_equals($csrfToken, $postedToken)) {
        $errorMessage = t("leave_form_expired");
    } elseif (!array_key_exists($reason, $allowedReasons)) {
        $errorMessage = t("leave_select_reason_error");
    } elseif (mb_strlen($description, "UTF-8") > 1000) {
        $errorMessage = t("leave_description_too_long");
    } else {
        try {
            $conn->begin_transaction();

            // Lock membership/community so the leave action cannot be submitted twice.
            $lockStmt = $conn->prepare(
                "
                SELECT c.status, cm.invitation_status
                FROM communities c
                INNER JOIN community_members cm
                    ON cm.community_id = c.id
                WHERE c.id = ?
                  AND cm.user_id = ?
                FOR UPDATE
                "
            );
            if (!$lockStmt) {
                throw new Exception($conn->error);
            }
            $lockStmt->bind_param("ii", $communityId, $currentUserId);
            $lockStmt->execute();
            $lockResult = $lockStmt->get_result();
            $locked = $lockResult->fetch_assoc();
            $lockStmt->close();
            if (!$locked || $locked["status"] !== "active" || $locked["invitation_status"] !== "accepted") {
                throw new Exception("Membership is no longer active.");
            }
            $insertStmt = $conn->prepare(
                "
                INSERT INTO community_leave_requests
                    (community_id, user_id, reason, description)
                VALUES (?, ?, ?, ?)
                "
            );
            if (!$insertStmt) {
                throw new Exception($conn->error);
            }
            $insertStmt->bind_param(
                "iiss",
                $communityId,
                $currentUserId,
                $reason,
                $description
            );
            if (!$insertStmt->execute()) {
                throw new Exception($insertStmt->error);
            }
            $insertStmt->close();
            $memberStmt = $conn->prepare(
                "
                UPDATE community_members
                SET invitation_status = 'left'
                WHERE community_id = ?
                  AND user_id = ?
                  AND invitation_status = 'accepted'
                "
            );
            if (!$memberStmt) {
                throw new Exception($conn->error);
            }
            $memberStmt->bind_param("ii", $communityId, $currentUserId);
            $memberStmt->execute();
            if ($memberStmt->affected_rows !== 1) {
                $memberStmt->close();
                throw new Exception("Membership update failed.");
            }
            $memberStmt->close();

            // Notify every other accepted member that this user left.
            // The actor is excluded, so in a normal 5-member active community
            // exactly the other 4 members receive the notification.
            $leaveNotificationType = "member_left";
            $notificationStmt = $conn->prepare(
                "
                INSERT INTO community_notifications (
                    community_id,
                    recipient_user_id,
                    actor_user_id,
                    notification_type,
                    is_read
                )
                SELECT
                    cm.community_id,
                    cm.user_id,
                    ?,
                    ?,
                    0
                FROM community_members cm
                WHERE cm.community_id = ?
                  AND cm.user_id <> ?
                  AND cm.invitation_status = 'accepted'
                "
            );
            if (!$notificationStmt) {
                throw new Exception($conn->error);
            }
            $notificationStmt->bind_param(
                "isii",
                $currentUserId,
                $leaveNotificationType,
                $communityId,
                $currentUserId
            );
            if (!$notificationStmt->execute()) {
                $notificationError = $notificationStmt->error;
                $notificationStmt->close();
                throw new Exception($notificationError);
            }
            $notificationStmt->close();

            // EnergyMatch communities are fixed 5-member groups.
            // Once one accepted member leaves, this exact community is cancelled.
            $statusStmt = $conn->prepare(
                "
                UPDATE communities
                SET status = 'cancelled'
                WHERE id = ?
                  AND status = 'active'
                "
            );
            if (!$statusStmt) {
                throw new Exception($conn->error);
            }
            $statusStmt->bind_param("i", $communityId);
            $statusStmt->execute();
            if ($statusStmt->affected_rows !== 1) {
                $statusStmt->close();
                throw new Exception("Community status update failed.");
            }
            $statusStmt->close();

            // Cancelling an active community may free one or more users who
            // were blocking another fully accepted pending community.
            // Re-check those communities before committing the leave action.
            activateEligiblePendingCommunities($conn);
            $conn->commit();
            $success = true;

            // Prevent reuse of the submitted token.
            $_SESSION["leave_community_csrf"] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $conn->rollback();
            $errorMessage = t("leave_failed");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EnergyMatch - <?php echo safe(t("leave_community_page_title")); ?></title>
    <link rel="stylesheet" href="css/leave_community.css?v=<?php echo ASSET_VERSION; ?>">
</head>
<body>
<div class="page-wrapper">

<?php if ($success): ?>
    <div class="leave-card success-card">
        <div class="icon"><i data-lucide="check"></i></div>
        <h1>
            <?php echo safe(t("leave_success_title")); ?>
        </h1>
        <p>
            <?php echo safe(t("leave_success_text")); ?>
        </p>
        <div class="actions">
            <a href="dashboard.php" class="btn cancel-btn">
                <?php echo safe(t("back_to_dashboard")); ?>
            </a>
            <a href="matches.php" class="btn cancel-btn">
                <?php echo safe(t("find_new_community")); ?>
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="leave-card">
        <div class="icon"><i data-lucide="log-out"></i></div>
        <h1>
            <?php echo safe(t("leave_community_page_title")); ?>
        </h1>
        <p class="intro">
            <?php echo safe(t("leave_intro")); ?>
        </p>
        <div class="community-name">
            <?php echo safe($community["name"]); ?>
        </div>
        <div class="warning">
            <strong><?php echo safe(t("important")); ?></strong>
            <?php echo " " . safe(t("leave_warning")); ?>
        </div>

        <?php if ($errorMessage !== ""): ?>
            <div class="error"><?php echo safe($errorMessage); ?></div>
        <?php endif; ?>

        <form method="POST" action="leave_community.php">
            <input type="hidden" name="community_id" value="<?php echo (int) $communityId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">
            <div class="field">
                <label for="reason">
                    <?php echo safe(t("leave_reason")); ?>
                </label>
                <select id="reason" name="reason" required>
                    <option value="">
                        <?php echo safe(t("leave_select_reason")); ?>
                    </option>

                    <?php foreach ($allowedReasons as $value => $translationKey): ?>
                        <option
                            value="<?php echo safe($value); ?>"
                            <?php echo (($_POST["reason"] ?? "") === $value) ? "selected" : ""; ?>
                        >
                            <?php echo safe(t($translationKey)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="description">
                    <?php echo safe(t("leave_additional_info")); ?>
                </label>
                <textarea
                    id="description"
                    name="description"
                    maxlength="1000"
                    placeholder="<?php echo safe(t("leave_description_placeholder")); ?>"
                ><?php echo safe($_POST["description"] ?? ""); ?></textarea>
                <div class="help">
                    <?php echo safe(t("leave_description_help")); ?>
                </div>
            </div>
            <div class="actions">
                <a
                    href="community.php?id=<?php echo (int) $communityId; ?>"
                    class="btn cancel-btn"
                >
                    <i data-lucide="arrow-left"></i> <?php echo safe(t("leave_cancel")); ?>
                </a>
                <button
                    type="submit"
                    class="btn leave-btn"
                    onclick="return confirm('<?php echo safe(t("leave_confirm")); ?>');"
                >
                    <i data-lucide="log-out"></i> <?php echo safe(t("leave_submit")); ?>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>
</div>
</body>
</html>
