<?php

session_start();
require_once __DIR__ . "/remember_me.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();

require_once "db.php";

require_once "lang.php";

require_once __DIR__ . "/contribution_valuation.php";

require_once __DIR__ . "/account_rules.php";

/* A mistake in what the user filled in: the profile page shows a message
   (profile.php?error=<key>) instead of a database error page. */
class EmProfileInputError extends Exception
{
}

require_once __DIR__ . "/pvgis_service.php";


if (!isset($_SESSION["user_id"])) {

    header("Location: sign_in.php");

    exit;

}

$user_id = (int) $_SESSION["user_id"];

/* =========================================
   PROFILE LOCK
   While the user is an accepted member of an ACTIVE community,
   the profile cannot be saved or cleared, so the percentages the
   members agreed on stay the same. Leaving the community unlocks it.
========================================= */

define("ENERGYMATCH_ALGORITHM_LIBRARY_ONLY", true);
require_once __DIR__ . "/community_algorithm.php";

if (emUserActiveCommunityId($conn, $user_id) !== null) {
    header("Location: profile.php?locked=1");
    exit;
}

$action = $_POST["action"] ?? "save";

/* =========================================
   LABOUR HOURS LIMIT (see MAX_LABOR_HOURS)
   Checked before anything is saved.
========================================= */

if (
    $action !== "clear"
    && (
        (
            in_array("manual_labor", (array) ($_POST["contribution_type"] ?? []), true)
            && (float) ($_POST["manual_hours"] ?? 0) > MAX_LABOR_HOURS
        )
        || (
            in_array("skilled_labor", (array) ($_POST["contribution_type"] ?? []), true)
            && (float) ($_POST["skilled_hours"] ?? 0) > MAX_LABOR_HOURS
        )
    )
) {
    header("Location: profile.php?edit=1&error=labor_hours_limit");
    exit;
}

/* =========================================
   LOCATION / REGION: letters only (account_rules.php)
   Empty is allowed (the field is optional).
========================================= */

$postedLocation = trim((string) ($_POST["location"] ?? ""));

if ($action !== "clear" && $postedLocation !== "" && !emPlaceIsValid($postedLocation)) {
    header("Location: profile.php?edit=1&error=invalid_location");
    exit;
}

/* =========================================
   EQUIPMENT ROWS ("+ Add equipment")
   The form sends equipment[n][type], [quantity],
   [capacity], [age], [condition], [battery_chemistry].
   - a completely empty row is ignored
   - a half-filled row stops the save with a message
   The unit is fixed by the type (EQUIPMENT_UNITS).
========================================= */

$equipment_rows = [];

if (
    $action !== "clear"
    && in_array("equipment", (array) ($_POST["contribution_type"] ?? []), true)
) {
    $postedRows = is_array($_POST["equipment"] ?? null) ? $_POST["equipment"] : [];
    $equipmentError = null;

    foreach ($postedRows as $postedRow) {
        if (!is_array($postedRow)) {
            continue;
        }

        $row = [
            "type" => strtolower(trim((string) ($postedRow["type"] ?? ""))),
            "quantity" => trim((string) ($postedRow["quantity"] ?? "")),
            "capacity" => trim((string) ($postedRow["capacity"] ?? "")),
            "age" => trim((string) ($postedRow["age"] ?? "")),
            "condition" => strtolower(trim((string) ($postedRow["condition"] ?? ""))),
            "battery_chemistry" => strtolower(trim((string) ($postedRow["battery_chemistry"] ?? ""))),
        ];

        /* Empty row -> ignore */
        if (
            $row["type"] === "" && $row["quantity"] === "" && $row["capacity"] === ""
            && $row["age"] === "" && $row["condition"] === ""
        ) {
            continue;
        }

        $unit = equipmentUnitForType($row["type"]);

        if (
            $unit === null
            || (int) $row["quantity"] <= 0
            || (float) $row["capacity"] <= 0
            || $row["age"] === "" || (float) $row["age"] < 0
            || !in_array($row["condition"], ["excellent", "good", "fair", "poor"], true)
        ) {
            $equipmentError = "equipment_incomplete";
            break;
        }

        $equipment_rows[] = [
            "type" => $row["type"],
            "quantity" => (int) $row["quantity"],
            "capacity" => (float) $row["capacity"],
            "unit" => $unit,
            "age" => (float) $row["age"],
            "condition" => $row["condition"],
            "battery_chemistry" => (
                $row["type"] === "battery_storage"
                && in_array($row["battery_chemistry"], ["lfp", "nmc", "lead_acid"], true)
            ) ? $row["battery_chemistry"] : null,
        ];
    }

    if ($equipmentError === null && count($equipment_rows) > MAX_EQUIPMENT_ROWS) {
        $equipmentError = "equipment_too_many";
    }

    if ($equipmentError !== null) {
        header("Location: profile.php?edit=1&error=" . $equipmentError);
        exit;
    }
}

