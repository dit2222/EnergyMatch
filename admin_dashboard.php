<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/admin_i18n.php";
require_once "db.php";

// Μόνο οι διαχειριστές έχουν πρόσβαση.
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

// Ασφαλής εμφάνιση κειμένου.
function adminSafe($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Μετράμε εγγραφές σε πίνακες.
function adminCount($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error);
    }
    $row = $result->fetch_row();
    $result->free();
    return (int)($row[0] ?? 0);
}
$stats = [
    "users" => 0,
    "communities" => 0,
    "active" => 0,
    "cancelled" => 0,
    "rejected" => 0,
    "pending_invitations" => 0,
    "reports" => 0,
    "new_messages" => 0
];
$error = "";
try {
    // Συνολικοί χρήστες
    $stats["users"] = adminCount(
        $conn,
        "SELECT COUNT(*) FROM users"
    );

    // Συνολικές κοινότητες
    $stats["communities"] = adminCount(
        $conn,
        "SELECT COUNT(*) FROM communities"
    );

    // Ενεργές κοινότητες
    $stats["active"] = adminCount(
        $conn,
        "SELECT COUNT(*)
         FROM communities
         WHERE status = 'active'"
    );

    // Ακυρωμένες κοινότητες
    $stats["cancelled"] = adminCount(
        $conn,
        "SELECT COUNT(*)
         FROM communities
         WHERE status = 'cancelled'"
    );

    // Απορριφθείσες κοινότητες
    $stats["rejected"] = adminCount(
        $conn,
        "SELECT COUNT(*)
         FROM communities
         WHERE status = 'rejected'"
    );

    // Εκκρεμείς προσκλήσεις
    $stats["pending_invitations"] = adminCount(
        $conn,
        "SELECT COUNT(*)
         FROM community_members
         WHERE invitation_status = 'pending'"
    );

    // Αναφορές χρηστών
    $stats["reports"] = adminCount(
        $conn,
        "SELECT COUNT(*)
         FROM user_reports"
    );
    $stats["new_messages"] = adminCount(
        $conn,
        "SELECT COUNT(*)
        FROM contact_messages
        WHERE status = 'new'"
);
} catch (Throwable $e) {
    error_log(
        "Admin dashboard error: " .
        $e->getMessage()
    );
    $error =
        adminT("Could not load the statistics.", "Δεν ήταν δυνατή η φόρτωση των στατιστικών.");
}
$adminName = $_SESSION["name"] ?? "Admin";
?>
<!DOCTYPE html>
<html lang="<?php echo adminLangCode(); ?>">
<head>
    <?php include "head_assets.php"; ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo adminT("EnergyMatch | Admin dashboard", "EnergyMatch | Πίνακας διαχείρισης"); ?></title>
<link rel="stylesheet" href="css/admin_dashboard.css?v=<?php echo ASSET_VERSION; ?>">
</head>
<body>

<?php include "admin_navbar.php"; ?>

