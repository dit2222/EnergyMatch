<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/admin_i18n.php";
include "db.php";

/* =========================================
   ADMIN ACCESS
========================================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
if (($_SESSION["role"] ?? "user") !== "admin") {
    header("Location: dashboard.php");
    exit();
}

/* =========================================
   COMMUNITY ID
========================================= */
$community_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
if ($community_id <= 0) {
    header("Location: admin_communities.php");
    exit();
}

/* =========================================
   COMMUNITY INFORMATION
========================================= */
$stmt = $conn->prepare("
    SELECT
        c.id,
        c.name,
        c.status,
        c.created_at,
        c.created_by,
        u.name AS creator_name,
        u.surname AS creator_surname,
        u.username AS creator_username
    FROM communities c
    LEFT JOIN users u
        ON u.id = c.created_by
    WHERE c.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$result = $stmt->get_result();
$community = $result->fetch_assoc();
$stmt->close();
if (!$community) {
    header("Location: admin_communities.php");
    exit();
}

/* =========================================
   COMMUNITY MEMBERS
========================================= */
$stmt = $conn->prepare("
    SELECT
        cm.id AS membership_id,
        cm.user_id,
        cm.invitation_status,
        cm.responded_at,
        cm.joined_at,
        u.name,
        u.surname,
        u.username,
        u.email,
        ep.location,
        ep.minimum_share,
        COALESCE(
            (
                SELECT SUM(uc.estimated_value)
                FROM user_contributions uc
                WHERE uc.user_id = cm.user_id
            ),
            0
        ) AS contribution_value
    FROM community_members cm
    INNER JOIN users u
        ON u.id = cm.user_id
    LEFT JOIN energy_profiles ep
        ON ep.user_id = cm.user_id
    WHERE cm.community_id = ?
    ORDER BY cm.id ASC
");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$result = $stmt->get_result();
$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}
$stmt->close();

/* =========================================
   TOTAL ECONOMIC VALUE
========================================= */
$total_value = 0;
foreach ($members as $member) {
    $total_value += (float) $member["contribution_value"];
}

/* =========================================
   CALCULATE FAIR SHARE
========================================= */
foreach ($members as $key => $member) {
    $value = (float) $member["contribution_value"];
    $minimum_share = (float) $member["minimum_share"];
    if ($total_value > 0) {
        $fair_share = ($value / $total_value) * 100;
    } else {
        $fair_share = 0;
    }
    $margin = $fair_share - $minimum_share;
    $members[$key]["fair_share"] = $fair_share;
    $members[$key]["margin"] = $margin;
    $members[$key]["requirement_met"] = ($fair_share >= $minimum_share);
}

/* =========================================
   COMMUNITY FAIRNESS
========================================= */
$minimum_margin = null;
$community_is_fair = !empty($members);
foreach ($members as $member) {
    if (
        $minimum_margin === null ||
        $member["margin"] < $minimum_margin
    ) {
        $minimum_margin = $member["margin"];
    }
    if (!$member["requirement_met"]) {
        $community_is_fair = false;
    }
}
if ($minimum_margin === null) {
    $minimum_margin = 0;
}

/* =========================================
   HELPERS
========================================= */

function adminSafe($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function adminStatusClass($status)
{
    $status = strtolower((string) $status);
    switch ($status) {
        case "active":
        case "accepted":
        case "joined":
            return "status-green";
        case "pending":
            return "status-yellow";
        case "cancelled":
        case "rejected":
        case "declined":
            return "status-red";
        default:
            return "status-gray";
    }
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
        <?php echo adminT("Community details | EnergyMatch Admin", "Λεπτομέρειες κοινότητας | EnergyMatch Admin"); ?>
    </title>
    <link
        rel="stylesheet"
        href="css/admin_community_view.css?v=<?php echo ASSET_VERSION; ?>"
    >
</head>
<body>

<!-- =========================================
     NAVBAR
========================================= -->

<?php include "admin_navbar.php"; ?>

<main class="admin-container">

    <!-- =====================================
         BACK BUTTON
    ====================================== -->
    <div class="top-actions">
        <a
            href="admin_communities.php"
            class="back-button"
        >
            <i data-lucide="arrow-left"></i> <?php echo adminT("Back to communities", "Επιστροφή στις κοινότητες"); ?>
        </a>
    </div>

    <!-- =====================================
         COMMUNITY HEADER
    ====================================== -->

    <section class="community-header">
        <div>
            <div class="community-label">
                <?php echo adminT("ENERGY COMMUNITY #", "ΕΝΕΡΓΕΙΑΚΗ ΚΟΙΝΟΤΗΤΑ #"); ?><?php echo (int) $community["id"]; ?>
            </div>
            <h1>
                <?php echo adminSafe($community["name"]); ?>
            </h1>
            <p>
                <?php echo adminT("Detailed view of the community, its members and the Fair Share distribution.", "Αναλυτική προβολή της κοινότητας, των μελών και της κατανομής Fair Share."); ?>
            </p>
        </div>
        <span
            class="status-badge
            <?php echo adminStatusClass($community["status"]); ?>"
        >
            <?php echo adminUpper(adminStatusLabel($community["status"])); ?>
        </span>
    </section>

    <!-- =====================================
         COMMUNITY INFO
    ====================================== -->

    <section class="summary-grid">
        <div class="summary-card">
            <span class="summary-label">
                <?php echo adminT("Creator", "Δημιουργός"); ?>
            </span>
            <strong>
                <?php
                echo adminSafe(
                    trim(
                        $community["creator_name"] . " " .
                        $community["creator_surname"]
                    )
                );
                ?>
            </strong>
            <small>
                @<?php echo adminSafe($community["creator_username"]); ?>
            </small>
        </div>
        <div class="summary-card">
            <span class="summary-label">
                <?php echo adminT("Members", "Μέλη"); ?>
            </span>
            <strong>
                <?php echo count($members); ?>
            </strong>
            <small>
                <?php echo adminT("Community members", "Μέλη κοινότητας"); ?>
            </small>
        </div>
        <div class="summary-card">
            <span class="summary-label">
                <?php echo adminT("Total group value", "Συνολική αξία ομάδας"); ?>
            </span>
            <strong>
                €<?php echo number_format($total_value, 2); ?>
            </strong>
            <small>
                <?php echo adminT("Economic contribution", "Οικονομική συνεισφορά"); ?>
            </small>
        </div>
        <div class="summary-card">
            <span class="summary-label">
                <?php echo adminT("Minimum fairness margin", "Ελάχιστο περιθώριο δικαιοσύνης"); ?>
            </span>
            <strong
                class="<?php
                echo $minimum_margin >= 0
                    ? "positive-text"
                    : "negative-text";
                ?>"
            >
                <?php
                echo ($minimum_margin >= 0 ? "+" : "") .
                    number_format($minimum_margin, 2);
                ?>%
            </strong>
            <small>
                <?php echo adminT("Lowest member margin", "Χαμηλότερο περιθώριο μέλους"); ?>
            </small>
        </div>
    </section>

    <!-- =====================================
         FAIRNESS RESULT
    ====================================== -->

    <section
        class="fairness-result
        <?php echo $community_is_fair ? "fair" : "unfair"; ?>"
    >
        <div class="fairness-icon">
            <?php echo $community_is_fair ? '<i data-lucide="check"></i>' : '<i data-lucide="triangle-alert"></i>'; ?>
        </div>
        <div>
            <strong>

                <?php if ($community_is_fair): ?>
                    <?php echo adminT("Fair Share requirements satisfied", "Οι απαιτήσεις Fair Share ικανοποιούνται"); ?>
                <?php else: ?>
                    <?php echo adminT("Fair Share requirements not satisfied", "Οι απαιτήσεις Fair Share δεν ικανοποιούνται"); ?>
                <?php endif; ?>
            </strong>
            <p>

                <?php if ($community_is_fair): ?>
                    <?php echo adminT("Every member has a Fair Share greater than or equal to their Minimum Share.", "Το Fair Share όλων των μελών είναι μεγαλύτερο ή ίσο από το Minimum Share τους."); ?>
                <?php else: ?>
                    <?php echo adminT("At least one member has a Fair Share lower than their Minimum Share.", "Τουλάχιστον ένα μέλος έχει Fair Share μικρότερο από το Minimum Share του."); ?>
                <?php endif; ?>
            </p>
        </div>
    </section>

    <!-- =====================================
         MEMBERS
    ====================================== -->

    <section class="members-section">
        <div class="section-heading">
            <div>
                <span class="section-label">
                    <?php echo adminT("COMMUNITY MEMBERS", "ΜΕΛΗ ΚΟΙΝΟΤΗΤΑΣ"); ?>
                </span>
                <h2>
                    <?php echo adminT("Member analysis", "Ανάλυση μελών"); ?>
                </h2>
            </div>
            <span class="member-count">
                <?php echo count($members); ?> <?php echo adminT("members", "μέλη"); ?>
            </span>
        </div>

        <?php if (empty($members)): ?>
            <div class="empty-state">
                <?php echo adminT("There are no members in this community.", "Δεν υπάρχουν μέλη σε αυτή την κοινότητα."); ?>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="members-table">
                    <thead>
                        <tr>
                            <th><?php echo adminT("Member", "Μέλος"); ?></th>
                            <th>
                                <?php echo adminT("Invitation", "Πρόσκληση"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Economic contribution", "Οικονομική συνεισφορά"); ?>
                            </th>
                            <th>
                                Minimum Share
                            </th>
                            <th>
                                Fair Share
                            </th>
                            <th>
                                <?php echo adminT("Margin", "Περιθώριο"); ?>
                            </th>
                            <th>
                                <?php echo adminT("Result", "Αποτέλεσμα"); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($members as $member): ?>
                        <tr>

                            <!-- MEMBER -->
                            <td>
                                <div class="member-info">
                                    <div class="member-avatar">
                                        <?php
                                        echo strtoupper(
                                            mb_substr(
                                                $member["name"],
                                                0,
                                                1
                                            ) .
                                            mb_substr(
                                                $member["surname"],
                                                0,
                                                1
                                            )
                                        );
                                        ?>
                                    </div>
                                    <div>
                                        <strong>
                                            <?php
                                            echo adminSafe(
                                                $member["name"] . " " .
                                                $member["surname"]
                                            );
                                            ?>
                                        </strong>
                                        <small>
                                            @<?php
                                            echo adminSafe(
                                                $member["username"]
                                            );
                                            ?>
                                        </small>
                                        <?php
                                        if (
                                            (int) $member["user_id"] ===
                                            (int) $community["created_by"]
                                        ):
                                        ?>
                                            <span class="creator-tag">
                                                <?php echo adminT("CREATOR", "ΔΗΜΙΟΥΡΓΟΣ"); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- INVITATION -->
                            <td>
                                <span
                                    class="status-badge
                                    <?php
                                    echo adminStatusClass(
                                        $member["invitation_status"]
                                    );
                                    ?>"
                                >
                                    <?php
                                    echo adminUpper(
                                        adminStatusLabel(
                                            $member["invitation_status"]
                                        )
                                    );
                                    ?>
                                </span>
                            </td>

                            <!-- ECONOMIC VALUE -->
                            <td>
                                <strong>
                                    €<?php
                                    echo number_format(
                                        (float)
                                        $member["contribution_value"],
                                        2
                                    );
                                    ?>
                                </strong>
                            </td>

                            <!-- MINIMUM SHARE -->
                            <td>
                                <?php
                                echo number_format(
                                    (float) $member["minimum_share"],
                                    2
                                );
                                ?>%
                            </td>

                            <!-- FAIR SHARE -->
                            <td>
                                <strong class="fair-share-value">
                                    <?php
                                    echo number_format(
                                        $member["fair_share"],
                                        2
                                    );
                                    ?>%
                                </strong>
                            </td>

                            <!-- MARGIN -->
                            <td>
                                <span
                                    class="<?php
                                    echo $member["margin"] >= 0
                                        ? "positive-text"
                                        : "negative-text";
                                    ?>"
                                >
                                    <?php
                                    echo (
                                        $member["margin"] >= 0
                                            ? "+"
                                            : ""
                                    );
                                    echo number_format(
                                        $member["margin"],
                                        2
                                    );
                                    ?>%
                                </span>
                            </td>

                            <!-- RESULT -->
                            <td>
                                <?php
                                if ($member["requirement_met"]):
                                ?>
                                    <span class="result-ok">
                                        <i data-lucide="check"></i> <?php echo adminT("Meets requirement", "Πληροί την απαίτηση"); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="result-fail">
                                        <i data-lucide="x"></i> <?php echo adminT("Below minimum", "Κάτω από το ελάχιστο"); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- =====================================
         COMMUNITY DETAILS
    ====================================== -->

    <section class="details-section">
        <h2>
            <?php echo adminT("Community information", "Πληροφορίες κοινότητας"); ?>
        </h2>
        <div class="details-grid">
            <div>
                <span>
                    <?php echo adminT("Community ID", "Κωδικός κοινότητας"); ?>
                </span>
                <strong>
                    #<?php echo (int) $community["id"]; ?>
                </strong>
            </div>
            <div>
                <span>
                    <?php echo adminT("Created at", "Δημιουργήθηκε"); ?>
                </span>
                <strong>
                    <?php
                    echo date(
                        "d/m/Y H:i",
                        strtotime($community["created_at"])
                    );
                    ?>
                </strong>
            </div>
            <div>
                <span>
                    <?php echo adminT("Status", "Κατάσταση"); ?>
                </span>
                <strong>
                    <?php echo adminStatusLabel($community["status"]); ?>
                </strong>
            </div>
            <div>
                <span>
                    <?php echo adminT("Creator user ID", "Κωδικός δημιουργού"); ?>
                </span>
                <strong>
                    #<?php echo (int) $community["created_by"]; ?>
                </strong>
            </div>
        </div>
    </section>
</main>
</body>
</html>
