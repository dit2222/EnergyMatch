<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - CONTRIBUTION VALUATION
|--------------------------------------------------------------------------
|
| This file is responsible for converting every user contribution
| into a project-specific relative contribution score.
|
| Contribution types:
|
| 1. Capital
| 2. Land / Space
| 3. Manual Labor
| 4. Skilled Labor / Expertise
| 5. Equipment / Infrastructure
|
| IMPORTANT:
| Economic valuation and technical/energy suitability are different.
|
*/
/*
|--------------------------------------------------------------------------
| CAPITAL VALUATION
|--------------------------------------------------------------------------
|
| Capital already represents a monetary contribution.
|
| Economic Value = Capital Amount
|
| Example:
| €5,000 contribution -> €5,000 estimated value
|
*/
function calculateCapitalValue($capitalAmount)
{
    $capitalAmount = (float) $capitalAmount;
    if ($capitalAmount <= 0) {
        return 0.00;
    }
    return round($capitalAmount, 2);
}
/*
|--------------------------------------------------------------------------
| LAND VALUATION
|--------------------------------------------------------------------------
|
| Land is valued as a one-off market value (see land_valuation_data.php):
|
|   Price per stremma = Eurostat national average (EUR/ha) / 10
|   f                 = H_location / H_reference, limited to [0.8, 1.2]
|   Land value        = stremmata x price per stremma x f
|
| Example: 5 stremmata, f = 1.02
|   5 x 1,357.10 x 1.02 = 6,921.21 EUR
|
*/

function getLandValuationData(): array
{
    static $data = null;

    if ($data === null) {
        $data = require __DIR__ . "/land_valuation_data.php";
    }

    return $data;
}

/* EUR per stremma (Eurostat national average / 10). */
function getLandPricePerStremma(): float
{
    $data = getLandValuationData();

    return (float) $data["national_average"] / (float) $data["stremmata_per_hectare"];
}

/*
 * Location factor f = H_location / H_reference, limited to [f_min, f_max].
 * Returns 1.00 when the irradiation or H_reference is not available.
 */
function calculateLandLocationFactor($annualIrradiation): float
{
    $data = getLandValuationData();
    $hReference = $data["h_reference"] ?? null;

    if (
        $hReference === null
        || (float) $hReference <= 0
        || $annualIrradiation === null
        || $annualIrradiation === ""
        || !is_numeric($annualIrradiation)
        || (float) $annualIrradiation <= 0
    ) {
        return 1.00;
    }

    $factor = (float) $annualIrradiation / (float) $hReference;
    $factor = max((float) $data["f_min"], min((float) $data["f_max"], $factor));

    return round($factor, 4);
}

/* Land value in EUR (one-off). */
function calculateLandValue($landArea, $annualIrradiation = null): ?float
{
    $landArea = (float) $landArea;

    if (!is_finite($landArea) || $landArea <= 0) {
        return null;
    }

    $value = $landArea
        * getLandPricePerStremma()
        * calculateLandLocationFactor($annualIrradiation);

    return is_finite($value) ? round($value, 2) : null;
}

/*
 * Maximum labour hours a user can declare: 1,000 hours,
 * about 6 months of full-time work (8 h/day). Without a limit a user
 * could declare e.g. 10,000 hours and take almost all of the Fair Share.
 */
const MAX_LABOR_HOURS = 1000;

/*
|--------------------------------------------------------------------------
| LABOUR VALUATION (manual and skilled)
|--------------------------------------------------------------------------
|
| Value = hours x hourly rate of the matching occupation group
| Hourly rates: Eurostat Structure of Earnings Survey 2022, Greece
| (see labor_valuation_data.php). Hours are limited to MAX_LABOR_HOURS.
|
| Example: Electrician, 300 hours -> 300 x 9.11 = 2,733.00 EUR
|
*/

function getLaborValuationData(): array
{
    static $data = null;

    if ($data === null) {
        $data = require __DIR__ . "/labor_valuation_data.php";
    }

    return $data;
}

/*
 * Reference group for a contribution:
 * ["isco" => "OC7", "group" => "...", "hourly_rate" => 9.11]
 */
function getLaborReference(string $type, ?string $specialty = null): array
{
    $data = getLaborValuationData();

    if ($type === "manual_labor") {
        return $data["manual_labor"];
    }

    $specialty = trim((string) $specialty);
    $groups = $data["skilled_labor"];

    return $groups[$specialty] ?? $groups[$data["skilled_labor_default"]];
}

