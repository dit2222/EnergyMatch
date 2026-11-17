<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/admin_i18n.php";
require_once __DIR__ . "/moderation.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();
require_once "db.php";

/* =========================================
   ADMIN ACCESS
========================================= */

/* =========================================
   ADMIN ACCESS
========================================= */

/* Δεν είναι συνδεδεμένος */
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}

/* Είναι συνδεδεμένος αλλά δεν είναι Admin */
if (($_SESSION["role"] ?? "user") !== "admin") {
    header("Location: dashboard.php");
    exit();
}

/* =========================================
   SAFE OUTPUT
========================================= */

function adminSafe($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================
   UPDATE REPORT STATUS + NOTIFICATION
========================================= */
$allowedReportStatuses = [
    "pending",
    "reviewed",
    "dismissed"
];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reportId = (int)($_POST["report_id"] ?? 0);
    $newStatus = trim($_POST["new_status"] ?? "");
    if (
        $reportId > 0 &&
        in_array($newStatus, $allowedReportStatuses, true)
    ) {
        try {
            /* -----------------------------------------
               1. Παίρνουμε τα στοιχεία της αναφοράς
            ----------------------------------------- */
            $stmt = $conn->prepare("
                SELECT
                    id,
                    community_id,
                    reporter_user_id,
                    reported_user_id,
                    status
                FROM user_reports
                WHERE id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $result = $stmt->get_result();
            $reportData = $result->fetch_assoc();
            $stmt->close();
            if ($reportData) {
                $oldStatus = $reportData["status"];

                /* -----------------------------------------
                   2. Αλλάζουμε το status
                ----------------------------------------- */
                $stmt = $conn->prepare("
                    UPDATE user_reports
                    SET status = ?
                    WHERE id = ?
                ");
                if (!$stmt) {
                    throw new Exception($conn->error);
                }
                $stmt->bind_param(
                    "si",
                    $newStatus,
                    $reportId
                );
                $stmt->execute();
                $stmt->close();

                /* -----------------------------------------
                   3. Notification μόνο όταν υπάρχει
                      πραγματική νέα απόφαση Admin
                ----------------------------------------- */
                $notificationType = null;
                if (
                    $newStatus === "reviewed" &&
                    $oldStatus !== "reviewed"
                ) {
                    $notificationType = "report_reviewed";
                } elseif (
                    $newStatus === "dismissed" &&
                    $oldStatus !== "dismissed"
                ) {
                    $notificationType = "report_dismissed";
                }

                /* -----------------------------------------
                   4. Δημιουργία notification
                ----------------------------------------- */
                if ($notificationType !== null) {
                    $communityId =
                        (int)$reportData["community_id"];
                    $recipientUserId =
                        (int)$reportData["reporter_user_id"];
                    $adminUserId =
                        (int)$_SESSION["user_id"];
                    $stmt = $conn->prepare("
                        INSERT INTO community_notifications
                        (
                            community_id,
                            recipient_user_id,
                            actor_user_id,
                            notification_type,
                            is_read
                        )
                        VALUES (?, ?, ?, ?, 0)
                    ");
                    if (!$stmt) {
                        throw new Exception($conn->error);
                    }
                    $stmt->bind_param(
                        "iiis",
                        $communityId,
                        $recipientUserId,
                        $adminUserId,
                        $notificationType
                    );
                    $stmt->execute();
                    $stmt->close();
                }

                /* -----------------------------------------
                   5. Approved: warning to the reported user,
                      or account deleted at the 5th (moderation.php)
                ----------------------------------------- */
                if ($notificationType === "report_reviewed") {
                    $moderationResult = emOnReportApproved(
                        $conn,
                        $reportId,
                        (int) $_SESSION["user_id"]
                    );
                }
            }
        } catch (Throwable $e) {
            error_log(
                "Admin report update error: " .
                $e->getMessage()
            );
        }
    }
    header("Location: admin_reports.php?updated=1&result=" . urlencode($moderationResult ?? ""));
    exit();
}

/* =========================================
   FILTERS
========================================= */
$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");
$reports = [];
$pageError = "";

/* =========================================
   LOAD REPORTS
========================================= */
try {
    $sql = "
        SELECT
            r.id,
            r.community_id,
            r.reporter_user_id,
            r.reported_user_id,
            r.reason,
            r.description,
            r.status,
            r.created_at,
            reporter.name AS reporter_name,
            reporter.surname AS reporter_surname,
            reporter.username AS reporter_username,
            reported.name AS reported_name,
            reported.surname AS reported_surname,
            reported.username AS reported_username,
            c.name AS community_name
        FROM user_reports r
        LEFT JOIN users reporter
            ON reporter.id = r.reporter_user_id
        LEFT JOIN users reported
            ON reported.id = r.reported_user_id
        LEFT JOIN communities c
            ON c.id = r.community_id
        WHERE 1 = 1
    ";
    $types = "";
    $params = [];

    /* SEARCH */
    if ($search !== "") {
        $sql .= "
            AND (
                reporter.name LIKE ?
                OR reporter.surname LIKE ?
                OR reporter.username LIKE ?
                OR reported.name LIKE ?
                OR reported.surname LIKE ?
                OR reported.username LIKE ?
                OR r.reason LIKE ?
                OR r.description LIKE ?
                OR c.name LIKE ?
            )
        ";
        $searchValue = "%" . $search . "%";
        $types .= "sssssssss";
        for ($i = 0; $i < 9; $i++) {
            $params[] = $searchValue;
        }
    }

    /* STATUS */
    if ($status !== "") {
        $sql .= "
            AND r.status = ?
        ";
        $types .= "s";
        $params[] = $status;
    }
    $sql .= "
        ORDER BY
            r.created_at DESC,
            r.id DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param(
            $types,
            ...$params
        );
    }
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();
} catch (Throwable $e) {
    error_log(
        "Admin reports error: " .
        $e->getMessage()
    );
    $pageError =
        adminT("Could not load the reports.", "Δεν ήταν δυνατή η φόρτωση των αναφορών.");
}
?>
<!DOCTYPE html>
<html lang="<?php echo adminLangCode(); ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        <?php echo adminT("EnergyMatch | User reports", "EnergyMatch | Αναφορές χρηστών"); ?>
    </title>
    <link
        rel="stylesheet"
        href="css/admin_reports.css?v=<?php echo ASSET_VERSION; ?>"
    >
</head>
<body>

<!-- =========================================
     NAVBAR
========================================= -->

<?php include "admin_navbar.php"; ?>

<!-- =========================================
     MAIN
========================================= -->

<main class="container">

    <!-- HEADER -->
    <section class="page-header">
        <div>
            <p class="page-label">
                <?php echo adminT("ADMINISTRATION", "ΔΙΑΧΕΙΡΙΣΗ"); ?>
            </p>
            <h1>
                <?php echo adminT("User reports", "Αναφορές χρηστών"); ?>
            </h1>
            <p>
                <?php echo adminT("Follow and review the reports submitted by EnergyMatch members.", "Παρακολούθηση και εξέταση των αναφορών που υποβάλλονται από τα μέλη του EnergyMatch."); ?>
            </p>
        </div>
        <div class="report-total">
            <span>
                <?php echo adminT("Reports", "Αναφορές"); ?>
            </span>
            <strong>
                <?php echo count($reports); ?>
            </strong>
        </div>
    </section>

    <?php if ($pageError !== ""): ?>
        <div class="error-box">
            <?php echo adminSafe($pageError); ?>
        </div>
    <?php endif; ?>

    <?php if (($_GET["result"] ?? "") === "warned"): ?>
        <div class="moderation-message">
            <i data-lucide="triangle-alert"></i>
            <?php echo adminT("Report approved. The reported user received a warning.", "Η αναφορά εγκρίθηκε. Ο χρήστης που αναφέρθηκε έλαβε προειδοποίηση."); ?>
        </div>
    <?php elseif (($_GET["result"] ?? "") === "none"): ?>
        <div class="moderation-message">
            <i data-lucide="info"></i>
            <?php echo adminT("Report approved, but no new warning: this reporter already has an approved report about the same user (it counts once).", "Η αναφορά εγκρίθηκε, αλλά δεν δόθηκε νέα προειδοποίηση: ο ίδιος χρήστης έχει ήδη εγκεκριμένη αναφορά για το ίδιο μέλος (μετράει μία φορά)."); ?>
        </div>
    <?php elseif (($_GET["result"] ?? "") === "banned"): ?>
        <div class="moderation-message is-deleted">
            <i data-lucide="user-x"></i>
            <?php echo adminT("This was the 5th approved report: the account was blocked and its communities were cancelled (members informed). At the next visit the user sees only a farewell message and the account is deleted (automatically after 30 days if they never come back).", "Ήταν η 5η εγκεκριμένη αναφορά: ο λογαριασμός αποκλείστηκε και οι κοινότητές του ακυρώθηκαν (τα μέλη ενημερώθηκαν). Στην επόμενη είσοδο ο χρήστης βλέπει μόνο μήνυμα αποχαιρετισμού και ο λογαριασμός διαγράφεται (αυτόματα μετά από 30 μέρες, αν δεν ξαναμπεί)."); ?>
        </div>
    <?php elseif (($_GET["result"] ?? "") === "deleted"): ?>
        <div class="moderation-message is-deleted">
            <i data-lucide="user-x"></i>
            <?php echo adminT("This was the 5th approved report: the account was deleted, its communities were cancelled and their members informed.", "Ήταν η 5η εγκεκριμένη αναφορά: ο λογαριασμός διαγράφηκε, οι κοινότητές του ακυρώθηκαν και τα μέλη τους ενημερώθηκαν."); ?>
        </div>
    <?php endif; ?>

    <?php emPurgeBannedAccounts($conn); /* blocked > 30 days without the farewell visit */ ?>
    <?php $approvedCounts = emApprovedReportCounts($conn); ?>

    <!-- =====================================
         FILTERS
    ====================================== -->

    <section class="filter-panel">

        <form
            method="GET"
            action="admin_reports.php"
            class="filter-form"
        >
            <div class="filter-field search-field">
                <label for="search">
                    <?php echo adminT("Search", "Αναζήτηση"); ?>
                </label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?php echo adminSafe($search); ?>"
                    placeholder="<?php echo adminT("User, reason, description or community...", "Χρήστης, λόγος, περιγραφή ή κοινότητα..."); ?>"
                >
            </div>
            <div class="filter-field">
                <label for="status">
                    <?php echo adminT("Status", "Κατάσταση"); ?>
                </label>
                <select
                    id="status"
                    name="status"
                >
                    <option value="">
                        <?php echo adminT("All", "Όλες"); ?>
                    </option>
                    <option
                        value="pending"
                        <?php echo $status === "pending" ? "selected" : ""; ?>
                    >
                        <?php echo adminT("Pending", "Εκκρεμεί"); ?>
                    </option>
                    <option
                        value="reviewed"
                        <?php echo $status === "reviewed" ? "selected" : ""; ?>
                    >
                        <?php echo adminT("Reviewed", "Εξετάστηκε"); ?>
                    </option>
                    <option
                        value="dismissed"
                        <?php echo $status === "dismissed" ? "selected" : ""; ?>
                    >
                        <?php echo adminT("Dismissed", "Απορρίφθηκε"); ?>
                    </option>
                </select>
            </div>
            <button
                type="submit"
                class="filter-button"
            >
                <?php echo adminT("Apply", "Εφαρμογή"); ?>
            </button>

            <?php if ($search !== "" || $status !== ""): ?>
                <a
                    href="admin_reports.php"
                    class="clear-button"
                >
                    <?php echo adminT("Clear", "Καθαρισμός"); ?>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- =====================================
         REPORTS
    ====================================== -->

    <section class="reports-panel">
        <div class="panel-heading">
            <div>
                <h2>
                    <?php echo adminT("Reports", "Αναφορές"); ?>
                </h2>
                <p>
                    <?php echo adminT("All user reports of the platform.", "Όλες οι αναφορές χρηστών της πλατφόρμας."); ?>
                </p>
            </div>
        </div>

        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-lucide="flag"></i>
                </div>
                <h3>
                    <?php echo adminT("No reports found", "Δεν βρέθηκαν αναφορές"); ?>
                </h3>
                <p>
                    <?php echo adminT("No reports match the filters.", "Δεν υπάρχουν αναφορές που να αντιστοιχούν στα φίλτρα."); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="reports-list">

                <?php foreach ($reports as $report): ?>
                    <?php
                    $reporterName = trim(
                        ($report["reporter_name"] ?? "") .
                        " " .
                        ($report["reporter_surname"] ?? "")
                    );
                    if ($reporterName === "") {
                        $reporterName = adminT("Unknown user", "Άγνωστος χρήστης");
                    }
                    $reportedName = trim(
                        ($report["reported_name"] ?? "") .
                        " " .
                        ($report["reported_surname"] ?? "")
                    );
                    if ($reportedName === "") {
                        $reportedName = adminT("Unknown user", "Άγνωστος χρήστης");
                    }
                    $createdAt = "—";
                    if (!empty($report["created_at"])) {
                        $timestamp =
                            strtotime($report["created_at"]);
                        if ($timestamp !== false) {
                            $createdAt =
                                date(
                                    "d/m/Y H:i",
                                    $timestamp
                                );
                        }
                    }
                    $reportStatus =
                        strtolower(
                            trim($report["status"] ?? "")
                        );
                    $userWarnings = $approvedCounts[(int) $report["reported_user_id"]] ?? 0;
                    ?>
                    <article class="report-card">

                        <!-- TOP -->
                        <div class="report-top">
                            <div>
                                <span class="report-id">
                                    <?php echo adminT("Report #", "Αναφορά #"); ?><?php echo (int)$report["id"]; ?>
                                </span>
                                <h3>
                                    <?php
                                    echo adminSafe(
                                        adminReasonLabel($report["reason"] ?? "")
                                    );
                                    ?>
                                </h3>
                            </div>

                            <?php if ($reportStatus === "pending"): ?>
                                <span class="status-badge status-pending">
                                    <?php echo adminT("Pending", "Εκκρεμεί"); ?>
                                </span>
                            <?php elseif ($reportStatus === "reviewed"): ?>
                                <span class="status-badge status-reviewed">
                                    <?php echo adminT("Reviewed", "Εξετάστηκε"); ?>
                                </span>
                            <?php elseif ($reportStatus === "dismissed"): ?>
                                <span class="status-badge status-dismissed">
                                    <?php echo adminT("Dismissed", "Απορρίφθηκε"); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-other">
                                    <?php echo adminSafe(ucfirst($reportStatus)); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- USERS -->
                        <div class="report-users">
                            <div class="user-box">
                                <span class="box-label">
                                    <?php echo adminT("REPORTED BY", "ΑΝΑΦΟΡΑ ΑΠΟ"); ?>
                                </span>
                                <strong>
                                    <?php echo adminSafe($reporterName); ?>
                                </strong>

                                <?php if (!empty($report["reporter_username"])): ?>
                                    <small>
                                        @<?php
                                        echo adminSafe(
                                            $report["reporter_username"]
                                        );
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="report-arrow">
                                <i data-lucide="arrow-right"></i>
                            </div>
                            <div class="user-box reported-user">
                                <span class="box-label">
                                    <?php echo adminT("REPORTED USER", "ΑΝΑΦΕΡΟΜΕΝΟΣ ΧΡΗΣΤΗΣ"); ?>
                                </span>
                                <strong>
                                    <?php echo adminSafe($reportedName); ?>
                                </strong>

                                <?php if (!empty($report["reported_username"])): ?>
                                    <small>
                                        @<?php
                                        echo adminSafe(
                                            $report["reported_username"]
                                        );
                                        ?>
                                    </small>
                                <?php endif; ?>
                                <span class="warning-meter <?php echo $userWarnings >= EM_WARNINGS_LIMIT - 1 ? "is-high" : ""; ?>">
                                    <i data-lucide="triangle-alert"></i>
                                    <?php echo adminT("Approved reports", "Εγκεκριμένες αναφορές"); ?>:
                                    <?php echo $userWarnings; ?>/<?php echo EM_WARNINGS_LIMIT; ?>
                                </span>
                            </div>
                        </div>

                        <!-- DESCRIPTION -->
                        <div class="description-box">
                            <span class="box-label">
                                <?php echo adminT("DESCRIPTION", "ΠΕΡΙΓΡΑΦΗ"); ?>
                            </span>
                            <p>
                                <?php
                                echo nl2br(
                                    adminSafe(
                                        $report["description"]
                                        ?? adminT("No description.", "Δεν υπάρχει περιγραφή.")
                                    )
                                );
                                ?>
                            </p>
                        </div>

                        <!-- DETAILS -->
                        <div class="report-details">
                            <div>
                                <span>
                                    <?php echo adminT("Community", "Κοινότητα"); ?>
                                </span>
                                <strong>

                                    <?php if (!empty($report["community_name"])): ?>
                                        <?php
                                        echo adminSafe(
                                            $report["community_name"]
                                        );
                                        ?>
                                    <?php else: ?>
                                        #<?php
                                        echo (int)$report["community_id"];
                                        ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div>
                                <span>
                                    <?php echo adminT("Date", "Ημερομηνία"); ?>
                                </span>
                                <strong>
                                    <?php echo adminSafe($createdAt); ?>
                                </strong>
                            </div>
                            <div>
                                <span>
                                    <?php echo adminT("Reported user ID", "Κωδικός αναφερόμενου χρήστη"); ?>
                                </span>
                                <strong>
                                    #<?php echo (int)$report["reported_user_id"]; ?>
                                </strong>
                            </div>
                        </div>

                    <!-- REPORT ACTIONS -->
                        <div class="report-actions">

                            <?php if ($reportStatus === "pending"): ?>

                                <form method="POST" action="admin_reports.php">
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="report_id"
                                        value="<?php echo (int)$report["id"]; ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="reviewed"
                                    >
                                    <button
                                        type="submit"
                                        class="report-action-btn reviewed-btn"
                                        <?php if ($userWarnings >= EM_WARNINGS_LIMIT - 1): ?>
                                            onclick="return confirm('<?php echo adminT("This could be the 5th approved report: the account will be BLOCKED, removed from its community and deleted after a farewell message. Continue?", "Αυτή μπορεί να είναι η 5η εγκεκριμένη αναφορά: ο λογαριασμός θα ΑΠΟΚΛΕΙΣΤΕΙ, θα βγει από την κοινότητά του και θα διαγραφεί μετά από μήνυμα αποχαιρετισμού. Συνέχεια;"); ?>');"
                                        <?php endif; ?>
                                    >
                                        <i data-lucide="check"></i> <?php echo adminT("Approve report (warning)", "Έγκριση αναφοράς (προειδοποίηση)"); ?>
                                    </button>
                                </form>

                                <form method="POST" action="admin_reports.php">
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="report_id"
                                        value="<?php echo (int)$report["id"]; ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="dismissed"
                                    >
                                    <button
                                        type="submit"
                                        class="report-action-btn dismiss-btn"
                                    >
                                        <i data-lucide="x"></i> <?php echo adminT("Dismiss report", "Απόρριψη αναφοράς"); ?>
                                    </button>
                                </form>
                            <?php elseif ($reportStatus === "reviewed"): ?>

                                <form method="POST" action="admin_reports.php">
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="report_id"
                                        value="<?php echo (int)$report["id"]; ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="dismissed"
                                    >
                                    <button
                                        type="submit"
                                        class="report-action-btn dismiss-btn"
                                    >
                                        <i data-lucide="x"></i> <?php echo adminT("Dismiss report", "Απόρριψη αναφοράς"); ?>
                                    </button>
                                </form>

                                <form method="POST" action="admin_reports.php">
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="report_id"
                                        value="<?php echo (int)$report["id"]; ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="pending"
                                    >
                                    <button
                                        type="submit"
                                        class="report-action-btn pending-btn"
                                    >
                                        <i data-lucide="undo-2"></i> <?php echo adminT("Back to pending", "Επαναφορά σε εκκρεμότητα"); ?>
                                    </button>
                                </form>
                            <?php elseif ($reportStatus === "dismissed"): ?>

                                <form method="POST" action="admin_reports.php">
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="report_id"
                                        value="<?php echo (int)$report["id"]; ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="pending"
                                    >
                                    <button
                                        type="submit"
                                        class="report-action-btn pending-btn"
                                    >
                                        <i data-lucide="undo-2"></i> <?php echo adminT("Back to pending", "Επαναφορά σε εκκρεμότητα"); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="footer">
    <?php echo adminT("© EnergyMatch — Administration panel", "© EnergyMatch — Πίνακας διαχείρισης"); ?>
</footer>
</body>
</html>
