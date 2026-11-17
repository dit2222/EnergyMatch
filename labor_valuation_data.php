<?php

/*
|--------------------------------------------------------------------------
| ENERGYMATCH - LABOUR VALUATION REFERENCE DATA
|--------------------------------------------------------------------------
|
| Labour is valued as the hourly cost of the same kind of work in Greece:
|
|   Value = hours x hourly rate of the matching occupation group
|
| ALL labour rates come from ONE official source, so manual and skilled
| work are compared on the same basis (and with the same logic as land,
| which also uses Eurostat, Greece, 2022):
|
|   Eurostat - Structure of Earnings Survey 2022
|   Table earn_ses22_14: Mean hourly earnings by sex, age and occupation
|   Greece, gross earnings, EUR, enterprises with 10+ employees,
|   NACE Rev. 2 B-S excluding O, sex = total, age = total.
|
| Eurostat publishes Greek data only for the main ISCO-08 groups (one
| digit), so each specialty is mapped to its main group.
|
| Maximum declared hours: MAX_LABOR_HOURS in contribution_valuation.php.
|
*/

return [

    "source" => "Eurostat - Structure of Earnings Survey 2022 (earn_ses22_14)",

    "reference_year" => 2022,

    "unit" => "EUR/hour",

    /* Manual labour -> ISCO-08 OC9 Elementary occupations */
    "manual_labor" => [
        "isco" => "OC9",
        "group" => "Elementary occupations",
        "hourly_rate" => 6.96,
    ],

    /* Skilled labour: specialty value (as stored by the form) -> ISCO group */
    "skilled_labor" => [

        "Engineer" => [
            "isco" => "OC2",
            "group" => "Professionals",
            "hourly_rate" => 12.15,
        ],

        "PV Technician" => [
            "isco" => "OC3",
            "group" => "Technicians and associate professionals",
            "hourly_rate" => 10.35,
        ],

        "Electrician" => [
            "isco" => "OC7",
            "group" => "Craft and related trades workers",
            "hourly_rate" => 9.11,
        ],

        "Installer" => [
            "isco" => "OC7",
            "group" => "Craft and related trades workers",
            "hourly_rate" => 9.11,
        ],

        "Other" => [
            "isco" => "OC7",
            "group" => "Craft and related trades workers",
            "hourly_rate" => 9.11,
        ],
    ],

    /* Used for any specialty that is not in the list above */
    "skilled_labor_default" => "Other",

];
