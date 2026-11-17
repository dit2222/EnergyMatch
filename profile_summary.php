<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - PROFILE SUMMARY
|--------------------------------------------------------------------------
|
| Included by profile.php. Shows the saved energy profile in a compact,
| read-only form: what the user declared, how much each contribution was
| valued at, and HOW that value was calculated.
|
| It only READS the values already stored in user_contributions
| (estimated_value etc.). Nothing is recalculated or saved here, so the
| numbers shown are exactly the ones the matching algorithm uses.
|
| Expects in scope: $profile, $contributionRows, safe(), t()
| All texts are translation keys in lang.php ("summary_*").
|
*/

/* Greek-style number: 1.540,88 */
function psNum($value, $decimals = 2)
{
    return number_format((float) $value, $decimals, ",", ".");
}

function psMoney($value)
{
    return psNum($value) . " €";
}

require_once __DIR__ . "/contribution_valuation.php";

$typeInfo = [
    "capital"       => ["icon" => '<i data-lucide="coins"></i>', "label" => t("summary_capital_money")],
    "land"          => ["icon" => '<i data-lucide="land-plot"></i>', "label" => t("summary_land_space")],
    "manual_labor"  => ["icon" => '<i data-lucide="hard-hat"></i>', "label" => t("summary_manual_labor")],
    "skilled_labor" => ["icon" => '<i data-lucide="wrench"></i>', "label" => t("summary_skilled_labor_expertise")],
    "equipment"     => ["icon" => '<i data-lucide="plug-zap"></i>', "label" => t("summary_equipment_infrastructure")],
];

$equipmentLabels = [
    "pv_panels"            => t("summary_pv_panels"),
    "battery_storage"      => t("summary_storage_batteries"),
    "electrical_equipment" => t("summary_electrical_equipment"),
];

$conditionLabels = [
    "excellent" => t("summary_excellent"),
    "good"      => t("summary_good"),
    "fair"      => t("summary_fair"),
    "poor"      => t("summary_poor"),
];

$chemistryLabels = [
    "lfp"       => "LFP",
    "nmc"       => "NMC",
    "lead_acid" => t("summary_lead_acid"),
];

/* =========================================
   BUILD ONE "CARD" PER CONTRIBUTION
   Each card has:
   - declared: what the user entered
   - steps:    the calculation, line by line
   - value:    the stored estimated_value
========================================= */

$summaryCards = [];
$totalValue = 0.0;
$landForMap = null;

