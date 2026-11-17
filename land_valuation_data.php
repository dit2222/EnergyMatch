<?php

/*
|--------------------------------------------------------------------------
| ENERGYMATCH - LAND VALUATION REFERENCE DATA
|--------------------------------------------------------------------------
|
| Land is valued as a one-off market value (the user transfers the land
| to the community), so it is comparable with capital, equipment and
| labour, which are also one-off EUR amounts.
|
|   Land value = stremmata x (national_average / 10) x f
|   f          = H_location / h_reference, limited to [f_min, f_max]
|
| - national_average: Eurostat agricultural land price (EUR / hectare).
|   1 hectare = 10 stremmata, hence the division by 10.
| - The NATIONAL average is used for every region. Regional Eurostat
|   prices have gaps for many Greek regions, and Attica (84,820 EUR/ha,
|   about 6x the national average) reflects urban pressure, not value
|   for a PV installation. What differentiates land is the sun (f).
| - H_location: annual irradiation on an optimally inclined plane,
|   H(i_opt), from PVGIS (pvgis_service.php), kWh/m2.
| - h_reference: average H(i_opt) of 5 reference cities (Athens,
|   Thessaloniki, Larisa, Heraklion, Ioannina), computed ONCE with the
|   SAME PVGIS call (tools/compute_h_reference.php).
| - f_min / f_max: covers all of Greece; values outside are treated as
|   input errors (wrong coordinates, unexpected PVGIS response).
|
| These are statistical reference values for comparative valuation,
| NOT certified property valuations.
|
*/

return [

    "source" => "Eurostat - Agricultural land prices by region",

    "dataset" => "apri_lprc",

    "reference_year" => 2022,

    "unit" => "EUR/ha",

    "land_category" => "arable_land",

    /* Greece national average, EUR per hectare */
    "national_average" => 13571.00,

    /* 1 hectare = 10 stremmata */
    "stremmata_per_hectare" => 10,

    /*
     * H_reference in kWh/m2 (PVGIS, H(i_opt), average of all years).
     * Fill in the value from tools/compute_h_reference.php.
     * Computed 16/10/2026: Athens 2035.71, Thessaloniki 1887.97,
     * Larisa 1925.41, Heraklion 2067.27, Ioannina 1872.60.
     * Check: Florina f = 0.893, Ierapetra f = 1.133 (inside 0.8-1.2).
     * If set to null, f = 1.00 for every plot.
     */
    "h_reference" => 1957.79,

    /* Allowed range of the location factor f */
    "f_min" => 0.80,

    "f_max" => 1.20,

];
