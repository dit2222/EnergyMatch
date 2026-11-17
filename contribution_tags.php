<?php
/*
|--------------------------------------------------------------------------
| CONTRIBUTION TAGS WITH DETAILS ON HOVER
|--------------------------------------------------------------------------
| Used by matches.php (all eligible members) and create_community.php.
| Each contribution type becomes a small tag; moving the mouse over it
| (or focusing it with the keyboard) shows a small box with the details
| and the valuation.
|
| emContributionTags($member, $landInfo)
|   $member   = one row of emLoadEligibleMembers() (community_algorithm.php)
|   $landInfo = row of emLoadLandInfo() for this user, or null
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/contribution_valuation.php";

/* Land details that are not part of the member row (solar score, factor f) */
function emLoadLandInfo($conn): array
{
    $landInfo = [];
    $sql = "SELECT user_id, SUM(estimated_value) AS land_value, SUM(land_area) AS land_area,
                   AVG(location_factor) AS location_factor, AVG(solar_score) AS solar_score,
                   AVG(solar_irradiation) AS solar_irradiation
            FROM user_contributions
            WHERE contribution_type = 'land'
            GROUP BY user_id";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $landInfo[(int) $row["user_id"]] = $row;
        }
        $result->free();
    }

    return $landInfo;
}

function emTagIsGreek(): bool
{
    return ($_SESSION["language"] ?? "en") === "gr";
}

function emTagText(string $english, string $greek): string
{
    return emTagIsGreek() ? $greek : $english;
}

function emTagNumber($value, int $decimals = 2): string
{
    return emTagIsGreek()
        ? number_format((float) $value, $decimals, ",", ".")
        : number_format((float) $value, $decimals, ".", ",");
}

/* 450.00 -> "450", 2.50 -> "2,5" */
function emTagShortNumber($value): string
{
    $text = emTagNumber($value, 2);
    $separator = emTagIsGreek() ? "," : ".";
    return rtrim(rtrim($text, "0"), $separator);
}

function emTagMoney($value): string
{
    return emTagIsGreek()
        ? emTagNumber($value) . " €"
        : "€" . emTagNumber($value);
}

function emTagEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function emTagSpecialtyLabel(string $specialty): string
{
    $labels = [
        "Electrician"   => ["Electrician", "Ηλεκτρολόγος"],
        "PV Technician" => ["PV Technician", "Τεχνικός Φωτοβολταϊκών"],
        "Engineer"      => ["Engineer", "Μηχανικός"],
        "Installer"     => ["Installer", "Εγκαταστάτης"],
        "Other"         => ["Other specialty", "Άλλη ειδικότητα"],
    ];

    return isset($labels[$specialty])
        ? emTagText($labels[$specialty][0], $labels[$specialty][1])
        : $specialty;
}

function emTagEquipmentLabel(string $type): string
{
    $labels = [
        "pv_panels"            => ["PV panels", "Φωτοβολταϊκά πάνελ"],
        "battery_storage"      => ["Batteries", "Μπαταρίες"],
        "electrical_equipment" => ["Electrical equipment", "Ηλεκτρολογικός εξοπλισμός"],
    ];

    return isset($labels[$type])
        ? emTagText($labels[$type][0], $labels[$type][1])
        : str_replace("_", " ", $type);
}

/*
 * Data for each tag: icon, short label, title, rows [label, value],
 * value and a short explanation.
 */
