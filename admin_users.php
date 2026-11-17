<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/admin_i18n.php";
require_once __DIR__ . "/moderation.php";
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
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================
   VARIABLES
========================================= */
$adminName = $_SESSION["name"] ?? "Admin";
$search = trim($_GET["search"] ?? "");
$users = [];
$pageError = "";

/* =========================================
   LOAD USERS
========================================= */
try {
    if ($search !== "") {
        $sql = "
            SELECT
                id,
                name,
                surname,
                email,
                username,
                region,
                city,
                role,
                created_at
            FROM users
            WHERE
                name LIKE ?
                OR surname LIKE ?
                OR username LIKE ?
                OR email LIKE ?
            ORDER BY created_at DESC, id DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $searchValue = "%" . $search . "%";
        $stmt->bind_param(
            "ssss",
            $searchValue,
            $searchValue,
            $searchValue,
            $searchValue
        );
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $result = $stmt->get_result();
    } else {
        $sql = "
            SELECT
                id,
                name,
                surname,
                email,
                username,
                region,
                city,
                role,
                created_at
            FROM users
            ORDER BY created_at DESC, id DESC
        ";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception($conn->error);
        }
    }
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    if (isset($stmt)) {
        $stmt->close();
    }
} catch (Throwable $error) {
    error_log(
        "Admin users error: " .
        $error->getMessage()
    );
    $pageError =
        adminT("Could not load the users.", "Δεν ήταν δυνατή η φόρτωση των χρηστών.");
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
        <?php echo adminT("EnergyMatch | User management", "EnergyMatch | Διαχείριση χρηστών"); ?>
    </title>
    <link
        rel="stylesheet"
        href="css/admin_users.css?v=<?php echo ASSET_VERSION; ?>"
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

    <section class="page-header">
        <div>
            <p class="page-label">
                <?php echo adminT("ADMINISTRATION", "ΔΙΑΧΕΙΡΙΣΗ"); ?>
            </p>
            <h1>
                <?php echo adminT("User management", "Διαχείριση χρηστών"); ?>
            </h1>
            <p>
                <?php echo adminT("View and oversee the registered users of EnergyMatch.", "Προβολή και εποπτεία των εγγεγραμμένων χρηστών του EnergyMatch."); ?>
            </p>
        </div>
        <div class="total-users">
            <span>
                <?php echo adminT("Total users", "Συνολικοί χρήστες"); ?>
            </span>
            <strong>
                <?php echo count($users); ?>
            </strong>
        </div>
    </section>

    <?php if ($pageError !== ""): ?>
        <div class="error-box">
            <?php echo adminSafe($pageError); ?>
        </div>
    <?php endif; ?>

    <!-- SEARCH -->
    <section class="search-panel">

        <form
            method="GET"
            action="admin_users.php"
            class="search-form"
        >
            <div class="search-field">
                <label for="search">
                    <?php echo adminT("Search user", "Αναζήτηση χρήστη"); ?>
                </label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?php echo adminSafe($search); ?>"
                    placeholder="<?php echo adminT("Name, surname, username or email...", "Όνομα, επώνυμο, όνομα χρήστη ή email..."); ?>"
                >
            </div>
            <button
                type="submit"
                class="search-button"
            >
                <?php echo adminT("Search", "Αναζήτηση"); ?>
            </button>

            <?php if ($search !== ""): ?>
                <a
                    href="admin_users.php"
                    class="clear-button"
                >
                    <?php echo adminT("Clear", "Καθαρισμός"); ?>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- USERS TABLE -->
    <section class="users-panel">
        <div class="panel-heading">
            <div>
                <h2>
                    <?php echo adminT("Registered users", "Εγγεγραμμένοι χρήστες"); ?>
                </h2>

                <?php if ($search !== ""): ?>
                    <p>
                        <?php echo adminT("Results for:", "Αποτελέσματα για:"); ?>
                        <strong>
                            "<?php echo adminSafe($search); ?>"
                        </strong>
                    </p>
                <?php else: ?>
                    <p>
                        <?php echo adminT("All accounts of the platform.", "Όλοι οι λογαριασμοί της πλατφόρμας."); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-lucide="user-round"></i>
                </div>
                <h3>
                    <?php echo adminT("No users found", "Δεν βρέθηκαν χρήστες"); ?>
                </h3>
                <p>
                    <?php echo adminT("No users match your search.", "Δεν υπάρχουν χρήστες που να αντιστοιχούν στην αναζήτησή σου."); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>
                                ID
                            </th>
                            <th>
                                <?php echo adminT("User", "Χρήστης"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Username", "Όνομα χρήστη"); ?>
                            </th>
                            <th>
                                Email
                            </th>
                            <th>
                                <?php echo adminT("Region", "Περιοχή"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Role", "Ρόλος"); ?>
                            </th>
                            <th title="<?php echo adminT("Approved reports. At 5 the account is deleted.", "Εγκεκριμένες αναφορές. Στις 5 ο λογαριασμός διαγράφεται."); ?>">
                                <?php echo adminT("Reports", "Αναφορές"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Registered", "Εγγραφή"); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php
                    $approvedCounts = emApprovedReportCounts($conn);
                    /* accounts blocked after the 5th report, waiting for the farewell visit */
                    $bannedIds = [];
                    try {
                        $bannedResult = $conn->query("SELECT id FROM users WHERE banned_at IS NOT NULL");
                        while ($bannedRow = $bannedResult->fetch_row()) {
                            $bannedIds[(int) $bannedRow[0]] = true;
                        }
                    } catch (Throwable $error) {
                        /* older database without banned_at */
                    }
                    ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $fullName = trim(
                            ($user["name"] ?? "") .
                            " " .
                            ($user["surname"] ?? "")
                        );
                        $locationParts = [];
                        if (
                            trim(
                                (string)($user["city"] ?? "")
                            ) !== ""
                        ) {
                            $locationParts[] =
                                trim($user["city"]);
                        }
                        if (
                            trim(
                                (string)($user["region"] ?? "")
                            ) !== ""
                        ) {
                            $locationParts[] =
                                trim($user["region"]);
                        }
                        $location =
                            !empty($locationParts)
                                ? implode(", ", $locationParts)
                                : "—";
                        $createdAt = "—";
                        if (!empty($user["created_at"])) {
                            $timestamp =
                                strtotime(
                                    $user["created_at"]
                                );
                            if ($timestamp !== false) {
                                $createdAt =
                                    date(
                                        "d/m/Y",
                                        $timestamp
                                    );
                            }
                        }
                        ?>
                        <tr>
                            <td class="user-id">
                                #<?php echo (int)$user["id"]; ?>
                            </td>
                            <td>
                                <div class="user-info">
                                    <div class="avatar">
                                        <?php
                                        $firstInitial =
                                            mb_substr(
                                                $user["name"] ?? "",
                                                0,
                                                1,
                                                "UTF-8"
                                            );
                                        $secondInitial =
                                            mb_substr(
                                                $user["surname"] ?? "",
                                                0,
                                                1,
                                                "UTF-8"
                                            );
                                        echo adminSafe(
                                            mb_strtoupper(
                                                $firstInitial .
                                                $secondInitial,
                                                "UTF-8"
                                            )
                                        );
                                        ?>
                                    </div>
                                    <div>
                                        <strong>
                                            <?php echo adminSafe($fullName); ?>
                                        </strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @<?php
                                echo adminSafe(
                                    $user["username"]
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo adminSafe(
                                    $user["email"]
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo adminSafe(
                                    $location
                                );
                                ?>
                            </td>
                            <td>

                                <?php if (($user["role"] ?? "user") === "admin"): ?>
                                    <span class="role-badge admin-role">
                                        <?php echo adminT("Admin", "Διαχειριστής"); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="role-badge user-role">
                                        <?php echo adminT("User", "Χρήστης"); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $userWarnings = $approvedCounts[(int) $user["id"]] ?? 0; ?>
                                <span class="warning-count <?php echo $userWarnings === 0 ? "is-zero" : ($userWarnings >= EM_WARNINGS_LIMIT - 1 ? "is-high" : ""); ?>">
                                    <?php echo $userWarnings; ?>/<?php echo EM_WARNINGS_LIMIT; ?>
                                </span>
                                <?php if (isset($bannedIds[(int) $user["id"]])): ?>
                                    <span class="banned-badge" title="<?php echo adminT("Blocked: sees the farewell message at the next visit, then the account is deleted (automatically after 30 days).", "Αποκλεισμένος: βλέπει το μήνυμα αποχαιρετισμού στην επόμενη είσοδο και ο λογαριασμός διαγράφεται (αυτόματα μετά από 30 μέρες)."); ?>">
                                        <?php echo adminT("Blocked", "Αποκλεισμένος"); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo adminSafe($createdAt); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="footer">
    <?php echo adminT("© EnergyMatch — Administration panel", "© EnergyMatch — Πίνακας διαχείρισης"); ?>
</footer>
</body>
</html>