<main class="container">

    <section class="welcome">
        <h1>
            <?php echo adminT("Welcome,", "Καλώς ήρθες,"); ?>
            <?php echo adminSafe($adminName); ?>!
        </h1>
        <p>
            <?php echo adminT("Welcome to the EnergyMatch admin panel. From here you can follow the users, the energy communities, the reports and the overall operation of the platform.", "Καλώς ήρθες στον πίνακα διαχείρισης του EnergyMatch. Από εδώ μπορείς να παρακολουθείς τους χρήστες, τις ενεργειακές κοινότητες, τις αναφορές και τη συνολική λειτουργία της πλατφόρμας."); ?>
        </p>
    </section>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?php echo adminSafe($error); ?>
        </div>
    <?php endif; ?>

    <section class="stats-grid">

    <!-- USERS -->
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="users"></i>
        </div>
        <h3>
            <?php echo adminT("Registered users", "Εγγεγραμμένοι χρήστες"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["users"]; ?>
        </div>
    </div>

    <!-- ALL COMMUNITIES -->
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="house"></i>
        </div>
        <h3>
            <?php echo adminT("Total communities", "Συνολικές κοινότητες"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["communities"]; ?>
        </div>
    </div>

    <!-- ACTIVE -->
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="sprout"></i>
        </div>
        <h3>
            <?php echo adminT("Active communities", "Ενεργές κοινότητες"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["active"]; ?>
        </div>
    </div>

    <!-- CANCELLED -->
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="ban"></i>
        </div>
        <h3>
            <?php echo adminT("Cancelled communities", "Ακυρωμένες κοινότητες"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["cancelled"]; ?>
        </div>
    </div>

    <!-- REJECTED -->
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="circle-x"></i>
        </div>
        <h3>
            <?php echo adminT("Rejected communities", "Απορριφθείσες κοινότητες"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["rejected"]; ?>
        </div>
    </div>

    <!-- PENDING INVITATIONS -->
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="hourglass"></i>
        </div>
        <h3>
            <?php echo adminT("Pending invitations", "Εκκρεμείς προσκλήσεις"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["pending_invitations"]; ?>
        </div>
    </div>

    <!-- REPORTS -->
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="flag"></i>
        </div>
        <h3>
            <?php echo adminT("User reports", "Αναφορές χρηστών"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["reports"]; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon">
            <i data-lucide="mail"></i>
        </div>
        <h3>
            <?php echo adminT("New messages", "Νέα μηνύματα"); ?>
        </h3>
        <div class="number">
            <?php echo $stats["new_messages"]; ?>
        </div>
    </div>
</section>
    <h2 class="section-title" id="management">
        <?php echo adminT("Platform management", "Διαχείριση πλατφόρμας"); ?>
    </h2>

    <section class="management-grid">
        <div class="management-card">
            <h3><i data-lucide="users"></i> <?php echo adminT("User management", "Διαχείριση χρηστών"); ?></h3>
            <p>
                <?php echo adminT("View registered users and their account details, and manage their access.", "Προβολή εγγεγραμμένων χρηστών, στοιχείων λογαριασμών και διαχείριση της πρόσβασής τους."); ?>
            </p>
            <a href="admin_users.php" class="manage-button">
                <?php echo adminT("User management", "Διαχείριση χρηστών"); ?>
            </a>
        </div>
        <div class="management-card">
            <h3><i data-lucide="house"></i> <?php echo adminT("Community management", "Διαχείριση κοινοτήτων"); ?></h3>
            <p>
                <?php echo adminT("Oversee the energy communities, their five members and the status of each invitation.", "Εποπτεία ενεργειακών κοινοτήτων, των πέντε μελών τους και της κατάστασης συμμετοχής."); ?>
            </p>
            <a
                href="admin_communities.php"
                class="manage-button"
            >
                <?php echo adminT("Community management", "Διαχείριση κοινοτήτων"); ?>
            </a>
        </div>
        <div class="management-card">
            <h3><i data-lucide="flag"></i> <?php echo adminT("User reports", "Αναφορές χρηστών"); ?></h3>
            <p>
                <?php echo adminT("Review reports, check incidents and follow their status.", "Εξέταση αναφορών, έλεγχος περιστατικών και παρακολούθηση της κατάστασής τους."); ?>
            </p>
            <a
                href="admin_reports.php"
                class="manage-button"
            >
                <?php echo adminT("View reports", "Προβολή αναφορών"); ?>
            </a>
        </div>
        <div class="management-card">
            <h3><i data-lucide="mail"></i> <?php echo adminT("Contact messages", "Μηνύματα επικοινωνίας"); ?></h3>
            <p>
                <?php echo adminT("View contact messages and handle support requests.", "Προβολή μηνυμάτων επικοινωνίας και διαχείριση αιτημάτων υποστήριξης."); ?>
            </p>
            <a
                href="admin_messages.php"
                class="manage-button"
            >
                <?php echo adminT("View messages", "Προβολή μηνυμάτων"); ?>
            </a>
        </div>
    </section>
</main>

<footer class="footer">
    <?php echo adminT("© EnergyMatch — Administration panel", "© EnergyMatch — Πίνακας διαχείρισης"); ?>
</footer>
</body>
</html>