/* =========================================

   CLEAR PROFILE

========================================= */

if ($action === "clear") {

    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare("

            DELETE FROM user_contributions

            WHERE user_id = ?

        ");

        if (!$stmt) {

            throw new Exception($conn->error);

        }

        $stmt->bind_param("i", $user_id);

        $stmt->execute();

        $stmt->close();

        $stmt = $conn->prepare("

            UPDATE energy_profiles

            SET

                location = '',

                description = '',

                minimum_share = 0.00

            WHERE user_id = ?

        ");

        if (!$stmt) {

            throw new Exception($conn->error);

        }

        $stmt->bind_param("i", $user_id);

        $stmt->execute();

        $stmt->close();

        $conn->commit();

        header("Location: profile.php?cleared=1");

        exit();

    }

    catch (Throwable $e) {

        $conn->rollback();

        error_log("EnergyMatch save_profile (clear): " . $e->getMessage());

        header("Location: profile.php?edit=1&error=profile_save_error");

        exit();

    }

}

/* =========================================

   GENERAL INFORMATION

========================================= */

$location = trim(

    $_POST["location"] ?? ""

);

$description = trim(

    $_POST["description"] ?? ""

);

$minimum_share =

    isset($_POST["minimum_share"])

        ? (float) $_POST["minimum_share"]

        : 0;

if ($minimum_share < 0) {

    $minimum_share = 0;

}

if ($minimum_share > 100) {

    $minimum_share = 100;

}

/* =========================================

   CONTRIBUTION TYPES

========================================= */

$contribution_types =

    isset($_POST["contribution_type"])

    &&

    is_array($_POST["contribution_type"])

        ? $_POST["contribution_type"]

        : [];

$allowed_types = [

    "capital",

    "land",

    "manual_labor",

    "skilled_labor",

    "equipment"

];

$contribution_types =

    array_values(

        array_intersect(

            $contribution_types,

            $allowed_types

        )

    );

/* =========================================

   CAPITAL

========================================= */

$capital_amount =

    isset($_POST["capital_amount"])

        ? (float) $_POST["capital_amount"]

        : 0;

/* =========================================

   TRANSACTION

========================================= */

$conn->begin_transaction();

try {

    /* =====================================

       CHECK PROFILE

    ===================================== */

    $check = $conn->prepare("

        SELECT id

        FROM energy_profiles

        WHERE user_id = ?

        LIMIT 1

    ");

    if (!$check) {

        throw new Exception($conn->error);

    }

    $check->bind_param(

        "i",

        $user_id

    );

    $check->execute();

    $result = $check->get_result();

    $profileExists =

        $result->num_rows > 0;

    $check->close();

    /* =====================================

       UPDATE PROFILE

    ===================================== */

    if ($profileExists) {

        $stmt = $conn->prepare("

            UPDATE energy_profiles

            SET

                location = ?,

                description = ?,

                minimum_share = ?

            WHERE user_id = ?

        ");

        if (!$stmt) {

            throw new Exception($conn->error);

        }

        $stmt->bind_param(

            "ssdi",

            $location,

            $description,

            $minimum_share,

            $user_id

        );

        $stmt->execute();

        $stmt->close();

    }

    /* =====================================

       INSERT PROFILE

    ===================================== */

    else {

        $stmt = $conn->prepare("

            INSERT INTO energy_profiles

            (

                user_id,

                location,

                description,

                minimum_share

            )

            VALUES (?, ?, ?, ?)

        ");

        if (!$stmt) {

            throw new Exception($conn->error);

        }

        $stmt->bind_param(

            "issd",

            $user_id,

            $location,

            $description,

            $minimum_share

        );

        $stmt->execute();

        $stmt->close();

    }

    /* =====================================

       DELETE PREVIOUS CONTRIBUTIONS

    ===================================== */

    $stmt = $conn->prepare("

        DELETE FROM user_contributions

        WHERE user_id = ?

    ");

    if (!$stmt) {

        throw new Exception($conn->error);

    }

    $stmt->bind_param(

        "i",

        $user_id

    );

    $stmt->execute();

    $stmt->close();

    /* =====================================

       CAPITAL

    ===================================== */

    if (

        in_array(

            "capital",

            $contribution_types,

            true

        )

        &&

        $capital_amount > 0

    ) {

        $estimated_value =

            $capital_amount;

        $valuation_method =

            "Direct monetary contribution";

        $stmt = $conn->prepare("

            INSERT INTO user_contributions

            (

                user_id,

                contribution_type,

                capital_amount,

                estimated_value,

                valuation_method

            )

            VALUES (

                ?,

                'capital',

                ?,

                ?,

                ?

            )

        ");

        if (!$stmt) {

            throw new Exception($conn->error);

        }

        $stmt->bind_param(

            "idds",

            $user_id,

            $capital_amount,

            $estimated_value,

            $valuation_method

        );

        $stmt->execute();

        $stmt->close();

    }

       /* =====================================

       LAND

    ===================================== */

    if (

        in_array(

            "land",

            $contribution_types,

            true

        )

    ) {

        $land_area =

            isset($_POST["land_area"])

                ? (float) $_POST["land_area"]

                : 0;

        $land_location =

            trim(

                $_POST["land_location"] ?? ""

            );

        $latitude =

            (

                isset($_POST["latitude"])

                &&

                $_POST["latitude"] !== ""

            )

                ? (float) $_POST["latitude"]

                : null;

        $longitude =

            (

                isset($_POST["longitude"])

                &&

                $_POST["longitude"] !== ""

            )

                ? (float) $_POST["longitude"]

                : null;

        $solar_irradiation = null;

        $optimal_slope = null;

        $optimal_azimuth = null;

        $solar_score = null;

        $location_factor = null;

        if ($latitude !== null && $longitude !== null) {

            $pvgis = getPvgisSolarData($latitude, $longitude);

            if ($pvgis !== null) {

                $solar_irradiation = $pvgis["annual_irradiation"];

                $optimal_slope = $pvgis["optimal_slope"];

                $optimal_azimuth = $pvgis["optimal_azimuth"];

                /* Solar Score: shown to the user, NOT used in the EUR value */
                $solar_score = $pvgis["solar_score"] ?? null;

            }

        }

        if ($land_area > 0) {

            /* The plot must be placed on the map (needed for PVGIS) */
            if ($latitude === null || $longitude === null) {
                throw new EmProfileInputError("land_location_missing");
            }

            /*
             * LAND VALUE (one-off EUR, see contribution_valuation.php):
             * stremmata x (Eurostat national average / 10) x f
             * f = H_location / H_reference, limited to 0.8 - 1.2.
             * If PVGIS is not available, f = 1.00 (national average price).
             */
            $location_factor = calculateLandLocationFactor($solar_irradiation);

            $estimated_value = calculateLandValue($land_area, $solar_irradiation);

            if ($estimated_value === null) {
                throw new EmProfileInputError("land_value_error");
            }

            $valuation_method = $solar_irradiation !== null
                ? "Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)"
                : "Eurostat national average land price / 10 x stremmata (PVGIS unavailable, f = 1.00)";
            /*

            =========================================

            SAVE LAND CONTRIBUTION

            =========================================

            */

            $stmt = $conn->prepare("

                INSERT INTO user_contributions

                (

                    user_id,

                    contribution_type,

                    land_area,

                    land_location,

                    latitude,

                    longitude,

                    estimated_value,

                    valuation_method,

                    solar_irradiation,

                    optimal_slope,

                    optimal_azimuth,

                    solar_score,

                    location_factor

                )

                VALUES (?, 'land', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

            ");

            if (!$stmt) {

                throw new Exception($conn->error);

            }

            $stmt->bind_param(

                "idsdddsddddd",

                $user_id,

                $land_area,

                $land_location,

                $latitude,

                $longitude,

                $estimated_value,

                $valuation_method,

                $solar_irradiation,

                $optimal_slope,

                $optimal_azimuth,

                $solar_score,

                $location_factor

            );

            $stmt->execute();

            $stmt->close();

        }

    }

    /* =====================================

   MANUAL LABOR

===================================== */

    if (

        in_array(

            "manual_labor",

            $contribution_types,

            true

        )

    ) {

        $manual_hours =

            isset($_POST["manual_hours"])

                ? (float) $_POST["manual_hours"]

                : 0;

        if ($manual_hours > 0) {

            /*

            =========================================

            CALCULATE MANUAL LABOR ECONOMIC VALUE

            =========================================

            */

            $estimated_value =

                calculateManualLaborValue(

                    $manual_hours

                );

            $valuation_method =

                "Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC9 Elementary occupations";

            /*

            =========================================

            SAVE MANUAL LABOR CONTRIBUTION

            =========================================

            */

            $stmt = $conn->prepare("

                INSERT INTO user_contributions

                (

                    user_id,

                    contribution_type,

                    manual_hours,

                    estimated_value,

                    valuation_method

                )

                VALUES (

                    ?,

                    'manual_labor',

                    ?,

                    ?,

                    ?

                )

            ");

            if (!$stmt) {

                throw new Exception($conn->error);

            }

            $stmt->bind_param(

                "idds",

                $user_id,

                $manual_hours,

                $estimated_value,

                $valuation_method

            );

            $stmt->execute();

            $stmt->close();

        }

    }

 /* =====================================

   SKILLED LABOR

===================================== */

if (

    in_array(

        "skilled_labor",

        $contribution_types,

        true

    )

) {

    $specialty =

        trim(

            $_POST["specialty"] ?? ""

        );

    $skilled_hours =

        isset($_POST["skilled_hours"])

            ? (float) $_POST["skilled_hours"]

            : 0;

    if (

        $specialty !== ""

        &&

        $skilled_hours > 0

    ) {

        /*

        =========================================

        CALCULATE SKILLED LABOR ECONOMIC VALUE

        =========================================

        */

        $estimated_value =

            calculateSkilledLaborValue(

                $specialty,

                $skilled_hours

            );

        $valuation_method =

            "Eurostat SES 2022 Greece - mean hourly earnings, ISCO "
            . getLaborReference("skilled_labor", $specialty)["isco"] . " "
            . getLaborReference("skilled_labor", $specialty)["group"];

        /*

        =========================================

        SAVE SKILLED LABOR CONTRIBUTION

        =========================================

        */

        $stmt = $conn->prepare("

            INSERT INTO user_contributions

            (

                user_id,

                contribution_type,

                specialty,

                skilled_hours,

                estimated_value,

                valuation_method

            )

            VALUES (

                ?,

                'skilled_labor',

                ?,

                ?,

                ?,

                ?

            )

        ");

        if (!$stmt) {

            throw new Exception($conn->error);

        }

        $stmt->bind_param(

            "isdds",

            $user_id,

            $specialty,

            $skilled_hours,

            $estimated_value,

            $valuation_method

        );

        $stmt->execute();

        $stmt->close();

    }

}

/* =====================================
   EQUIPMENT
   One row in user_contributions for every
   equipment group the user declared
   (already validated in $equipment_rows).
===================================== */



if (in_array("equipment", $contribution_types, true)) {

    $stmt = $conn->prepare("
        INSERT INTO user_contributions
        (
            user_id,
            contribution_type,
            equipment_type,
            equipment_quantity,
            equipment_capacity,
            equipment_capacity_unit,
            equipment_age,
            equipment_condition,
            battery_chemistry,
            estimated_value,
            valuation_method
        )
        VALUES (?, 'equipment', ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    foreach ($equipment_rows as $equipment) {

        $estimated_value = calculateEquipmentValue(
            $equipment["type"],
            $equipment["quantity"],
            $equipment["capacity"],
            $equipment["unit"],
            $equipment["age"],
            $equipment["condition"],
            $equipment["battery_chemistry"]
        );

        if ($estimated_value === null) {
            throw new Exception(
                "Unable to calculate equipment value for type " . $equipment["type"]
            );
        }

        $valuation_method = getEquipmentValuationMethod($equipment["type"]);

        $stmt->bind_param(
            "isidsdssds",
            $user_id,
            $equipment["type"],
            $equipment["quantity"],
            $equipment["capacity"],
            $equipment["unit"],
            $equipment["age"],
            $equipment["condition"],
            $equipment["battery_chemistry"],
            $estimated_value,
            $valuation_method
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
    }

    $stmt->close();
}

/* =====================================

       SUCCESS

    ===================================== */

    $conn->commit();

    header(

        "Location: profile.php?saved=1"

    );

    exit();

}

catch (Throwable $e) {

    $conn->rollback();

    /* the user's own mistake -> message on the profile page */
    if ($e instanceof EmProfileInputError) {
        header("Location: profile.php?edit=1&error=" . urlencode($e->getMessage()));
        exit();
    }

    /* anything else: logged for the developers, a translated message for the user */
    error_log("EnergyMatch save_profile: " . $e->getMessage());

    header("Location: profile.php?edit=1&error=profile_save_error");

    exit();

}

?>