function emContributionTagData(array $member, ?array $landInfo = null): array
{
    $details = $member["contribution_details"] ?? [];
    $values = $member["contribution_breakdown"] ?? [];
    $tags = [];

    foreach (($member["contribution_types"] ?? []) as $type) {
        $value = (float) ($values[$type] ?? 0);

        if ($type === "capital") {
            $tags[] = [
                "type" => $type,
                "icon" => "coins",
                "label" => emTagText("Capital", "Κεφάλαιο"),
                "title" => emTagText("Capital", "Κεφάλαιο"),
                "rows" => [
                    [emTagText("Amount", "Ποσό"), emTagMoney($details["capital_amount"] ?? $value)],
                ],
                "value" => $value,
                "note" => emTagText(
                    "Money the member puts into the project. It counts at its full amount.",
                    "Χρήματα που βάζει το μέλος στο έργο. Μετράνε με όλο τους το ποσό."
                ),
            ];
        } elseif ($type === "land") {
            $area = (float) ($landInfo["land_area"] ?? $details["land_area"] ?? 0);
            $rows = [
                [emTagText("Area", "Έκταση"), emTagShortNumber($area) . " " . emTagText("str.", "στρ.")],
            ];

            if (trim((string) ($details["land_location"] ?? "")) !== "") {
                $rows[] = [emTagText("Location", "Τοποθεσία"), $details["land_location"]];
            }

            if ($landInfo !== null && $landInfo["solar_score"] !== null) {
                $rows[] = ["Solar Score", emTagNumber($landInfo["solar_score"]) . "%"];
            }

            if ($landInfo !== null && $landInfo["location_factor"] !== null) {
                $landData = require __DIR__ . "/land_valuation_data.php";
                $pricePerStremma = (float) $landData["national_average"] / (float) $landData["stremmata_per_hectare"];
                $rows[] = [
                    emTagText("Calculation", "Υπολογισμός"),
                    emTagShortNumber($area) . " × " . emTagMoney($pricePerStremma) . " × f " . emTagNumber($landInfo["location_factor"], 3),
                ];
            }

            $tags[] = [
                "type" => $type,
                "icon" => "land-plot",
                "label" => emTagText("Land", "Γη") . " · " . emTagShortNumber($area) . " " . emTagText("str.", "στρ."),
                "title" => emTagText("Land / space", "Γη / χώρος"),
                "rows" => $rows,
                "value" => $value,
                "note" => emTagText(
                    "Eurostat land price adjusted to the solar potential (f) of the location. Model estimate.",
                    "Τιμή γης Eurostat προσαρμοσμένη στο ηλιακό δυναμικό (f) της τοποθεσίας. Εκτίμηση μοντέλου."
                ),
            ];
        } elseif ($type === "manual_labor") {
            $hours = (float) ($details["manual_hours"] ?? 0);
            $rate = (float) getLaborReference("manual_labor")["hourly_rate"];
            $tags[] = [
                "type" => $type,
                "icon" => "hard-hat",
                "label" => emTagText("Manual labour", "Χειρωνακτική"),
                "title" => emTagText("Manual labour", "Χειρωνακτική εργασία"),
                "rows" => [
                    [emTagText("Hours", "Ώρες"), emTagShortNumber($hours)],
                    [emTagText("Hourly rate", "Ωριαία αμοιβή"), emTagMoney($rate)],
                ],
                "value" => $value,
                "note" => emTagText(
                    "General work on the installation (e.g. carrying, cleaning, assembly).",
                    "Γενική εργασία στην εγκατάσταση (π.χ. μεταφορές, καθαρισμός, συναρμολόγηση)."
                ),
            ];
        } elseif ($type === "skilled_labor") {
            $specialty = trim((string) ($details["specialty"] ?? ""));
            $hours = (float) ($details["skilled_hours"] ?? 0);
            $rate = (float) getLaborReference("skilled_labor", $specialty)["hourly_rate"];
            $specialtyLabel = $specialty !== ""
                ? emTagSpecialtyLabel($specialty)
                : emTagText("Skilled labour", "Εξειδικευμένη");
            $tags[] = [
                "type" => $type,
                "icon" => "wrench",
                "label" => $specialtyLabel,
                "title" => emTagText("Skilled labour", "Εξειδικευμένη εργασία"),
                "rows" => [
                    [emTagText("Specialty", "Ειδικότητα"), $specialtyLabel],
                    [emTagText("Hours", "Ώρες"), emTagShortNumber($hours)],
                    [emTagText("Hourly rate", "Ωριαία αμοιβή"), emTagMoney($rate)],
                ],
                "value" => $value,
                "note" => emTagText(
                    "Professional work, valued with the mean hourly earnings of the specialty in Greece (Eurostat 2022).",
                    "Επαγγελματική εργασία, με τις μέσες ωριαίες αποδοχές της ειδικότητας στην Ελλάδα (Eurostat 2022)."
                ),
            ];
        } elseif ($type === "equipment") {
            $items = $details["equipment_items"] ?? [];
            $rows = [];

            foreach ($items as $item) {
                $text = (int) $item["equipment_quantity"] . " × " . emTagShortNumber($item["equipment_capacity"]);
                if ($item["equipment_capacity_unit"] !== "") {
                    $text .= " " . $item["equipment_capacity_unit"];
                }
                $rows[] = [emTagEquipmentLabel((string) $item["equipment_type"]), $text];
            }

            $label = count($items) === 1
                ? emTagEquipmentLabel((string) $items[0]["equipment_type"])
                : emTagText("Equipment", "Εξοπλισμός") . (count($items) > 1 ? " (" . count($items) . ")" : "");

            $tags[] = [
                "type" => $type,
                "icon" => "plug-zap",
                "label" => $label,
                "title" => emTagText("Equipment", "Εξοπλισμός"),
                "rows" => $rows,
                "value" => $value,
                "note" => emTagText(
                    "Price new × age factor × condition (sun.store market prices).",
                    "Τιμή καινούργιου × συντελεστής ηλικίας × κατάσταση (τιμές αγοράς sun.store)."
                ),
            ];
        }
    }

    return $tags;
}

/* HTML of the tags (the details box appears on hover / keyboard focus) */
function emContributionTags(array $member, ?array $landInfo = null): string
{
    $html = "";

    foreach (emContributionTagData($member, $landInfo) as $tag) {
        $html .= '<span class="em-ctag" tabindex="0" data-type="' . emTagEscape($tag["type"]) . '">';
        $html .= '<i data-lucide="' . emTagEscape($tag["icon"]) . '"></i>';
        $html .= '<span class="em-ctag-label">' . emTagEscape($tag["label"]) . '</span>';

        $html .= '<span class="em-ctag-tip" role="tooltip">';
        $html .= '<span class="em-ctag-tip-title"><i data-lucide="' . emTagEscape($tag["icon"]) . '"></i>' . emTagEscape($tag["title"]) . '</span>';

        foreach ($tag["rows"] as $row) {
            $html .= '<span class="em-ctag-tip-row"><span>' . emTagEscape($row[0]) . '</span><b>' . emTagEscape($row[1]) . '</b></span>';
        }

        $html .= '<span class="em-ctag-tip-value"><span>' . emTagText("Valuation", "Αποτίμηση") . '</span><b>' . emTagEscape(emTagMoney($tag["value"])) . '</b></span>';
        $html .= '<small>' . emTagEscape($tag["note"]) . '</small>';
        $html .= '</span>';

        $html .= '</span>';
    }

    return $html;
}
