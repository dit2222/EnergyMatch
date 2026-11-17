<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once "db.php";
require_once "lang.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
$current_user_id = (int) $_SESSION["user_id"];
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: matches.php");
    exit();
}
$viewed_user_id = (int) $_GET["id"];
if ($viewed_user_id === $current_user_id) {
    header("Location: profile.php");
    exit();
}

function safe($value)
{
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, "UTF-8");
}

function getInitials($name, $surname)
{
    $first = mb_substr(trim((string) $name), 0, 1, "UTF-8");
    $second = mb_substr(trim((string) $surname), 0, 1, "UTF-8");
    return mb_strtoupper($first . $second, "UTF-8");
}

function money($value)
{
    return number_format((float) $value, 2, ",", ".") . " €";
}

function numberValue($value, $decimals = 2)
{
    return number_format((float) $value, $decimals, ",", ".");
}

function translatedValue($value)
{
    $keyMap = [
        "electrician" => "electrician",
        "pv_technician" => "pv_technician",
        "engineer" => "engineer",
        "installer" => "installer",
        "other" => "other_specialty",
        "pv_panels" => "pv_panels",
        "inverter" => "inverter",
        "battery_storage" => "battery_storage",
        "mounting_system" => "mounting_system",
        "electrical_equipment" => "electrical_equipment",
        "excellent" => "condition_excellent",
        "good" => "condition_good",
        "fair" => "condition_fair",
        "poor" => "condition_poor"
    ];
    $normalized = strtolower(trim((string) $value));
    if ($normalized === "") {
        return "";
    }
    return isset($keyMap[$normalized]) ? t($keyMap[$normalized]) : str_replace("_", " ", (string) $value);
}
$sql = "
    SELECT
        u.id,
        u.name,
        u.surname,
        ep.location,
        ep.description,
        ep.minimum_share
    FROM users u
    INNER JOIN energy_profiles ep ON ep.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die(t("view_profile_database_error"));
}
$stmt->bind_param("i", $viewed_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header("Location: matches.php?not_found=1");
    exit();
}
$user = $result->fetch_assoc();
$stmt->close();
$contributionSql = "
    SELECT *
    FROM user_contributions
    WHERE user_id = ?
    ORDER BY id ASC
";
$contributionStmt = $conn->prepare($contributionSql);
$contributions = [];
if ($contributionStmt) {
    $contributionStmt->bind_param("i", $viewed_user_id);
    $contributionStmt->execute();
    $contributionResult = $contributionStmt->get_result();
    while ($row = $contributionResult->fetch_assoc()) {
        $contributions[] = $row;
    }
    $contributionStmt->close();
}
$conn->close();
$totalEstimatedValue = 0.0;
foreach ($contributions as $contribution) {
    $totalEstimatedValue += (float) ($contribution["estimated_value"] ?? 0);
}
$fullName = trim($user["name"] . " " . $user["surname"]);
$initials = getInitials($user["name"], $user["surname"]);
$location = trim((string) ($user["location"] ?? ""));
$description = trim((string) ($user["description"] ?? ""));
$minimumShare = (float) ($user["minimum_share"] ?? 0);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($_SESSION["language"] ?? "en"); ?>">
<head>
    <?php include "head_assets.php"; ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/view_profile.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <title><?php echo safe($fullName); ?> - EnergyMatch</title>
</head>
<body>

