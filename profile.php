<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
include "db.php";
include "lang.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
$user_id = (int) $_SESSION["user_id"];

/* =========================================
   HELPER
========================================= */

function safe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================
   DEFAULT PROFILE
========================================= */
$profileExists = false;
$contributionRows = [];
$profile = [
    "location" => "",
    "description" => "",
    "minimum_share" => ""
];

/* =========================================
   LOAD ENERGY PROFILE
========================================= */
$stmt = $conn->prepare("
    SELECT
        location,
        description,
        minimum_share
    FROM energy_profiles
    WHERE user_id = ?
    LIMIT 1
");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $profile = $result->fetch_assoc();
    $profileExists = true;
}
$stmt->close();

/* =========================================
   DEFAULT CONTRIBUTIONS
========================================= */
$contributions = [
    "capital" => [
        "selected" => false,
        "capital_amount" => ""
    ],
    "land" => [
        "selected" => false,
        "land_area" => "",
        "land_location" => "",
        "latitude" => "",
        "longitude" => ""
    ],
    "manual_labor" => [
        "selected" => false,
        "manual_hours" => ""
    ],
    "skilled_labor" => [
        "selected" => false,
        "specialty" => "",
        "skilled_hours" => ""
    ],
    "equipment" => [
        "selected" => false,

        /* One entry per declared equipment group (see "+ Add equipment") */
        "rows" => []
    ]
];

/* =========================================
   LOAD CONTRIBUTIONS
========================================= */
$stmt = $conn->prepare("
    SELECT *
    FROM user_contributions
    WHERE user_id = ?
    ORDER BY id ASC
");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    /* Raw rows are kept for the read-only profile summary */
    $contributionRows[] = $row;
    $type = $row["contribution_type"];
    if (!isset($contributions[$type])) {
        continue;
    }
    $contributions[$type]["selected"] = true;
    if ($type === "capital") {
        $contributions[$type]["capital_amount"] =
            $row["capital_amount"] ?? "";
    } elseif ($type === "land") {
        $contributions[$type]["land_area"] =
            $row["land_area"] ?? "";
        $contributions[$type]["land_location"] =
            $row["land_location"] ?? "";
        $contributions[$type]["latitude"] =
            $row["latitude"] ?? "";
        $contributions[$type]["longitude"] =
            $row["longitude"] ?? "";
        $contributions[$type]["solar_irradiation"] = $row["solar_irradiation"] ?? null;
        $contributions[$type]["solar_score"] = $row["solar_score"] ?? null;
        $contributions[$type]["location_factor"] = $row["location_factor"] ?? null;
        $contributions[$type]["estimated_value"] = $row["estimated_value"] ?? null;
    } elseif ($type === "manual_labor") {
        $contributions[$type]["manual_hours"] =
            $row["manual_hours"] ?? "";
    } elseif ($type === "skilled_labor") {
        $contributions[$type]["specialty"] =
            $row["specialty"] ?? "";
        $contributions[$type]["skilled_hours"] =
            $row["skilled_hours"] ?? "";
    } elseif ($type === "equipment") {
        /* Show the capacity in the unit the form uses for this type */
        $eqType = (string) ($row["equipment_type"] ?? "");
        $eqCapacity = (float) ($row["equipment_capacity"] ?? 0);
        $eqUnit = strtolower((string) ($row["equipment_capacity_unit"] ?? ""));
        if ($eqType === "pv_panels" && in_array($eqUnit, ["kw", "kwp"], true)) {
            $eqCapacity *= 1000;
        } elseif ($eqType === "electrical_equipment" && in_array($eqUnit, ["w", "wp"], true)) {
            $eqCapacity /= 1000;
        } elseif ($eqType === "battery_storage" && $eqUnit === "wh") {
            $eqCapacity /= 1000;
        }
        $contributions[$type]["rows"][] = [
            "type" => $eqType,
            "quantity" => $row["equipment_quantity"] ?? "",
            "capacity" => $eqCapacity > 0 ? rtrim(rtrim(number_format($eqCapacity, 3, ".", ""), "0"), ".") : "",
            "age" => isset($row["equipment_age"]) ? rtrim(rtrim((string) $row["equipment_age"], "0"), ".") : "",
            "condition" => $row["equipment_condition"] ?? "",
            "battery_chemistry" => $row["battery_chemistry"] ?? ""
        ];
    }
}
$stmt->close();

