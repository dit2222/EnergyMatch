<?php

function getPvgisSolarData(float $latitude, float $longitude): ?array
{
    if (
        $latitude < -90 ||
        $latitude > 90 ||
        $longitude < -180 ||
        $longitude > 180
    ) {
        return null;
    }

    $query = http_build_query([
        "lat" => $latitude,
        "lon" => $longitude,
        "optrad" => 1,
        "outputformat" => "json"
    ]);

    $url = "https://re.jrc.ec.europa.eu/api/v5_3/MRcalc?" . $query;

    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "timeout" => 12,
            "header" => "User-Agent: EnergyMatch/1.0\r\n"
        ]
    ]);

    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);

    if (
        !is_array($data) ||
        !isset($data["outputs"]["monthly"]) ||
        !is_array($data["outputs"]["monthly"])
    ) {
        return null;
    }

    $yearlyTotals = [];

    foreach ($data["outputs"]["monthly"] as $month) {
        if (!isset($month["year"], $month["H(i_opt)_m"])) {
            continue;
        }

        $year = (int) $month["year"];
        $irradiation = (float) $month["H(i_opt)_m"];

        if (!isset($yearlyTotals[$year])) {
            $yearlyTotals[$year] = 0.0;
        }

        $yearlyTotals[$year] += $irradiation;
    }

    if (!$yearlyTotals) {
        return null;
    }

    $annualIrradiation = array_sum($yearlyTotals) / count($yearlyTotals);

    $optimalSlope = null;
    $optimalAzimuth = null;

    $plane = $data["inputs"]["plane"]["fixed_inclined_optimal"] ?? null;

    if (is_array($plane)) {
        if (isset($plane["slope"]["value"])) {
            $optimalSlope = (float) $plane["slope"]["value"];
        }

        if (isset($plane["azimuth"]["value"])) {
            $optimalAzimuth = (float) $plane["azimuth"]["value"];
        }
    }

    $solarMinimum = 1300.0;
$solarMaximum = 2100.0;

$solarScore = (
    ($annualIrradiation - $solarMinimum) /
    ($solarMaximum - $solarMinimum)
) * 100;

$solarScore = max(0, min(100, $solarScore));

    return [
        "annual_irradiation" => round($annualIrradiation, 2),
        "optimal_slope" => $optimalSlope,
        "optimal_azimuth" => $optimalAzimuth,
        "solar_score" => round($solarScore, 2)
    ];
}