<section class="hero-section">
    <?php include "navbar.php"; ?>

    <main class="profile-container">
        <a href="matches.php" class="back-link"><span><i data-lucide="arrow-left"></i></span><?php echo t("view_profile_back_matches"); ?></a>

        <section class="profile-header-card">
            <div class="profile-main-info">
                <div class="profile-avatar"><?php echo safe($initials); ?></div>
                <div class="profile-name-area">
                    <p class="profile-label"><?php echo t("view_profile_member_label"); ?></p>
                    <h1><?php echo safe($fullName); ?></h1>
                    <p class="profile-location"><i data-lucide="map-pin"></i> <?php echo $location !== "" ? safe($location) : t("location_not_provided"); ?></p>
                </div>
            </div>
            <div class="profile-status">
                <span class="status-circle"></span>
                <?php echo t("view_profile_available_matching"); ?>
            </div>
        </section>

        <section class="summary-card">
            <div class="section-heading">
                <div>
                    <p class="small-title"><?php echo t("view_profile_participation_label"); ?></p>
                    <h2><?php echo t("view_profile_contribution_summary"); ?></h2>
                </div>
                <div class="section-icon"><i data-lucide="zap"></i></div>
            </div>
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="summary-icon"><i data-lucide="coins"></i></span>
                    <div>
                        <p><?php echo t("view_profile_total_value"); ?></p>
                        <strong><?php echo money($totalEstimatedValue); ?></strong>
                    </div>
                </div>
                <div class="summary-item">
                    <span class="summary-icon"><i data-lucide="chart-column"></i></span>
                    <div>
                        <p><?php echo t("minimum_share"); ?></p>
                        <strong><?php echo numberValue($minimumShare, 2); ?>%</strong>
                    </div>
                </div>
                <div class="summary-item">
                    <span class="summary-icon"><i data-lucide="puzzle"></i></span>
                    <div>
                        <p><?php echo t("view_profile_contribution_types"); ?></p>
                        <strong><?php echo count($contributions); ?></strong>
                    </div>
                </div>
            </div>
            <p class="fair-share-note"><i data-lucide="info"></i> <?php echo t("view_profile_fair_share_note"); ?></p>
        </section>

        <section class="contributions-section">
            <div class="section-heading contributions-heading">
                <div>
                    <p class="small-title"><?php echo t("view_profile_resources_label"); ?></p>
                    <h2><?php echo t("view_profile_contributions_title"); ?></h2>
                </div>
                <div class="section-icon"><i data-lucide="lightbulb"></i></div>
            </div>

            <?php if (empty($contributions)): ?>
                <div class="empty-contributions">
                    <div class="empty-icon"><i data-lucide="leaf"></i></div>
                    <h3><?php echo t("view_profile_no_contributions"); ?></h3>
                    <p><?php echo t("view_profile_no_contributions_text"); ?></p>
                </div>
            <?php else: ?>
                <div class="contribution-grid">

                    <?php foreach ($contributions as $contribution): ?>
                        <?php
                        $type = (string) ($contribution["contribution_type"] ?? "");
                        $icon = '<i data-lucide="zap"></i>';
                        $title = t("view_profile_contribution");
                        if ($type === "capital") {
                            $icon = '<i data-lucide="coins"></i>';
                            $title = t("capital_money");
                        } elseif ($type === "land") {
                            $icon = '<i data-lucide="land-plot"></i>';
                            $title = t("land_space");
                        } elseif ($type === "manual_labor") {
                            $icon = '<i data-lucide="hard-hat"></i>';
                            $title = t("manual_labor");
                        } elseif ($type === "skilled_labor") {
                            $icon = '<i data-lucide="wrench"></i>';
                            $title = t("skilled_labor");
                        } elseif ($type === "equipment") {
                            $icon = '<i data-lucide="plug-zap"></i>';
                            $title = t("equipment_infrastructure");
                        }
                        ?>
                        <article class="contribution-card">
                            <div class="contribution-card-header">
                                <div class="contribution-icon"><?php echo $icon; ?></div>
                                <div>
                                    <p class="small-title"><?php echo t("view_profile_contribution"); ?></p>
                                    <h3><?php echo safe($title); ?></h3>
                                </div>
                                <div class="value-badge">
                                    <span><?php echo t("view_profile_estimated_value"); ?></span>
                                    <strong><?php echo money($contribution["estimated_value"] ?? 0); ?></strong>
                                </div>
                            </div>
                            <div class="contribution-details">

                                <?php if ($type === "capital"): ?>
                                    <div class="contribution-detail"><span><?php echo t("available_capital"); ?></span><strong><?php echo money($contribution["capital_amount"] ?? 0); ?></strong></div>
                                <?php elseif ($type === "land"): ?>
                                    <div class="contribution-detail"><span><?php echo t("land_area"); ?></span><strong><?php echo numberValue($contribution["land_area"] ?? 0); ?></strong></div>

                                    <?php if (!empty($contribution["land_location"])): ?><div class="contribution-detail"><span><?php echo t("land_location"); ?></span><strong><?php echo safe($contribution["land_location"]); ?></strong></div><?php endif; ?>
                                <div class="contribution-detail"><span>Solar Score</span><strong><?php echo $contribution["solar_score"] !== null ? numberValue($contribution["solar_score"]) . "%" : t("land_not_calculated"); ?></strong></div>

<?php if ($contribution["solar_irradiation"] !== null): ?>
<div class="contribution-detail"><span><?php echo t("land_annual_irradiation"); ?></span><strong><?php echo numberValue($contribution["solar_irradiation"]); ?> kWh/m²</strong></div>
<?php endif; ?>

<?php if (isset($contribution["location_factor"]) && $contribution["location_factor"] !== null): ?>
<div class="contribution-detail"><span><?php echo t("land_location_factor"); ?></span><strong><?php echo numberValue($contribution["location_factor"], 3); ?></strong></div>
<?php endif; ?>

<?php if ($contribution["latitude"] !== null && $contribution["longitude"] !== null && $contribution["latitude"] !== "" && $contribution["longitude"] !== ""): ?>

