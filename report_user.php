<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once "db.php";
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
if (empty($_SESSION["report_user_csrf"])) {
    $_SESSION["report_user_csrf"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["report_user_csrf"];
$communityId = (int) ($_GET["community_id"] ?? $_POST["community_id"] ?? 0);
$reportedUserId = (int) ($_GET["user_id"] ?? $_POST["reported_user_id"] ?? 0);
$errorMessage = "";
$success = false;
$community = null;
$reportedUser = null;
$allowedReasons = [
    "inappropriate_behavior" => "reason_inappropriate_behavior",
    "harassment" => "reason_harassment",
    "misleading_information" => "reason_misleading_information",
    "collaboration_problem" => "reason_collaboration_problem",
    "other" => "reason_other"
];
if ($communityId <= 0 || $reportedUserId <= 0 || $reportedUserId === $currentUserId) {
    header("Location: community.php" . ($reportedUserId === $currentUserId ? "?report_access=denied" : ""));
    exit();
}
$accessStmt = $conn->prepare(
    "SELECT c.id, c.name, c.status, cm.invitation_status
     FROM communities c
     INNER JOIN community_members cm ON cm.community_id = c.id
     WHERE c.id = ? AND cm.user_id = ?
     LIMIT 1"
);
if (!$accessStmt) {
    die("Database error: " . $conn->error);
}
$accessStmt->bind_param("ii", $communityId, $currentUserId);
$accessStmt->execute();
$community = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();
if (
    !$community ||
    $community["status"] !== "active" ||
    $community["invitation_status"] !== "accepted"
) {
    header("Location: community.php?report_access=denied");
    exit();
}
$reportedStmt = $conn->prepare(
    "SELECT u.id, u.name, u.surname
     FROM community_members cm
     INNER JOIN users u ON u.id = cm.user_id
     WHERE cm.community_id = ?
       AND cm.user_id = ?
       AND cm.invitation_status = 'accepted'
     LIMIT 1"
);
if (!$reportedStmt) {
    die("Database error: " . $conn->error);
}
$reportedStmt->bind_param("ii", $communityId, $reportedUserId);
$reportedStmt->execute();
$reportedUser = $reportedStmt->get_result()->fetch_assoc();
$reportedStmt->close();
if (!$reportedUser) {
    header("Location: community.php?id=" . $communityId . "&report_access=denied");
    exit();
}
$reportedFullName = trim($reportedUser["name"] . " " . $reportedUser["surname"]);
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = (string) ($_POST["csrf_token"] ?? "");
    $reason = trim((string) ($_POST["reason"] ?? ""));
    $description = trim((string) ($_POST["description"] ?? ""));
    if (!hash_equals($csrfToken, $postedToken)) {
        $errorMessage = t("report_form_expired");
    } elseif (!array_key_exists($reason, $allowedReasons)) {
        $errorMessage = t("report_select_reason_error");
    } elseif (mb_strlen($description, "UTF-8") > 1000) {
        $errorMessage = t("report_description_too_long");
    } else {
        $duplicateStmt = $conn->prepare(
            "SELECT id
             FROM user_reports
             WHERE community_id = ?
               AND reporter_user_id = ?
               AND reported_user_id = ?
               AND status = 'pending'
             LIMIT 1"
        );
        if (!$duplicateStmt) {
            $errorMessage = t("report_check_error");
        } else {
            $duplicateStmt->bind_param("iii", $communityId, $currentUserId, $reportedUserId);
            $duplicateStmt->execute();
            $alreadyReported = (bool) $duplicateStmt->get_result()->fetch_assoc();
            $duplicateStmt->close();
            if ($alreadyReported) {
                $errorMessage = t("report_duplicate_error");
            } else {
                $insertStmt = $conn->prepare(
                    "INSERT INTO user_reports
                        (community_id, reporter_user_id, reported_user_id, reason, description)
                     VALUES (?, ?, ?, ?, ?)"
                );
                if (!$insertStmt) {
                    $errorMessage = t("report_save_error");
                } else {
                    $insertStmt->bind_param(
                        "iiiss",
                        $communityId,
                        $currentUserId,
                        $reportedUserId,
                        $reason,
                        $description
                    );
                    if ($insertStmt->execute()) {
                        $success = true;
                        $_SESSION["report_user_csrf"] = bin2hex(random_bytes(32));
                        $csrfToken = $_SESSION["report_user_csrf"];
                    } else {
                        $errorMessage = t("report_save_error");
                    }
                    $insertStmt->close();
                }
            }
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
    <title><?php echo safe(t("report_user_title")); ?> | EnergyMatch</title>
    <link rel="stylesheet" href="css/report_user.css?v=<?php echo ASSET_VERSION; ?>">
</head>
<body>
<div class="page-wrapper">

<?php if ($success): ?>
    <div class="report-card success-card">
        <div class="icon"><i data-lucide="check"></i></div>
        <h1><?php echo safe(t("report_submitted")); ?></h1>
        <p><?php echo safe(t("report_submitted_text")); ?></p>
        <div class="actions">
            <a href="community.php?community_id=<?php echo (int) $communityId; ?>" class="btn cancel-btn">
                <?php echo safe(t("back_to_community")); ?>
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="report-card">
        <div class="icon"><i data-lucide="flag"></i></div>
        <h1><?php echo safe(t("report_user_title")); ?></h1>
        <p class="intro"><?php echo safe(t("report_intro")); ?> <a href="conduct.php"><?php echo t("conduct_title"); ?></a></p>
        <div class="reported-user">
            <span><?php echo safe(t("reported_user")); ?></span>
            <strong><?php echo safe($reportedFullName); ?></strong>
        </div>
        <div class="community-name">
            <span><?php echo safe(t("community")); ?></span>
            <?php echo safe($community["name"]); ?>
        </div>
        <div class="notice"><?php echo safe(t("report_notice")); ?></div>

        <?php if ($errorMessage !== ""): ?>
            <div class="error"><?php echo safe($errorMessage); ?></div>
        <?php endif; ?>

        <form method="POST" action="report_user.php">
            <input type="hidden" name="community_id" value="<?php echo (int) $communityId; ?>">
            <input type="hidden" name="reported_user_id" value="<?php echo (int) $reportedUserId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo safe($csrfToken); ?>">
            <div class="field">
                <label for="reason"><?php echo safe(t("reason_for_report")); ?></label>
                <select id="reason" name="reason" required>
                    <option value=""><?php echo safe(t("select_reason")); ?></option>

                    <?php foreach ($allowedReasons as $value => $translationKey): ?>
                        <option value="<?php echo safe($value); ?>"
                            <?php echo (($_POST["reason"] ?? "") === $value) ? "selected" : ""; ?>>
                            <?php echo safe(t($translationKey)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="description"><?php echo safe(t("additional_information")); ?></label>
                <textarea
                    id="description"
                    name="description"
                    maxlength="1000"
                    placeholder="<?php echo safe(t("report_description_placeholder")); ?>"
                ><?php echo safe($_POST["description"] ?? ""); ?></textarea>
                <div class="help"><?php echo safe(t("report_description_help")); ?></div>
            </div>
            <div class="actions">
                <a href="community.php?community_id=<?php echo (int) $communityId; ?>" class="btn cancel-btn">
                    <i data-lucide="arrow-left"></i> <?php echo safe(t("cancel")); ?>
                </a>
                <button type="submit" class="btn report-btn">
                    <i data-lucide="flag"></i> <?php echo safe(t("submit_report")); ?>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>
</div>
</body>
</html>