function calculateManualLaborValue($manualHours)
{
    $manualHours = min((float) $manualHours, MAX_LABOR_HOURS);

    if ($manualHours <= 0) {
        return null;
    }

    $rate = (float) getLaborReference("manual_labor")["hourly_rate"];

    return round($manualHours * $rate, 2);
}

function calculateSkilledLaborValue($specialty, $skilledHours)
{
    $specialty = trim((string) $specialty);
    $skilledHours = min((float) $skilledHours, MAX_LABOR_HOURS);

    if ($specialty === "" || $skilledHours <= 0) {
        return null;
    }

    $rate = (float) getLaborReference("skilled_labor", $specialty)["hourly_rate"];

    return round($skilledHours * $rate, 2);
}

/*
|--------------------------------------------------------------------------
| EQUIPMENT TYPES AND THEIR UNIT
|--------------------------------------------------------------------------
|
| The unit is fixed by the type, so the user does not choose it:
|   PV panels            -> W   (power of ONE panel)
|   Storage batteries    -> kWh (capacity of ONE battery)
|   Electrical equipment -> kW  (power of ONE unit, e.g. inverter)
|
*/

const EQUIPMENT_UNITS = [
    "pv_panels" => "W",
    "battery_storage" => "kWh",
    "electrical_equipment" => "kW",
];

/* Maximum number of equipment rows one user can declare */
const MAX_EQUIPMENT_ROWS = 10;

function equipmentUnitForType(string $type): ?string
{
    return EQUIPMENT_UNITS[$type] ?? null;
}

/*
|--------------------------------------------------------------------------
| EQUIPMENT VALUATION
|--------------------------------------------------------------------------
|
| One rule for every type (see equipment_valuation_data.php):
|
|   Value = quantity x capacity per unit x new price
|           x age factor x condition factor
|
|   age factor = max(0.20, 1 - age / service life)
|
| getEquipmentValuationBreakdown() returns every step, so the profile
| summary can show exactly how the value was calculated.
|
*/

function getEquipmentValuationData(): array
{
    static $data = null;

    if ($data === null) {
        $data = require __DIR__ . "/equipment_valuation_data.php";
    }

    return $data;
}

/* Converts a stored capacity to the unit of the type (W, kWh or kW) */
function convertEquipmentCapacity(string $type, float $capacity, string $unit): ?float
{
    $unit = strtolower(trim($unit));

    if ($type === "pv_panels") {
        if ($unit === "w" || $unit === "wp") {
            return $capacity;
        }
        if ($unit === "kw" || $unit === "kwp") {
            return $capacity * 1000;
        }
    }

    if ($type === "battery_storage") {
        if ($unit === "kwh") {
            return $capacity;
        }
        if ($unit === "wh") {
            return $capacity / 1000;
        }
    }

    if ($type === "electrical_equipment") {
        if ($unit === "kw" || $unit === "kwp") {
            return $capacity;
        }
        if ($unit === "w" || $unit === "wp") {
            return $capacity / 1000;
        }
    }

    return null;
}

function getEquipmentValuationBreakdown(
    $equipmentType,
    $quantity,
    $capacity,
    $capacityUnit,
    $age,
    $condition
): ?array {
    $type = str_replace([" ", "-"], "_", strtolower(trim((string) $equipmentType)));
    $quantity = (int) $quantity;
    $age = max(0, (float) $age);
    $condition = strtolower(trim((string) $condition));

    $data = getEquipmentValuationData();

    if (!isset($data[$type]) || $quantity <= 0 || (float) $capacity <= 0) {
        return null;
    }

    $typeData = $data[$type];
    $conditionFactor = $data["condition_factors"][$condition] ?? null;
    $capacityPerUnit = convertEquipmentCapacity($type, (float) $capacity, (string) $capacityUnit);

    if ($conditionFactor === null || $capacityPerUnit === null) {
        return null;
    }

    $totalCapacity = $quantity * $capacityPerUnit;
    $serviceLife = (float) $typeData["service_life"];
    $minAgeFactor = (float) $data["min_age_factor"];
    $ageFactor = max($minAgeFactor, 1 - ($age / $serviceLife));
    $newValue = $totalCapacity * (float) $typeData["reference_price"];

    return [
        "type" => $type,
        "quantity" => $quantity,
        "capacity_per_unit" => $capacityPerUnit,
        "capacity_unit" => $typeData["capacity_unit"],
        "total_capacity" => $totalCapacity,
        "reference_price" => (float) $typeData["reference_price"],
        "price_unit" => $typeData["price_unit"],
        "price_source" => $typeData["price_source"],
        "new_value" => round($newValue, 2),
        "age" => $age,
        "service_life" => $serviceLife,
        "min_age_factor" => $minAgeFactor,
        "age_factor" => $ageFactor,
        "age_factor_at_minimum" => $ageFactor <= $minAgeFactor,
        "condition" => $condition,
        "condition_factor" => (float) $conditionFactor,
        "value" => round($newValue * $ageFactor * (float) $conditionFactor, 2),
    ];
}