foreach ($contributionRows as $row) {

    $type = $row["contribution_type"];

    if (!isset($typeInfo[$type])) {
        continue;
    }

    $value = $row["estimated_value"] !== null ? (float) $row["estimated_value"] : null;
    $declared = [];
    $steps = [];
    $note = "";
    $unitSuffix = "";

    /* ---------- CAPITAL ---------- */
    if ($type === "capital") {

        $amount = (float) $row["capital_amount"];

        $declared[] = [t("summary_amount"), psMoney($amount)];

        $steps[] = t("summary_money_is_already_a_monetary_value");
        $steps[] = "<strong>" . psMoney($amount) . " = " . psMoney($amount) . "</strong>";
    }

    /* ---------- LAND ---------- */
    elseif ($type === "land") {

        $area = (float) $row["land_area"];
        $areaM2 = $area * 1000;
        $lat = $row["latitude"];
        $lon = $row["longitude"];
        $irradiation = $row["solar_irradiation"] !== null ? (float) $row["solar_irradiation"] : null;

        $declared[] = [t("summary_area"), psNum($area) . " " . t("summary_stremmata") . " (" . psNum($areaM2, 0) . " m²)"];

        if (trim((string) $row["land_location"]) !== "") {
            $declared[] = [t("summary_location"), safe($row["land_location"])];
        }

        if ($lat !== null && $lon !== null && $lat !== "" && $lon !== "") {
            $declared[] = [t("summary_coordinates"), psNum($lat, 6) . ", " . psNum($lon, 6)];
            $landForMap = ["lat" => (float) $lat, "lon" => (float) $lon];
        }

        if ($irradiation !== null) {
            $declared[] = [t("summary_annual_solar_irradiation_pvgis"), psNum($irradiation) . " kWh/m²"];
        }

        if ($row["optimal_slope"] !== null) {
            $declared[] = [t("summary_optimal_panel_slope"), psNum($row["optimal_slope"], 0) . "°"];
        }

        if ($row["optimal_azimuth"] !== null) {
            $declared[] = [t("summary_optimal_azimuth"), psNum($row["optimal_azimuth"], 0) . "°"];
        }

        if ($row["solar_score"] !== null) {
            $declared[] = ["Solar Score", psNum($row["solar_score"]) . "% <small>(" . t("summary_scale_1_300_2_100_kwh") . ")</small>"];
        }

        /* Land value (see contribution_valuation.php):
           stremmata × (Eurostat national average / 10) × f,
           f = H / H_reference limited to 0.8 - 1.2.
           Price per stremma and H_reference come from land_valuation_data.php;
           f is the value stored when the profile was saved. */
        $landData = require __DIR__ . "/land_valuation_data.php";
        $nationalAverage = (float) $landData["national_average"];
        $pricePerStremma = $nationalAverage / (float) $landData["stremmata_per_hectare"];
        $hReference = $landData["h_reference"] !== null ? (float) $landData["h_reference"] : null;
        $factor = $row["location_factor"] !== null ? (float) $row["location_factor"] : null;

        $steps[] = t("summary_land_step1_price");
        $steps[] = psNum($nationalAverage) . " €/ha ÷ 10 = <strong>" . psNum($pricePerStremma) . " €/" . t("summary_stremma") . "</strong>";

        $steps[] = t("summary_land_step2_factor");

        if ($factor !== null && $irradiation !== null && $hReference !== null) {
            $rawFactor = $irradiation / $hReference;
            $clamped = abs($rawFactor - $factor) > 0.0001
                ? " <small>(" . t("summary_land_factor_limited") . ")</small>"
                : "";
            $steps[] = psNum($irradiation) . " ÷ " . psNum($hReference) . " = <strong>" . psNum($factor, 4) . "</strong>" . $clamped;
        } else {
            $steps[] = "<strong>" . psNum($factor ?? 1, 4) . "</strong> <small>(" . t("summary_land_factor_default") . ")</small>";
        }

        if ($value !== null) {
            $steps[] = t("summary_land_step3_value");
            $steps[] = psNum($area) . " × " . psNum($pricePerStremma) . " € × " . psNum($factor ?? 1, 4) . " = <strong>" . psMoney($value) . "</strong>";
        }

        $note = t("summary_land_note");
    }

    /* ---------- MANUAL & SKILLED LABOR ---------- */
    elseif ($type === "manual_labor" || $type === "skilled_labor") {

        $hours = (float) ($type === "manual_labor" ? $row["manual_hours"] : $row["skilled_hours"]);

        $specialtyLabels = [
            "Electrician"   => t("electrician"),
            "PV Technician" => t("pv_technician"),
            "Engineer"      => t("engineer"),
            "Installer"     => t("installer"),
            "Other"         => t("other_specialty"),
        ];

        if ($type === "skilled_labor" && trim((string) $row["specialty"]) !== "") {
            $declared[] = [t("summary_specialty"), $specialtyLabels[$row["specialty"]] ?? safe($row["specialty"])];
        }

        $declared[] = [t("summary_committed_hours"), psNum($hours) . " " . t("summary_hours")];

        /* Hourly rate of the matching occupation group
           (Eurostat SES 2022, Greece, see labor_valuation_data.php) */
        $reference = getLaborReference($type, $row["specialty"] ?? null);
        $rate = (float) $reference["hourly_rate"];
        $countedHours = min($hours, MAX_LABOR_HOURS);

        if ($countedHours > 0 && $value !== null) {
            $steps[] = t("summary_labor_step1_rate");
            $steps[] = "ISCO " . $reference["isco"] . " · " . t("labor_group_" . $reference["isco"]) . ": <strong>" . psNum($rate) . " €/" . t("summary_hour") . "</strong>";
            $steps[] = t("summary_step2_value_hours_hourly_rate");
            $steps[] = psNum($countedHours) . " × " . psNum($rate) . " € = <strong>" . psMoney($value) . "</strong>";
        }

        $note = t("summary_labor_note");
    }

    /* ---------- EQUIPMENT ---------- */
    elseif ($type === "equipment") {

        $eqType = (string) $row["equipment_type"];
        $condition = (string) $row["equipment_condition"];

        /* Every step of the calculation (contribution_valuation.php) */
        $eq = getEquipmentValuationBreakdown(
            $eqType,
            $row["equipment_quantity"],
            $row["equipment_capacity"],
            $row["equipment_capacity_unit"],
            $row["equipment_age"],
            $condition
        );

        $unitText = $eq !== null ? $eq["capacity_unit"] : safe((string) $row["equipment_capacity_unit"]);
        $capacityShown = $eq !== null ? $eq["capacity_per_unit"] : (float) $row["equipment_capacity"];

        $declared[] = [t("summary_type"), $equipmentLabels[$eqType] ?? safe($eqType)];
        $declared[] = [t("summary_quantity"), (int) $row["equipment_quantity"]];
        $declared[] = [t("summary_capacity_per_unit"), psNum($capacityShown) . " " . $unitText];
        $declared[] = [t("summary_age"), psNum((float) $row["equipment_age"], 1) . " " . t("summary_years")];
        $declared[] = [t("summary_condition"), $conditionLabels[$condition] ?? safe($condition)];

        if (!empty($row["battery_chemistry"])) {
            $declared[] = [t("summary_battery_chemistry"), $chemistryLabels[$row["battery_chemistry"]] ?? safe($row["battery_chemistry"])];
        }

        if ($eq !== null && $value !== null) {

            $priceDecimals = $eq["reference_price"] < 1 ? 3 : 2;

            $steps[] = t("summary_eq_step1_new_value");
            $steps[] = $eq["quantity"] . " × " . psNum($eq["capacity_per_unit"]) . " " . $eq["capacity_unit"]
                . " × " . psNum($eq["reference_price"], $priceDecimals) . " " . $eq["price_unit"]
                . " = <strong>" . psMoney($eq["new_value"]) . "</strong>";

            $steps[] = t("summary_eq_step2_age_factor");
            $rawAgeFactor = 1 - ($eq["age"] / $eq["service_life"]);
            $steps[] = "1 − " . psNum($eq["age"], 1) . " / " . psNum($eq["service_life"], 0)
                . ($eq["age_factor_at_minimum"]
                    ? " = " . psNum($rawAgeFactor, 2) . " → <strong>" . psNum($eq["age_factor"], 4) . "</strong> <small>(" . t("summary_eq_age_minimum") . ")</small>"
                    : " = <strong>" . psNum($eq["age_factor"], 4) . "</strong>");

            $steps[] = t("summary_eq_step3_condition");
            $steps[] = "<strong>" . psNum($eq["condition_factor"]) . "</strong> <small>(" . ($conditionLabels[$condition] ?? safe($condition)) . ")</small>";

            $steps[] = t("summary_eq_step4_value");
            $steps[] = psMoney($eq["new_value"]) . " × " . psNum($eq["age_factor"], 4) . " × " . psNum($eq["condition_factor"])
                . " = <strong>" . psMoney($value) . "</strong>";

            /* source text in the user's language (lang.php), the data file keeps the English original */
            $sourceKey = "price_source_" . $eqType;
            $sourceText = t($sourceKey);
            $note = t("summary_reference_price_source") . ": " . safe($sourceText !== $sourceKey ? $sourceText : $eq["price_source"]);
        }
    }

    if ($value !== null && $value > 0) {
        $totalValue += $value;
    }

    $summaryCards[] = [
        "type"     => $type,
        "icon"     => $typeInfo[$type]["icon"],
        "label"    => $typeInfo[$type]["label"],
        "declared" => $declared,
        "steps"    => $steps,
        "value"    => $value,
        "suffix"   => $unitSuffix,
        "note"     => $note,
    ];
}
?>