/* =========================================
   SUMMARY OR EDIT MODE
   - Saved profile  -> show the summary first
   - No profile yet -> open the form directly
   - ?edit=1        -> open the form
========================================= */
$hasSavedProfile = $profileExists || !empty($contributionRows);
$startInEditMode = !$hasSavedProfile || isset($_GET["edit"]);

/* =========================================
   PROFILE LOCK
   Locked while the user is an accepted member of an active
   community (see save_profile.php). Unlocks when they leave.
========================================= */
if (!defined("ENERGYMATCH_ALGORITHM_LIBRARY_ONLY")) {
    define("ENERGYMATCH_ALGORITHM_LIBRARY_ONLY", true);
}
require_once __DIR__ . "/community_algorithm.php";

/* Valuation constants (e.g. MAX_LABOR_HOURS for the form) */
require_once __DIR__ . "/contribution_valuation.php";
require_once __DIR__ . "/account_rules.php";
$lockedCommunityId = emUserActiveCommunityId($conn, $user_id);
$profileLocked = $lockedCommunityId !== null;
if ($profileLocked && $hasSavedProfile) {
    $startInEditMode = false;
}
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <link
        rel="stylesheet"
        href="css/profile.css?v=<?php echo ASSET_VERSION; ?>"
    >
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="css/land-map.css?v=<?php echo ASSET_VERSION; ?>">
    <link
        rel="stylesheet"
        href="css/chatbot.css?v=<?php echo ASSET_VERSION; ?>"
    >
    <title>
        EnergyMatch - <?php echo t("profile"); ?>
    </title>
</head>
<body>