<!-- LAND MAP (read-only): position of the plot -->
<div
    class="view-land-map"
    data-lat="<?php echo safe($contribution["latitude"]); ?>"
    data-lon="<?php echo safe($contribution["longitude"]); ?>"
    aria-label="<?php echo safe(t("land_location")); ?>"
></div>
<?php endif; ?>
<p class="fair-share-note"><?php echo t("land_value_note_short"); ?></p>
<?php elseif ($type === "manual_labor"): ?>
                                    <div class="contribution-detail"><span><?php echo t("manual_hours"); ?></span><strong><?php echo numberValue($contribution["manual_hours"] ?? 0, 1); ?></strong></div>
                                <?php elseif ($type === "skilled_labor"): ?>

                                    <?php if (!empty($contribution["specialty"])): ?><div class="contribution-detail"><span><?php echo t("specialty"); ?></span><strong><?php echo safe(translatedValue($contribution["specialty"])); ?></strong></div><?php endif; ?>
                                    <div class="contribution-detail"><span><?php echo t("skilled_hours"); ?></span><strong><?php echo numberValue($contribution["skilled_hours"] ?? 0, 1); ?></strong></div>
                                <?php elseif ($type === "equipment"): ?>

                                    <?php if (!empty($contribution["equipment_type"])): ?><div class="contribution-detail"><span><?php echo t("equipment_type"); ?></span><strong><?php echo safe(translatedValue($contribution["equipment_type"])); ?></strong></div><?php endif; ?>

                                    <?php if ((int) ($contribution["equipment_quantity"] ?? 0) > 0): ?><div class="contribution-detail"><span><?php echo t("quantity"); ?></span><strong><?php echo (int) $contribution["equipment_quantity"]; ?></strong></div><?php endif; ?>

                                    <?php if ((float) ($contribution["equipment_capacity"] ?? 0) > 0): ?><div class="contribution-detail"><span><?php echo t("equipment_capacity"); ?></span><strong><?php echo numberValue($contribution["equipment_capacity"]); ?> <?php echo safe($contribution["equipment_capacity_unit"] ?? ""); ?></strong></div><?php endif; ?>

                                    <?php if ((float) ($contribution["equipment_age"] ?? 0) > 0): ?><div class="contribution-detail"><span><?php echo t("equipment_age"); ?></span><strong><?php echo numberValue($contribution["equipment_age"], 1); ?></strong></div><?php endif; ?>

                                    <?php if (!empty($contribution["equipment_condition"])): ?><div class="contribution-detail"><span><?php echo t("condition"); ?></span><strong><?php echo safe(translatedValue($contribution["equipment_condition"])); ?></strong></div><?php endif; ?>
                                <?php endif; ?>
                            </div>

                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="details-card">
            <div class="section-heading">
                <div>
                    <p class="small-title"><?php echo t("view_profile_member_details"); ?></p>
                    <h2><?php echo t("view_profile_about_member"); ?></h2>
                </div>
                <div class="section-icon"><i data-lucide="user-round"></i></div>
            </div>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-icon"><i data-lucide="map-pin"></i></div>
                    <div><p><?php echo t("location_region"); ?></p><h3><?php echo $location !== "" ? safe($location) : t("location_not_provided"); ?></h3></div>
                </div>
                <div class="detail-item">
                    <div class="detail-icon"><i data-lucide="chart-column"></i></div>
                    <div><p><?php echo t("minimum_share"); ?></p><h3><?php echo numberValue($minimumShare, 2); ?>%</h3></div>
                </div>
            </div>
            <div class="description-area">
                <h3><i data-lucide="file-text"></i> <?php echo t("view_profile_description_title"); ?></h3>
                <p><?php echo $description !== "" ? nl2br(safe($description)) : t("view_profile_no_description"); ?></p>
            </div>
        </section>
        <div class="profile-buttons">
            <a href="matches.php" class="secondary-btn"><i data-lucide="arrow-left"></i> <?php echo t("view_profile_back_matches"); ?></a>
            <a href="matches.php" class="primary-btn"><?php echo t("view_profile_find_more"); ?></a>
        </div>
    </main>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<script>

/* Read-only map for every land contribution on this profile */
document.addEventListener("DOMContentLoaded", function () {
    if (typeof L === "undefined") return;
    document.querySelectorAll(".view-land-map").forEach(function (element) {
        const lat = parseFloat(element.dataset.lat);
        const lon = parseFloat(element.dataset.lon);
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
        const map = L.map(element, {
            scrollWheelZoom: false,
            dragging: !L.Browser.mobile
        }).setView([lat, lon], 14);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }).addTo(map);
        L.marker([lat, lon]).addTo(map);
    });
});
</script>
</body>
</html>