/* Text stored in user_contributions.valuation_method */
function getEquipmentValuationMethod(string $type): ?string
{
    $data = getEquipmentValuationData();

    if (!isset($data[$type])) {
        return null;
    }

    return $data[$type]["price_source"]
        . " + straight-line age factor (service life " . $data[$type]["service_life"] . " years, minimum 20%)"
        . " + EnergyMatch condition factor";
}

/*
 * Value of one equipment group (see getEquipmentValuationBreakdown).
 * Battery chemistry is stored for information and does not change the value.
 */
function calculateEquipmentValue(
    $equipmentType,
    $quantity,
    $capacity,
    $capacityUnit,
    $age,
    $condition,
    $batteryChemistry = null
) {
    $breakdown = getEquipmentValuationBreakdown(
        $equipmentType,
        $quantity,
        $capacity,
        $capacityUnit,
        $age,
        $condition
    );

    return $breakdown === null ? null : $breakdown["value"];
}

/*
|--------------------------------------------------------------------------
| GENERIC CONTRIBUTION VALUATION
|--------------------------------------------------------------------------
|
| Receives one row from user_contributions and calls
| the appropriate valuation method.
|
*/
function calculateContributionValue(array $contribution)
{
    $type = $contribution["contribution_type"] ?? "";
    switch ($type) {
        case "capital":
            return calculateCapitalValue(
                $contribution["capital_amount"] ?? 0
            );
        case "land":
            return calculateLandValue(
                $contribution["land_area"] ?? 0,
                $contribution["solar_irradiation"] ?? null
            );
        case "manual_labor":
            return calculateManualLaborValue(
                $contribution["manual_hours"] ?? 0
            );
        case "skilled_labor":
            return calculateSkilledLaborValue(
                $contribution["specialty"] ?? "",
                $contribution["skilled_hours"] ?? 0
            );
case "equipment":
    return calculateEquipmentValue(
        $contribution["equipment_type"] ?? "",
        $contribution["equipment_quantity"] ?? 0,
        $contribution["equipment_capacity"] ?? 0,
        $contribution["equipment_capacity_unit"] ?? "",
        $contribution["equipment_age"] ?? 0,
        $contribution["equipment_condition"] ?? "",
        $contribution["battery_chemistry"] ?? null
    );
        default:
            return null;
    }
}
/*
|--------------------------------------------------------------------------
| TOTAL USER CONTRIBUTION VALUE
|--------------------------------------------------------------------------
|
| Adds all economically valued contributions of one user.
|
*/
function calculateUserContributionValue(array $contributions)
{
    $total = 0.00;
    foreach ($contributions as $contribution) {
        $value = calculateContributionValue($contribution);
        if ($value !== null && $value > 0) {
            $total += $value;
        }
    }
    return round($total, 2);
}
/*
|--------------------------------------------------------------------------
| FAIR SHARE
|--------------------------------------------------------------------------
|
| FairShare_i =
|
| UserContributionValue
| ------------------------- × 100
| TotalGroupValue
|
*/
function calculateFairShare(
    $userContributionValue,
    $totalGroupValue
) {
    $userContributionValue = (float) $userContributionValue;
    $totalGroupValue = (float) $totalGroupValue;
    if ($totalGroupValue <= 0) {
        return 0.00;
    }
    return round(
        ($userContributionValue / $totalGroupValue) * 100,
        2
    );
}
/*
|--------------------------------------------------------------------------
| MINIMUM SHARE CHECK
|--------------------------------------------------------------------------
|
| A member accepts the proposed community when:
|
| FairShare >= MinimumShare
|
*/
function satisfiesMinimumShare(
    $fairShare,
    $minimumShare
) {
    $fairShare = (float) $fairShare;
    $minimumShare = (float) $minimumShare;
    return $fairShare >= $minimumShare;
}
/*
|--------------------------------------------------------------------------
| GROUP MINIMUM SHARE PRE-CHECK
|--------------------------------------------------------------------------
|
| If the sum of the five minimum shares is greater than 100%,
| the proposed group cannot satisfy all members.
|
*/
function minimumSharesArePossible(array $minimumShares)
{
    $totalMinimumShare = 0.00;
    foreach ($minimumShares as $minimumShare) {
        $totalMinimumShare += (float) $minimumShare;
    }
    return $totalMinimumShare <= 100.00;
}