<section class="hero-section">
    <?php include "navbar.php"; ?>
    <div class="ui-page profile-container">
        <div
            class="profile-card <?php echo $startInEditMode ? "" : "summary-mode"; ?>"
            id="profileCard"
        >
            <h1 class="editor-only">
                <?php echo t("profile_page_title"); ?>
            </h1>
            <p class="subtitle editor-only">
                <?php echo t("profile_page_subtitle"); ?>
            </p>

            <!-- LABOUR HOURS LIMIT ERROR -->
            <?php if (($_GET["error"] ?? "") === "labor_hours_limit"): ?>
                <div class="profile-lock-notice">
                    <span class="profile-lock-icon"><i data-lucide="triangle-alert"></i></span>
                    <div>
                        <strong><?php echo t("labor_hours_limit_title"); ?></strong>
                        <p><?php echo t("labor_hours_limit_text"); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- EQUIPMENT ERRORS -->
            <?php if (in_array($_GET["error"] ?? "", ["equipment_incomplete", "equipment_too_many", "invalid_location", "land_location_missing", "land_value_error", "profile_save_error"], true)): ?>
                <div class="profile-lock-notice">
                    <span class="profile-lock-icon"><i data-lucide="triangle-alert"></i></span>
                    <div>
                        <strong><?php echo t($_GET["error"] . "_title"); ?></strong>
                        <p><?php echo t($_GET["error"] . "_text"); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SAVED MESSAGE -->
            <?php
            if (
                isset($_GET["saved"])
                &&
                $_GET["saved"] === "1"
            ):
            ?>
                <div class="success-message">
                    <span class="success-icon"><i data-lucide="check"></i></span>
                    <div>
                        <strong>
                            <?php echo t("profile_updated"); ?>
                        </strong>
                        <p>
                            <?php echo t("profile_saved_info"); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CLEARED MESSAGE -->
            <?php
            if (
                isset($_GET["cleared"])
                &&
                $_GET["cleared"] === "1"
            ):
            ?>
                <div class="success-message">
                    <span class="success-icon"><i data-lucide="check"></i></span>
                    <div>
                        <strong>
                            <?php echo t("profile_cleared"); ?>
                        </strong>
                        <p>
                            <?php echo t("profile_cleared_info"); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- PROFILE LOCKED (active community) -->
            <?php if ($profileLocked): ?>
                <div class="profile-lock-notice">
                    <span class="profile-lock-icon"><i data-lucide="lock"></i></span>
                    <div>
                        <strong><?php echo t("profile_locked_title"); ?></strong>
                        <p>
                            <?php echo t(isset($_GET["locked"]) ? "profile_locked_attempt" : "profile_locked_text"); ?>
                        </p>
                        <a href="community.php?id=<?php echo (int) $lockedCommunityId; ?>">
                            <?php echo t("profile_locked_go_community"); ?> <i data-lucide="arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- READ-ONLY PROFILE SUMMARY -->
            <?php if ($hasSavedProfile): ?>
                <?php include "profile_summary.php"; ?>
                <a
                    href="profile.php"
                    class="back-to-summary editor-only"
                    id="closeProfileEditor"
                >
                    <i data-lucide="arrow-left"></i> <?php echo t("summary_back_to_summary"); ?>
                </a>
            <?php endif; ?>

            <form
                action="save_profile.php"
                method="POST"
                id="energyProfileForm"
                class="editor-only"
            >
                <?php echo csrfField(); ?>

                <!-- CONTRIBUTION TYPES -->
                <div class="section">
                    <h2>
                        <?php echo t("what_can_contribute"); ?>
                    </h2>
                    <p class="section-description">
                        <?php echo t("contribution_description"); ?>
                    </p>
                    <div class="cards contribution-cards">

                        <!-- CAPITAL -->
                        <label class="option-card">
                            <input
                                type="checkbox"
                                name="contribution_type[]"
                                value="capital"
                                data-target="capital-fields"
                                <?php
                                echo $contributions["capital"]["selected"]
                                    ? "checked"
                                    : "";
                                ?>
                            >
                            <span>
                                <i data-lucide="coins"></i> <?php echo t("capital_money"); ?>
                            </span>
                        </label>

                        <!-- LAND -->
                        <label class="option-card">
                            <input
                                type="checkbox"
                                name="contribution_type[]"
                                value="land"
                                data-target="land-fields"
                                <?php
                                echo $contributions["land"]["selected"]
                                    ? "checked"
                                    : "";
                                ?>
                            >
                            <span>
                                <i data-lucide="land-plot"></i> <?php echo t("land_space"); ?>
                            </span>
                        </label>

                        <!-- MANUAL LABOR -->
                        <label class="option-card">
                            <input
                                type="checkbox"
                                name="contribution_type[]"
                                value="manual_labor"
                                data-target="manual-fields"
                                <?php
                                echo $contributions["manual_labor"]["selected"]
                                    ? "checked"
                                    : "";
                                ?>
                            >
                            <span>
                                <i data-lucide="hard-hat"></i> <?php echo t("manual_labor"); ?>
                            </span>
                        </label>

                        <!-- SKILLED LABOR -->
                        <label class="option-card">
                            <input
                                type="checkbox"
                                name="contribution_type[]"
                                value="skilled_labor"
                                data-target="skilled-fields"
                                <?php
                                echo $contributions["skilled_labor"]["selected"]
                                    ? "checked"
                                    : "";
                                ?>
                            >
                            <span>
                                <i data-lucide="wrench"></i> <?php echo t("skilled_labor"); ?>
                            </span>
                        </label>

                        <!-- EQUIPMENT -->
                        <label class="option-card">
                            <input
                                type="checkbox"
                                name="contribution_type[]"
                                value="equipment"
                                data-target="equipment-fields"
                                <?php
                                echo $contributions["equipment"]["selected"]
                                    ? "checked"
                                    : "";
                                ?>
                            >
                            <span>
                                <i data-lucide="plug-zap"></i> <?php echo t("equipment_infrastructure"); ?>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- CAPITAL DETAILS -->
                <div
                    class="contribution-details"
                    id="capital-fields"
                >
                    <div class="contribution-header">
                        <div class="contribution-icon">
                            <i data-lucide="coins"></i>
                        </div>
                        <div>
                            <h3>
                                <?php echo t("capital_contribution"); ?>
                            </h3>
                            <p>
                                <?php echo t("capital_contribution_description"); ?>
                            </p>
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="capital_amount">
                            <?php echo t("available_capital"); ?>
                        </label>
                        <input
                            type="number"
                            id="capital_amount"
                            name="capital_amount"
                            min="0"
                            step="0.01"
                            placeholder="5000"
                            value="<?php
                            echo safe(
                                $contributions["capital"]["capital_amount"]
                            );
                            ?>"
                        >
                    </div>
                </div>

                <!-- LAND DETAILS -->
                <div
                    class="contribution-details"
                    id="land-fields"
                >
                    <div class="contribution-header">
                        <div class="contribution-icon">
                            <i data-lucide="land-plot"></i>
                        </div>
                        <div>
                            <h3>
                                <?php echo t("land_contribution"); ?>
                            </h3>
                            <p>
                                <?php echo t("land_contribution_description"); ?>
                            </p>
                        </div>
                    </div>
                    <div class="input-grid">
                        <div class="input-group">
                            <label for="land_area">
                                <?php echo t("land_area"); ?>
                            </label>
                            <input
                                type="number"
                                id="land_area"
                                name="land_area"
                                min="0"
                                step="0.01"
                                placeholder="5"
                                value="<?php
                                echo safe(
                                    $contributions["land"]["land_area"]
                                );
                                ?>"
                            >
                        </div>
                        <div class="input-group">
                            <label for="land_location">
                                <?php echo t("land_location"); ?>
                            </label>
                            <input
                                type="text"
                                id="land_location"
                                name="land_location"
                                placeholder="<?php echo t("land_location_placeholder"); ?>"
                                value="<?php
                                echo safe(
                                    $contributions["land"]["land_location"]
                                );
                                ?>"
                            >
                        </div>
                        <div class="input-group">
                            <label for="latitude">
                                <?php echo t("latitude"); ?>
                            </label>
                            <input
                                type="number"
                                id="latitude"
                                name="latitude"
                                step="0.0000001"
                                min="-90"
                                max="90"
                                placeholder="37.8200"
                                value="<?php
                                echo safe(
                                    $contributions["land"]["latitude"]
                                );
                                ?>"
                            >
                        </div>
                        <div class="input-group">
                            <label for="longitude">
                                <?php echo t("longitude"); ?>
                            </label>
                            <input
                                type="number"
                                id="longitude"
                                name="longitude"
                                step="0.0000001"
                                min="-180"
                                max="180"
                                placeholder="22.6600"
                                value="<?php
                                echo safe(
                                    $contributions["land"]["longitude"]
                                );
                                ?>"
                            >
                        </div>
                    </div>
                    <div class="land-map-section">
                        <div class="land-map-heading">
                            <div>
                                <h4><?php echo t("land_location"); ?></h4>
                                <p><?php echo t("land_map_help"); ?></p>
                            </div>
                        </div>
                        <div
                            id="landMap"
                            class="land-map"
                            aria-label="<?php echo safe(t("land_location")); ?>"
                            data-lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>"
                            data-msg-searching="<?php echo safe(t("land_map_searching")); ?>"
                            data-msg-found="<?php echo safe(t("land_map_found")); ?>"
                            data-msg-not-found="<?php echo safe(t("land_map_not_found")); ?>"
                            data-msg-address-filled="<?php echo safe(t("land_map_address_filled")); ?>"
                            data-msg-error="<?php echo safe(t("land_map_error")); ?>"
                        ></div>
                        <div class="map-selected-point" id="mapSelectedPoint"></div>
                        <div class="map-geocode-status" id="mapGeocodeStatus" aria-live="polite"></div>
                    </div>

                    <?php if ($contributions["land"]["selected"]): ?>
                    <div class="land-energy-results">
                        <h3><?php echo ($_SESSION["language"] ?? "en") === "gr" ? "Ηλιακή αξιολόγηση γης" : "Land Solar Assessment"; ?></h3>

                        <?php if ($contributions["land"]["solar_score"] !== null): ?>
                        <p><strong>Solar Score:</strong> <?php echo number_format((float) $contributions["land"]["solar_score"], 2, ",", "."); ?>%</p>
                        <?php endif; ?>

                        <?php if ($contributions["land"]["solar_irradiation"] !== null): ?>
                        <p><strong><?php echo ($_SESSION["language"] ?? "en") === "gr" ? "Ετήσια ηλιακή ακτινοβολία" : "Annual Solar Irradiation"; ?>:</strong> <?php echo number_format((float) $contributions["land"]["solar_irradiation"], 2, ",", "."); ?> kWh/m²/<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "έτος" : "year"; ?></p>
                        <?php endif; ?>

                        <?php if ($contributions["land"]["estimated_value"] !== null): ?>
                        <p><strong><?php echo t("land_value_title"); ?>:</strong> <?php echo number_format((float) $contributions["land"]["estimated_value"], 2, ",", "."); ?> €<?php if ($contributions["land"]["location_factor"] !== null): ?> <small>(f = <?php echo number_format((float) $contributions["land"]["location_factor"], 3, ",", "."); ?>)</small><?php endif; ?></p>
                        <small><?php echo t("land_value_note_short"); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- MANUAL LABOR -->
                <div
                    class="contribution-details"
                    id="manual-fields"
                >
                    <div class="contribution-header">
                        <div class="contribution-icon">
                            <i data-lucide="hard-hat"></i>
                        </div>
                        <div>
                            <h3>
                                <?php echo t("manual_labor"); ?>
                            </h3>
                            <p>
                                <?php echo t("manual_labor_description"); ?>
                            </p>
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="manual_hours">
                            <?php echo t("manual_hours"); ?>
                        </label>
                        <input
                            type="number"
                            id="manual_hours"
                            name="manual_hours"
                            min="0"
                            max="<?php echo MAX_LABOR_HOURS; ?>"
                            step="0.5"
                            placeholder="100"
                            value="<?php
                            echo safe(
                                $contributions["manual_labor"]["manual_hours"]
                            );
                            ?>"
                        >
                        <small class="field-help">
                            <?php echo t("labor_hours_limit_help"); ?>
                        </small>
                    </div>
                </div>

                <!-- SKILLED LABOR -->
                <div
                    class="contribution-details"
                    id="skilled-fields"
                >
                    <div class="contribution-header">
                        <div class="contribution-icon">
                            <i data-lucide="wrench"></i>
                        </div>
                        <div>
                            <h3>
                                <?php echo t("skilled_labor"); ?>
                            </h3>
                            <p>
                                <?php echo t("skilled_labor_description"); ?>
                            </p>
                        </div>
                    </div>
                    <div class="input-grid">
                        <div class="input-group">
                            <label for="specialty">
                                <?php echo t("specialty"); ?>
                            </label>
                            <select
                                id="specialty"
                                name="specialty"
                            >
                                <option value="">
                                    <?php echo t("select_specialty"); ?>
                                </option>
                                <option
                                    value="Electrician"
                                    <?php
                                    echo $contributions["skilled_labor"]["specialty"] === "Electrician"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    <?php echo t("electrician"); ?>
                                </option>
                                <option
                                    value="PV Technician"
                                    <?php
                                    echo $contributions["skilled_labor"]["specialty"] === "PV Technician"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    <?php echo t("pv_technician"); ?>
                                </option>
                                <option
                                    value="Engineer"
                                    <?php
                                    echo $contributions["skilled_labor"]["specialty"] === "Engineer"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    <?php echo t("engineer"); ?>
                                </option>
                                <option
                                    value="Installer"
                                    <?php
                                    echo $contributions["skilled_labor"]["specialty"] === "Installer"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    <?php echo t("installer"); ?>
                                </option>
                                <option
                                    value="Other"
                                    <?php
                                    echo $contributions["skilled_labor"]["specialty"] === "Other"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    <?php echo t("other_specialty"); ?>
                                </option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="skilled_hours">
                                <?php echo t("skilled_hours"); ?>
                            </label>
                            <input
                                type="number"
                                id="skilled_hours"
                                name="skilled_hours"
                                min="0"
                                max="<?php echo MAX_LABOR_HOURS; ?>"
                                step="0.5"
                                placeholder="50"
                                value="<?php
                                echo safe(
                                    $contributions["skilled_labor"]["skilled_hours"]
                                );
                                ?>"
                            >
                            <small class="field-help">
                                <?php echo t("skilled_hours_help"); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- EQUIPMENT -->
                <?php

                /*
                 * Equipment is declared in groups ("+ Add equipment"):
                 * e.g. "3 panels 450 W, 2 years" and "2 panels 300 W, 8 years".
                 * Each group becomes one row in user_contributions.
                 * The unit is fixed by the type (EQUIPMENT_UNITS).
                 */
                $equipmentRows = $contributions["equipment"]["rows"];
                if (count($equipmentRows) === 0) {
                    $equipmentRows[] = [];
                }

                function renderEquipmentRow($index, array $row)
                {
                    $type = (string) ($row["type"] ?? "");
                    $condition = (string) ($row["condition"] ?? "");
                    $chemistry = (string) ($row["battery_chemistry"] ?? "");
                    $name = "equipment[" . $index . "]";
                    $unit = EQUIPMENT_UNITS[$type] ?? "";
                    ?>
                    <div class="equipment-row" data-index="<?php echo safe($index); ?>">
                        <div class="equipment-row-header">
                            <strong class="equipment-row-title">
                                <?php echo t("equipment_group"); ?>
                                <span class="equipment-row-number"></span>
                            </strong>
                            <button type="button" class="equipment-remove-btn">
                                <i data-lucide="x"></i> <?php echo t("remove_equipment"); ?>
                            </button>
                        </div>
                        <div class="input-grid">
                            <div class="input-group">
                                <label><?php echo t("equipment_type"); ?></label>
                                <select name="<?php echo $name; ?>[type]" class="equipment-type-select">
                                    <option value=""><?php echo t("select_equipment"); ?></option>

                                    <?php foreach (["pv_panels", "battery_storage", "electrical_equipment"] as $option): ?>
                                        <option
                                            value="<?php echo $option; ?>"
                                            data-unit="<?php echo safe(EQUIPMENT_UNITS[$option]); ?>"
                                            data-capacity-label="<?php echo safe(t("equipment_capacity_" . $option)); ?>"
                                            <?php echo $type === $option ? "selected" : ""; ?>
                                        >
                                            <?php echo t($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label><?php echo t("quantity"); ?></label>
                                <input
                                    type="number"
                                    name="<?php echo $name; ?>[quantity]"
                                    min="1"
                                    step="1"
                                    placeholder="5"
                                    value="<?php echo safe($row["quantity"] ?? ""); ?>"
                                >
                            </div>
                            <div class="input-group">
                                <label>
                                    <span class="equipment-capacity-label"><?php
                                        echo $type !== ""
                                            ? t("equipment_capacity_" . $type)
                                            : t("equipment_capacity_per_unit");
                                    ?></span>
                                    <span class="equipment-unit"><?php echo $unit !== "" ? "(" . safe($unit) . ")" : ""; ?></span>
                                </label>
                                <input
                                    type="number"
                                    name="<?php echo $name; ?>[capacity]"
                                    min="0"
                                    step="0.01"
                                    placeholder="450"
                                    value="<?php echo safe($row["capacity"] ?? ""); ?>"
                                >
                            </div>
                            <div class="input-group">
                                <label><?php echo t("equipment_age"); ?></label>
                                <input
                                    type="number"
                                    name="<?php echo $name; ?>[age]"
                                    min="0"
                                    step="0.1"
                                    placeholder="2"
                                    value="<?php echo safe($row["age"] ?? ""); ?>"
                                >
                            </div>
                            <div class="input-group">
                                <label><?php echo t("condition"); ?></label>
                                <select name="<?php echo $name; ?>[condition]">
                                    <option value=""><?php echo t("select_condition"); ?></option>

                                    <?php foreach (["excellent", "good", "fair", "poor"] as $option): ?>
                                        <option value="<?php echo $option; ?>" <?php echo $condition === $option ? "selected" : ""; ?>>
                                            <?php echo t("condition_" . $option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div
                                class="input-group battery-specific-field"
                                <?php echo $type === "battery_storage" ? "" : 'style="display: none;"'; ?>
                            >
                                <label><?php echo t("battery_chemistry_optional"); ?></label>
                                <select name="<?php echo $name; ?>[battery_chemistry]">
                                    <option value=""><?php echo t("battery_chemistry_unknown"); ?></option>
                                    <option value="lfp" <?php echo $chemistry === "lfp" ? "selected" : ""; ?>>LFP</option>
                                    <option value="nmc" <?php echo $chemistry === "nmc" ? "selected" : ""; ?>>NMC</option>
                                    <option value="lead_acid" <?php echo $chemistry === "lead_acid" ? "selected" : ""; ?>><?php echo t("summary_lead_acid"); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <div
                    class="contribution-details"
                    id="equipment-fields"
                >
                    <div class="contribution-header">
                        <div class="contribution-icon">
                            <i data-lucide="plug-zap"></i>
                        </div>
                        <div>
                            <h3>
                                <?php echo t("equipment_infrastructure"); ?>
                            </h3>
                            <p>
                                <?php echo t("equipment_contribution_description"); ?>
                            </p>
                        </div>
                    </div>
                    <p class="field-help equipment-help">
                        <?php echo t("equipment_groups_help"); ?>
                    </p>
                    <div id="equipment-rows" data-max-rows="<?php echo MAX_EQUIPMENT_ROWS; ?>">

                        <?php foreach (array_values($equipmentRows) as $index => $row): ?>
                            <?php renderEquipmentRow($index, $row); ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty row, copied by "+ Add equipment" -->
                    <template id="equipment-row-template">
                        <?php renderEquipmentRow("__INDEX__", []); ?>
                    </template>
                    <button type="button" id="add-equipment-btn" class="equipment-add-btn">
                        <i data-lucide="plus"></i> <?php echo t("add_equipment"); ?>
                    </button>
                    <small class="field-help equipment-max-help">
                        <?php echo t("equipment_max_rows_help"); ?>
                    </small>
                </div>

                <!-- PARTICIPATION PREFERENCES -->
                <div class="section additional-section">
                    <h2>
                        <?php echo t("participation_preferences"); ?>
                    </h2>
                    <p class="section-description">
                        <?php echo t("participation_description"); ?>
                    </p>
                    <div class="input-grid">
                        <div class="input-group">
                            <label for="minimum_share">
                                <?php echo t("minimum_participation_share"); ?>
                            </label>
                            <input
                                type="number"
                                id="minimum_share"
                                name="minimum_share"
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="20"
                                value="<?php echo safe($profile["minimum_share"]); ?>"
                                required
                            >
                            <small class="field-help">
                                <?php echo t("fair_share_help"); ?>
                            </small>
                        </div>
                        <div class="input-group">
                            <label for="location">
                                <?php echo t("location_region"); ?>
                            </label>
                            <input
                                type="text"
                                id="location"
                                name="location"
                                placeholder="<?php echo t("location_placeholder"); ?>"
                                value="<?php echo safe($profile["location"]); ?>"
                                pattern="<?php echo EM_PLACE_PATTERN; ?>"
                                data-pattern-message="<?php echo htmlspecialchars(t("register_error_place"), ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="description">
                            <?php echo t("short_description"); ?>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            placeholder="<?php echo t("description_placeholder"); ?>"
                        ><?php echo safe($profile["description"]); ?></textarea>
                    </div>
                </div>

                <!-- BUTTONS -->
                <div class="profile-actions">
                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="save-btn"
                    >
                        <i data-lucide="save"></i> <?php echo t("save_profile"); ?>
                    </button>
                    <button
                        type="submit"
                        name="action"
                        value="clear"
                        class="clear-btn"
                        formnovalidate
                        onclick="return confirm('<?php echo safe(t("clear_profile_confirm")); ?>');"
                    >
                        <i data-lucide="trash-2"></i> <?php echo t("clear_profile"); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php include "chatbot.php"; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<script src="js/land-map.js?v=<?php echo ASSET_VERSION; ?>"></script>

<script src="js/chatbot.js?v=<?php echo ASSET_VERSION; ?>"></script>

<script>

/* =========================================
   SWITCH BETWEEN SUMMARY AND EDIT FORM
   Links also work without JavaScript
   (profile.php?edit=1 / profile.php).
========================================= */
document.addEventListener("DOMContentLoaded", function () {
    const profileCard = document.getElementById("profileCard");
    const openEditor = document.getElementById("openProfileEditor");
    const closeEditor = document.getElementById("closeProfileEditor");
    if (!profileCard) {
        return;
    }

    function scrollToCardTop() {
        profileCard.scrollIntoView({ behavior: "smooth", block: "start" });
    }
    if (openEditor) {
        openEditor.addEventListener("click", function (event) {
            event.preventDefault();
            profileCard.classList.remove("summary-mode");

            /* The land map was hidden, so Leaflet must re-measure it. */
            setTimeout(function () {
                window.dispatchEvent(new Event("resize"));
            }, 50);
            scrollToCardTop();
        });
    }
    if (closeEditor) {
        closeEditor.addEventListener("click", function (event) {
            event.preventDefault();
            profileCard.classList.add("summary-mode");
            setTimeout(function () {
                window.dispatchEvent(new Event("resize"));
            }, 50);
            scrollToCardTop();
        });
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const contributionCheckboxes = document.querySelectorAll(
        'input[name="contribution_type[]"]'
    );

    function updateContributionSections() {
        contributionCheckboxes.forEach(function (checkbox) {
            const section =
                document.getElementById(checkbox.dataset.target);
            if (!section) {
                return;
            }
            section.classList.toggle(
                "active",
                checkbox.checked
            );
        });
    }
    contributionCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener(
            "change",
            updateContributionSections
        );
    });
    updateContributionSections();

    /* =========================================
       EQUIPMENT ROWS ("+ Add equipment")
    ========================================= */
    const rowsBox = document.getElementById("equipment-rows");
    const rowTemplate = document.getElementById("equipment-row-template");
    const addButton = document.getElementById("add-equipment-btn");
    if (!rowsBox || !rowTemplate || !addButton) {
        return;
    }
    const maxRows = parseInt(rowsBox.dataset.maxRows, 10) || 10;
    let nextIndex = rowsBox.querySelectorAll(".equipment-row").length;

    /* Type changed -> correct unit, label and battery field */
    function updateRowType(row) {
        const select = row.querySelector(".equipment-type-select");
        const option = select.options[select.selectedIndex];
        const unit = option ? (option.dataset.unit || "") : "";
        const label = option && option.dataset.capacityLabel
            ? option.dataset.capacityLabel
            : row.querySelector(".equipment-capacity-label").dataset.defaultLabel;
        row.querySelector(".equipment-capacity-label").textContent = label;
        row.querySelector(".equipment-unit").textContent = unit ? "(" + unit + ")" : "";
        row.querySelector(".battery-specific-field").style.display =
            select.value === "battery_storage" ? "" : "none";
    }

    /* Numbers 1, 2, 3 ... and hide "remove" when only one row is left */
    function refreshRows() {
        const rows = rowsBox.querySelectorAll(".equipment-row");
        rows.forEach(function (row, position) {
            row.querySelector(".equipment-row-number").textContent = "#" + (position + 1);
            row.querySelector(".equipment-remove-btn").style.display =
                rows.length > 1 ? "" : "none";
        });
        addButton.disabled = rows.length >= maxRows;
    }

    function setupRow(row) {
        const labelSpan = row.querySelector(".equipment-capacity-label");
        if (!labelSpan.dataset.defaultLabel) {
            labelSpan.dataset.defaultLabel = <?php echo json_encode(t("equipment_capacity_per_unit")); ?>;
        }
        row.querySelector(".equipment-type-select").addEventListener("change", function () {
            updateRowType(row);
        });
        row.querySelector(".equipment-remove-btn").addEventListener("click", function () {
            row.remove();
            refreshRows();
        });
    }
    rowsBox.querySelectorAll(".equipment-row").forEach(setupRow);
    addButton.addEventListener("click", function () {
        if (rowsBox.querySelectorAll(".equipment-row").length >= maxRows) {
            return;
        }
        const html = rowTemplate.innerHTML.replaceAll("__INDEX__", String(nextIndex));
        nextIndex++;
        const holder = document.createElement("div");
        holder.innerHTML = html.trim();
        const row = holder.firstElementChild;
        rowsBox.appendChild(row);
        setupRow(row);
        refreshRows();

        /* draw the icons of the new row */
        if (window.emRefreshIcons) {
            window.emRefreshIcons();
        }
        const firstField = row.querySelector("select");
        if (firstField) {
            firstField.focus();
        }
    });
    refreshRows();
});
</script>
</body>
</html>
