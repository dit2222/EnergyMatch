<?php

/*
|--------------------------------------------------------------------------
| ENERGYMATCH - EQUIPMENT VALUATION REFERENCE DATA
|--------------------------------------------------------------------------
|
| Equipment is valued as what the community would pay to buy the same
| equipment NEW today, reduced for its age and its condition.
| ONE rule for every type:
|
|   Value = quantity x capacity per unit x new price
|           x age factor x condition factor
|
|   age factor = max(min_age_factor, 1 - age / service life)
|
| ALL new prices come from ONE source, so the types are compared on the
| same basis (as land and labour both use Eurostat):
|
|   sun.store - PV Index (modules, inverters) and Battery Index,
|   European online PV trading platform, published monthly
|   (also reported by pv Europe).
|
| Active types: PV panels, storage batteries, electrical equipment.
| (Wind turbines were removed: EnergyMatch focuses on PV communities.)
|
| The service lives, the minimum age factor and the condition factors
| are EnergyMatch modelling assumptions.
|
*/

return [

    /*
     * Old equipment never drops below 20% of its new value
     * (it still works and has some resale value).
     */
    "min_age_factor" => 0.20,

    /* EnergyMatch modelling assumption, the same for every type */
    "condition_factors" => [
        "excellent" => 1.00,
        "good" => 0.90,
        "fair" => 0.75,
        "poor" => 0.50,
    ],

    /* =========================================
       PV PANELS  (capacity per panel in W)
    ========================================= */
    "pv_panels" => [
        /* sun.store PV Index, September 2026, TOPCon monofacial
           (the mainstream module category): EUR 0.120 / W */
        "reference_price" => 0.120,
        "price_unit" => "€/W",
        "capacity_unit" => "W",
        "price_source" => "sun.store PV Index, September 2026 - TOPCon monofacial modules",

        /* Typical PV module service life (manufacturers' performance
           warranties are 25 years) */
        "service_life" => 25,
    ],

    /* =========================================
       STORAGE BATTERIES  (capacity per battery in kWh)
    ========================================= */
    "battery_storage" => [
        /* sun.store Battery Index, August 2026, residential (up to
           20 kWh), high-voltage performance brands: EUR 198.10 / kWh */
        "reference_price" => 198.10,
        "price_unit" => "€/kWh",
        "capacity_unit" => "kWh",
        "price_source" => "sun.store Battery Index, August 2026 - residential high-voltage performance",

        /* Typical home battery warranty / service life */
        "service_life" => 10,

        /* Chemistry is stored as information only, it does not change the value */
        "supported_chemistries" => ["lfp", "nmc", "lead_acid"],
    ],

    /* =========================================
       ELECTRICAL EQUIPMENT  (power per unit in kW)
    ========================================= */
    "electrical_equipment" => [
        /*
         * sun.store PV Index, September 2026, hybrid inverters
         * 1-20 kW: EUR 94.66 / kW.
         *
         * EnergyMatch ASSUMPTION: the inverter is the main and most
         * expensive electrical component, so its price per kW is used
         * for the whole category (inverter, panels/boards, cables,
         * protection). There is no public price index for the other parts.
         */
        "reference_price" => 94.66,
        "price_unit" => "€/kW",
        "capacity_unit" => "kW",
        "price_source" => "sun.store PV Index, September 2026 - hybrid inverters 1-20 kW (EnergyMatch assumption: used for all electrical equipment)",

        /* Typical inverter warranty / service life */
        "service_life" => 10,
    ],

];
