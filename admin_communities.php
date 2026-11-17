<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/admin_i18n.php";
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
   VARIABLES
========================================= */
$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");
$allowedStatuses = [
    "",
    "active",
    "cancelled",
    "rejected"
];
if (!in_array($status, $allowedStatuses, true)) {
    $status = "";
}
$communities = [];
$pageError = "";

/* =========================================
   LOAD COMMUNITIES
========================================= */
try {
    $sql = "
        SELECT
            c.id,
            c.name,
            c.created_by,
            c.status,
            c.created_at,
            u.name AS creator_name,
            u.surname AS creator_surname,
            u.username AS creator_username,
            COUNT(cm.id) AS member_count,
            SUM(
                CASE
                    WHEN cm.invitation_status = 'pending'
                    THEN 1
                    ELSE 0
                END
            ) AS pending_count
        FROM communities c
        LEFT JOIN users u
            ON u.id = c.created_by
        LEFT JOIN community_members cm
            ON cm.community_id = c.id
        WHERE 1 = 1
    ";
    $types = "";
    $params = [];

    /* SEARCH */
    if ($search !== "") {
        $sql .= "
            AND (
                c.name LIKE ?
                OR u.name LIKE ?
                OR u.surname LIKE ?
                OR u.username LIKE ?
            )
        ";
        $searchValue = "%" . $search . "%";
        $types .= "ssss";
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    /* STATUS FILTER */
    if ($status !== "") {
        $sql .= "
            AND c.status = ?
        ";
        $types .= "s";
        $params[] = $status;
    }
    $sql .= "
        GROUP BY
            c.id,
            c.name,
            c.created_by,
            c.status,
            c.created_at,
            u.name,
            u.surname,
            u.username
        ORDER BY
            c.created_at DESC,
            c.id DESC
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
        $communities[] = $row;
    }
    $stmt->close();

    /* =========================================
       FAIR SHARE METRICS PER COMMUNITY
       Same rule as admin_community_view.php:
       Fair Share = member value / group value × 100,
       margin = Fair Share − Minimum Share,
       community margin = the smallest member margin (maximin).
       Fixed query, no user input.
    ========================================= */
    $fairness = [];
    $memberResult = $conn->query("
        SELECT
            cm.community_id,
            COALESCE(ep.minimum_share, 0) AS minimum_share,
            COALESCE(
                (
                    SELECT SUM(uc.estimated_value)
                    FROM user_contributions uc
                    WHERE uc.user_id = cm.user_id
                ),
                0
            ) AS contribution_value
        FROM community_members cm
        LEFT JOIN energy_profiles ep
            ON ep.user_id = cm.user_id
    ");
    if ($memberResult) {
        $membersByCommunity = [];
        while ($memberRow = $memberResult->fetch_assoc()) {
            $membersByCommunity[(int) $memberRow["community_id"]][] = $memberRow;
        }
        foreach ($membersByCommunity as $communityId => $communityMembers) {
            $groupValue = 0.0;
            foreach ($communityMembers as $m) {
                $groupValue += (float) $m["contribution_value"];
            }
            $minMargin = null;
            foreach ($communityMembers as $m) {
                $share = $groupValue > 0
                    ? ((float) $m["contribution_value"] / $groupValue) * 100
                    : 0;
                $margin = $share - (float) $m["minimum_share"];
                if ($minMargin === null || $margin < $minMargin) {
                    $minMargin = $margin;
                }
            }
            $fairness[$communityId] = [
                "group_value" => $groupValue,
                "min_margin"  => $minMargin,
            ];
        }
    }
} catch (Throwable $e) {
    error_log(
        "Admin communities error: " .
        $e->getMessage()
    );
    $pageError =
        adminT("Could not load the communities.", "Δεν ήταν δυνατή η φόρτωση των κοινοτήτων.");
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
        <?php echo adminT("EnergyMatch | Community management", "EnergyMatch | Διαχείριση κοινοτήτων"); ?>
    </title>
    <link
        rel="stylesheet"
        href="css/admin_communities.css?v=<?php echo ASSET_VERSION; ?>"
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

    <!-- PAGE HEADER -->
    <section class="page-header">
        <div>
            <p class="page-label">
                <?php echo adminT("ADMINISTRATION", "ΔΙΑΧΕΙΡΙΣΗ"); ?>
            </p>
            <h1>
                <?php echo adminT("Community management", "Διαχείριση κοινοτήτων"); ?>
            </h1>
            <p>
                <?php echo adminT("Oversee all energy communities created in EnergyMatch.", "Εποπτεία όλων των ενεργειακών κοινοτήτων που έχουν δημιουργηθεί στο EnergyMatch."); ?>
            </p>
        </div>
        <div class="community-total">
            <span>
                <?php echo adminT("Results", "Αποτελέσματα"); ?>
            </span>
            <strong>
                <?php echo count($communities); ?>
            </strong>
        </div>
    </section>

    <?php if ($pageError !== ""): ?>
        <div class="error-box">
            <?php echo adminSafe($pageError); ?>
        </div>
    <?php endif; ?>

    <!-- =====================================
         FILTERS
    ====================================== -->

    <section class="filter-panel">

        <form
            method="GET"
            action="admin_communities.php"
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
                    placeholder="<?php echo adminT("Community or creator...", "Κοινότητα ή δημιουργός..."); ?>"
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
                        value="active"
                        <?php echo $status === "active" ? "selected" : ""; ?>
                    >
                        <?php echo adminT("Active", "Ενεργή"); ?>
                    </option>
                    <option
                        value="cancelled"
                        <?php echo $status === "cancelled" ? "selected" : ""; ?>
                    >
                        <?php echo adminT("Cancelled", "Ακυρωμένη"); ?>
                    </option>
                    <option
                        value="rejected"
                        <?php echo $status === "rejected" ? "selected" : ""; ?>
                    >
                        <?php echo adminT("Rejected", "Απορριφθείσα"); ?>
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
                    href="admin_communities.php"
                    class="clear-button"
                >
                    <?php echo adminT("Clear", "Καθαρισμός"); ?>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- =====================================
         COMMUNITIES
    ====================================== -->

    <section class="communities-panel">
        <div class="panel-heading">
            <div>
                <h2>
                    <?php echo adminT("Energy communities", "Ενεργειακές κοινότητες"); ?>
                </h2>
                <p>
                    <?php echo adminT("Full list of the platform communities.", "Πλήρης λίστα των κοινοτήτων της πλατφόρμας."); ?>
                </p>
            </div>
        </div>

        <?php if (empty($communities)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-lucide="house"></i>
                </div>
                <h3>
                    <?php echo adminT("No communities found", "Δεν βρέθηκαν κοινότητες"); ?>
                </h3>
                <p>
                    <?php echo adminT("No communities match these filters.", "Δεν υπάρχουν κοινότητες που να αντιστοιχούν στα συγκεκριμένα φίλτρα."); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="communities-table">
                    <thead>
                        <tr>
                            <th>
                                ID
                            </th>
                            <th>
                                <?php echo adminT("Community", "Κοινότητα"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Creator", "Δημιουργός"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Status", "Κατάσταση"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Members", "Μέλη"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Pending", "Σε αναμονή"); ?>
                            </th>
                            <th title="<?php echo adminT("The smallest (Fair Share − Minimum Share) among the members", "Το μικρότερο (Fair Share − Minimum Share) ανάμεσα στα μέλη"); ?>">
                                <?php echo adminT("Min. margin", "Ελάχ. περιθώριο"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Total value", "Συνολική αξία"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Created", "Δημιουργήθηκε"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Action", "Ενέργεια"); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($communities as $community): ?>
                        <?php
                        $creatorName = trim(
                            ($community["creator_name"] ?? "") .
                            " " .
                            ($community["creator_surname"] ?? "")
                        );
                        if ($creatorName === "") {
                            $creatorName = adminT("Unknown user", "Άγνωστος χρήστης");
                        }
                        $createdAt = "—";
                        if (!empty($community["created_at"])) {
                            $timestamp =
                                strtotime(
                                    $community["created_at"]
                                );
                            if ($timestamp !== false) {
                                $createdAt =
                                    date(
                                        "d/m/Y H:i",
                                        $timestamp
                                    );
                            }
                        }
                        $communityStatus =
                            strtolower(
                                trim(
                                    $community["status"] ?? ""
                                )
                            );
                        ?>
                        <tr>

                            <!-- ID -->
                            <td class="community-id">
                                #<?php echo (int)$community["id"]; ?>
                            </td>

                            <!-- COMMUNITY -->
                            <td>
                                <div class="community-name">
                                    <strong>
                                        <?php
                                        echo adminSafe(
                                            $community["name"]
                                        );
                                        ?>
                                    </strong>
                                </div>
                            </td>

                            <!-- CREATOR -->
                            <td>
                                <div class="creator">
                                    <strong>
                                        <?php
                                        echo adminSafe(
                                            $creatorName
                                        );
                                        ?>
                                    </strong>

                                    <?php if (!empty($community["creator_username"])): ?>
                                        <small>
                                            @<?php
                                            echo adminSafe(
                                                $community["creator_username"]
                                            );
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- STATUS -->
                            <td>

                                <?php if ($communityStatus === "active"): ?>
                                    <span class="status-badge status-active">
                                        <?php echo adminT("Active", "Ενεργή"); ?>
                                    </span>
                                <?php elseif ($communityStatus === "cancelled"): ?>
                                    <span class="status-badge status-cancelled">
                                        <?php echo adminT("Cancelled", "Ακυρωμένη"); ?>
                                    </span>
                                <?php elseif ($communityStatus === "rejected"): ?>
                                    <span class="status-badge status-rejected">
                                        <?php echo adminT("Rejected", "Απορριφθείσα"); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-other">
                                        <?php
                                        echo adminSafe(
                                            adminStatusLabel($communityStatus)
                                        );
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- MEMBERS -->
                            <td>
                                <span class="members-count">
                                    <?php
                                    echo (int)$community["member_count"];
                                    ?>
                                </span>
                            </td>

                            <!-- PENDING -->
                            <td>

                                <?php if ((int)$community["pending_count"] > 0): ?>
                                    <span class="pending-count">
                                        <?php
                                        echo (int)$community["pending_count"];
                                        ?>
                                    </span>
                                <?php else: ?>
                                    <span class="zero-value">
                                        0
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php $communityFairness = $fairness[(int) $community["id"]] ?? null; ?>

                            <!-- MINIMUM FAIRNESS MARGIN (maximin) -->
                            <td>

                                <?php if ($communityFairness !== null && $communityFairness["min_margin"] !== null): ?>
                                    <?php $minMargin = (float) $communityFairness["min_margin"]; ?>
                                    <strong class="<?php echo $minMargin >= 0 ? "margin-positive" : "margin-negative"; ?>">
                                        <?php echo ($minMargin >= 0 ? "+" : "") . number_format($minMargin, 2, ",", "."); ?>%
                                    </strong>
                                <?php else: ?>
                                    <span class="zero-value">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- TOTAL GROUP VALUE -->
                            <td>
                                <span class="needs">
                                    <?php echo $communityFairness !== null
                                        ? number_format((float) $communityFairness["group_value"], 2, ",", ".")
                                        : "—"; ?>
                                </span>
                            </td>

                            <!-- DATE -->
                            <td>
                                <?php
                                echo adminSafe(
                                    $createdAt
                                );
                                ?>
                            </td>
                            <td>
                                <a
                                    href="admin_community_view.php?id=<?php echo (int) $community["id"]; ?>"
                                    class="view-community-btn"
                                >
                                    <?php echo adminT("View", "Προβολή"); ?>
                                </a>
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