<!-- =========================================
     PROFILE SUMMARY (read-only)
========================================= -->
<div class="profile-summary summary-only">

    <div class="summary-header">
        <div>
            <h1><?php echo t("summary_my_energy_profile"); ?></h1>
            <p class="subtitle">
                <?php echo t("summary_what_you_contribute_how_it_was"); ?>
            </p>
        </div>

        <?php if (!empty($profileLocked)): ?>

        <span class="summary-locked-badge"><i data-lucide="lock"></i> <?php echo t("profile_locked_badge"); ?></span>

        <?php else: ?>

        <a href="profile.php?edit=1" class="summary-edit-btn" id="openProfileEditor">
            <i data-lucide="pencil"></i> <?php echo t("summary_edit_profile"); ?>
        </a>

        <?php endif; ?>
    </div>

    <!-- KEY FIGURES -->
    <div class="summary-stats">

        <div class="summary-stat summary-stat-main">
            <span><?php echo t("summary_total_contribution_score"); ?></span>
            <strong><?php echo psNum($totalValue); ?></strong>
            <small><?php echo t("summary_sum_of_all_your_contributions_below"); ?></small>
        </div>

        <div class="summary-stat">
            <span><?php echo t("summary_minimum_share"); ?></span>
            <strong><?php echo psNum($profile["minimum_share"] ?? 0); ?>%</strong>
            <small><?php echo t("summary_your_fair_share_is_calculated_per"); ?></small>
        </div>

        <div class="summary-stat">
            <span><?php echo t("summary_location_region"); ?></span>
            <strong class="summary-stat-text">
                <?php echo trim((string) ($profile["location"] ?? "")) !== "" ? safe($profile["location"]) : "—"; ?>
            </strong>
            <small><?php echo count($summaryCards) . " " . t("summary_contribution_type_s"); ?></small>
        </div>

    </div>

    <!-- CONTRIBUTIONS -->
    <?php if (empty($summaryCards)): ?>

        <div class="summary-empty">
            <?php echo t("summary_you_have_not_added_any_contribution"); ?>
        </div>

    <?php else: ?>

        <div class="summary-contributions">

            <?php foreach ($summaryCards as $card): ?>

                <div class="summary-contribution">

                    <div class="summary-contribution-head">
                        <span class="summary-contribution-icon"><?php echo $card["icon"]; ?></span>
                        <h3><?php echo $card["label"]; ?></h3>
                        <div class="summary-contribution-value">
                            <?php if ($card["value"] !== null): ?>
                                <?php echo psMoney($card["value"]) . $card["suffix"]; ?>
                            <?php else: ?>
                                <span class="summary-muted"><?php echo t("summary_not_calculated"); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="summary-contribution-body">

                        <div class="summary-block">
                            <h4><?php echo t("summary_what_you_declared"); ?></h4>
                            <dl class="summary-details">
                                <?php foreach ($card["declared"] as $item): ?>
                                    <dt><?php echo $item[0]; ?></dt>
                                    <dd><?php echo $item[1]; ?></dd>
                                <?php endforeach; ?>
                            </dl>

                            <?php if ($card["type"] === "land" && $landForMap !== null): ?>
                                <div id="landSummaryMap" class="summary-land-map"></div>
                            <?php endif; ?>
                        </div>

                        <div class="summary-block summary-calc">
                            <h4><?php echo t("summary_how_it_was_calculated"); ?></h4>

                            <?php if (!empty($card["steps"])): ?>
                                <div class="summary-steps">
                                    <?php foreach ($card["steps"] as $step): ?>
                                        <div class="summary-step"><?php echo $step; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="summary-muted">
                                    <?php echo t("summary_calculation_details_are_not_available_for"); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($card["note"] !== ""): ?>
                                <p class="summary-note"><?php echo $card["note"]; ?></p>
                            <?php endif; ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <!-- DESCRIPTION -->
    <div class="summary-description">
        <h4><?php echo t("summary_short_description"); ?></h4>
        <p>
            <?php echo trim((string) ($profile["description"] ?? "")) !== ""
                ? nl2br(safe($profile["description"]))
                : '<span class="summary-muted">' . t("summary_no_description_added_yet") . "</span>"; ?>
        </p>
    </div>

    <div class="summary-footer">
        <a href="matches.php" class="summary-matches-link">
            <i data-lucide="zap"></i> <?php echo t("summary_find_my_energy_communities"); ?> <i data-lucide="arrow-right"></i>
        </a>
    </div>

</div>

<?php if ($landForMap !== null): ?>
<script>
/* Small read-only map with the saved land location */
document.addEventListener("DOMContentLoaded", function () {
    const el = document.getElementById("landSummaryMap");
    if (!el || typeof L === "undefined") return;

    const point = [<?php echo json_encode($landForMap["lat"]); ?>, <?php echo json_encode($landForMap["lon"]); ?>];

    const summaryMap = L.map(el, {
        zoomControl: false,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
        touchZoom: false
    }).setView(point, 13);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(summaryMap);

    L.marker(point).addTo(summaryMap);
});
</script>
<?php endif; ?>
