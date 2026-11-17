<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
require_once "db.php";
require_once "lang.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
$user_id = (int) $_SESSION["user_id"];
$language = $_SESSION["language"] ?? "en";

function safe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function emText($english, $greek)
{
    $language = $_SESSION["language"] ?? "en";
    return $language === "gr" ? $greek : $english;
}

/* Numbers in the user's language: 16.452,63 (Greek) / 16,452.63 (English) */
function emNumber($value, $decimals = 2)
{
    $greek = ($_SESSION["language"] ?? "en") === "gr";
    return number_format((float) $value, $decimals, $greek ? "," : ".", $greek ? "." : ",");
}

function contributionTypeLabel($type)
{
    $labels = [
        "capital" => ["Capital", "Κεφάλαιο"],
        "land" => ["Land / Space", "Γη / Χώρος"],
        "manual_labor" => ["Manual Labor", "Χειρωνακτική Εργασία"],
        "skilled_labor" => ["Skilled Labor", "Εξειδικευμένη Εργασία"],
        "equipment" => ["Equipment", "Εξοπλισμός"]
    ];
    if (!isset($labels[$type])) {
        return (string) $type;
    }
    return emText(
        $labels[$type][0],
        $labels[$type][1]
    );
}

function equipmentTypeLabel($type)
{
    $labels = [
        "pv_panels" => ["PV Panels", "Φωτοβολταϊκά Πάνελ"],
        "battery_storage" => ["Storage Batteries", "Μπαταρίες Αποθήκευσης"],
        "electrical_equipment" => ["Electrical Equipment", "Ηλεκτρολογικός Εξοπλισμός"]
    ];
    if (!isset($labels[$type])) {
        return str_replace("_", " ", (string) $type);
    }
    return emText($labels[$type][0], $labels[$type][1]);
}

function contributionPreviewItems($member)
{
    $items = [];
    $types = $member["contribution_types"] ?? [];
    $details = $member["contribution_details"] ?? [];
    foreach ($types as $type) {
        if ($type === "capital") {
            $amount = (float) ($details["capital_amount"] ?? 0);
            $icon = "coins";
            $text = contributionTypeLabel("capital");
            if ($amount > 0) {
                $text .= ": €" . emNumber($amount, 2);
            }
            $items[] = ["icon" => $icon, "text" => $text];
        } elseif ($type === "land") {
            $area = (float) ($details["land_area"] ?? 0);
            $location = trim((string) ($details["land_location"] ?? ""));
            $icon = "land-plot";
            $text = contributionTypeLabel("land");
            if ($area > 0) {
                $text .= ": " . emNumber($area, 2) . " " . emText("stremmata", "στρέμματα");
            }
            if ($location !== "") {
                $text .= " · " . $location;
            }
            $items[] = ["icon" => $icon, "text" => $text];
        } elseif ($type === "manual_labor") {
            $hours = (float) ($details["manual_hours"] ?? 0);
            $icon = "hard-hat";
            $text = contributionTypeLabel("manual_labor");
            if ($hours > 0) {
                $text .= ": " . emNumber($hours, 1) . " " . emText("hours", "ώρες");
            }
            $items[] = ["icon" => $icon, "text" => $text];
        } elseif ($type === "skilled_labor") {
            $specialty = trim((string) ($details["specialty"] ?? ""));
            $hours = (float) ($details["skilled_hours"] ?? 0);
            $icon = "wrench";
            $text = contributionTypeLabel("skilled_labor");
            if ($specialty !== "") {
                $text .= ": " . emTagSpecialtyLabel($specialty);
            }
            if ($hours > 0) {
                $text .= " · " . emNumber($hours, 1) . " " . emText("hours", "ώρες");
            }
            $items[] = ["icon" => $icon, "text" => $text];
        } elseif ($type === "equipment") {
            /* One line per equipment group */
            foreach (($details["equipment_items"] ?? []) as $item) {
                $icon = "plug-zap";
                $text = equipmentTypeLabel($item["equipment_type"]);
                if ($item["equipment_quantity"] > 0) {
                    $text .= " · " . $item["equipment_quantity"] . " " . emText("units", "τεμ.");
                }
                if ($item["equipment_capacity"] > 0) {
                    $text .= " × " . rtrim(rtrim(emNumber($item["equipment_capacity"], 2), "0"), ".,");
                    if ($item["equipment_capacity_unit"] !== "") {
                        $text .= " " . $item["equipment_capacity_unit"];
                    }
                }
                $items[] = ["icon" => $icon, "text" => $text];
            }
        }
    }
    return $items;
}

/*
|--------------------------------------------------------------------------
| NEW COMMUNITY ALGORITHM
|--------------------------------------------------------------------------
| This file now works with:
| - user_contributions.estimated_value
| - energy_profiles.minimum_share
| - Fair Share = contribution / total group value * 100
| - exactly 5 members per automatic community
|--------------------------------------------------------------------------
*/
require_once "community_algorithm.php";
require_once "contribution_tags.php";
$eligibleMembers = $eligibleMembers ?? [];
$membersById = $membersById ?? [];
$community_proposals = $community_proposals ?? [];
$all_valid_communities = $all_valid_communities ?? [];
$community_algorithm_error = $community_algorithm_error ?? null;
$community_offset = $community_offset ?? 0;
$community_limit = $community_limit ?? 5;
$has_more_communities = $has_more_communities ?? false;
$next_community_offset = $next_community_offset ?? null;
$previous_community_offset = $previous_community_offset ?? null;

/* =========================================
   CURRENT USER
========================================= */
$currentMember = $membersById[$user_id] ?? null;

/* =========================================
   ALL OTHER ELIGIBLE MEMBERS
========================================= */
$matches = [];
foreach ($eligibleMembers as $member) {
    if ((int) $member["user_id"] === $user_id) {
        continue;
    }
    $matches[] = $member;
}
$matches_count = count($matches);
$landEnergyByUser = [];

/* Land price per stremma (Eurostat national average / 10), see land_valuation_data.php */
$landValuationData = require __DIR__ . "/land_valuation_data.php";
$landPricePerStremma = (float) $landValuationData["national_average"] / (float) $landValuationData["stremmata_per_hectare"];
$landSql = "SELECT user_id, SUM(estimated_value) AS land_value, SUM(land_area) AS land_area, AVG(location_factor) AS location_factor, AVG(solar_score) AS solar_score, AVG(solar_irradiation) AS solar_irradiation FROM user_contributions WHERE contribution_type = 'land' GROUP BY user_id";
$landStmt = $conn->prepare($landSql);
if ($landStmt) {
    if ($landStmt->execute()) {
        $landResult = $landStmt->get_result();
        while ($landRow = $landResult->fetch_assoc()) {
            $landEnergyByUser[(int) $landRow["user_id"]] = $landRow;
        }
    }
    $landStmt->close();
}

/* =========================================
   AVAILABLE REGIONS
========================================= */
$available_regions = [];
foreach ($matches as $match) {
    $region = trim((string) ($match["location"] ?? ""));
    if ($region !== "") {
        $available_regions[] = $region;
    }
}
$available_regions = array_values(
    array_unique($available_regions)
);
sort(
    $available_regions,
    SORT_NATURAL | SORT_FLAG_CASE
);

/* =========================================
   INITIAL VIEW
========================================= */
$initial_view =
    (isset($_GET["view"]) && $_GET["view"] === "community")
        ? "community"
        : "all";
$total_valid_communities = count($all_valid_communities);
?>
<!DOCTYPE html>
<html lang="<?php echo $language === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link
        rel="stylesheet"
        href="css/match.css?v=<?php echo ASSET_VERSION; ?>"
    >
    <link
        rel="stylesheet"
        href="css/chatbot.css?v=<?php echo ASSET_VERSION; ?>"
    >
    <title>
        EnergyMatch - <?php echo t("matches"); ?>
    </title>
</head>
<body>

<?php include "navbar.php"; ?>

<section class="hero-section ui-page">

    <section class="matches-toolbar">
        <div class="toolbar-top">
            <div class="toolbar-title">
                <p class="toolbar-label">
                    <?php echo emText("ENERGY COMMUNITY", "ΕΝΕΡΓΕΙΑΚΗ ΚΟΙΝΟΤΗΤΑ"); ?>
                </p>
                <h1>
                    <?php echo emText(
                        "Community Matching",
                        "Αντιστοίχιση Κοινότητας"
                    ); ?>
                </h1>
                <p
                    class="toolbar-description"
                    id="toolbarDescription"
                >
                    <?php
                    echo emText(
                        "Eligible members are users with a positive EnergyMatch contribution score.",
                        "Επιλέξιμα μέλη είναι οι χρήστες με θετική βαθμολογία συνεισφοράς EnergyMatch."
                    );
                    ?>
                </p>
            </div>
            <a
                href="create_community.php"
                class="create-community-btn"
            >
                <?php echo emText(
                    "Manual Community",
                    "Χειροκίνητη Κοινότητα"
                ); ?>
            </a>
        </div>
        <div class="toolbar-bottom">
            <div class="match-view-area">
                <div class="match-view-buttons">
                    <button
                        type="button"
                        id="showAllMembers"
                        class="view-mode-btn <?php echo $initial_view === "all" ? "active-view-btn" : ""; ?>"
                    >
                        <?php echo emText(
                            "All Eligible Members",
                            "Όλα τα Επιλέξιμα Μέλη"
                        ); ?>
                    </button>
                    <button
                        type="button"
                        id="findCommunityBtn"
                        class="view-mode-btn <?php echo $initial_view === "community" ? "active-view-btn" : ""; ?>"
                    >
                        <?php echo emText(
                            "Find My Energy Communities",
                            "Βρες τις Ενεργειακές μου Κοινότητες"
                        ); ?>
                    </button>
                </div>
            </div>
            <div
                class="filters <?php echo $initial_view === "community" ? "community-hidden" : ""; ?>"
                id="memberFilters"
            >

                <!-- SEARCH + HISTORY -->
                <div class="search-history-wrapper" id="searchHistoryWrapper">
                    <input
                        type="text"
                        id="userSearch"
                        placeholder="<?php echo emText("Search by name or username", "Αναζήτηση με όνομα ή username"); ?>"
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        class="search-history-toggle"
                        id="searchHistoryToggle"
                        title="<?php echo t("search_history_show"); ?>"
                        aria-label="<?php echo t("search_history_show"); ?>"
                        aria-expanded="false"
                        aria-controls="searchHistoryPanel"
                    >
                        <i data-lucide="history"></i>
                    </button>
                    <div
                        class="search-history-panel"
                        id="searchHistoryPanel"
                        hidden
                    >
                        <div class="search-history-title">
                            <?php echo t("search_history_recent"); ?>
                        </div>
                        <ul class="search-history-list" id="searchHistoryList"></ul>
                        <div class="search-history-empty" id="searchHistoryEmpty">
                            <?php echo t("search_history_empty"); ?>
                        </div>
                        <button
                            type="button"
                            class="search-history-clear"
                            id="searchHistoryClear"
                        >
                            <?php echo t("search_history_clear"); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (($creator_active_community_id ?? null) !== null): ?>

        <!-- USER ALREADY IN AN ACTIVE COMMUNITY -->
        <div class="profile-warning">
            <strong>
                <?php echo t("already_in_active_community_title"); ?>
            </strong>
            <p>
                <?php echo t("already_in_active_community_text"); ?>
            </p>
            <a href="community.php">
                <?php echo t("go_to_my_community"); ?>
            </a>
        </div>
    <?php elseif ($community_algorithm_error !== null): ?>
        <div class="profile-warning">
            <strong>
                <?php echo emText(
                    "Matching cannot run yet.",
                    "Η αντιστοίχιση δεν μπορεί να εκτελεστεί ακόμη."
                ); ?>
            </strong>
            <p>
                <?php echo safe($community_algorithm_error); ?>
            </p>
            <a href="profile.php">
                <?php echo emText(
                    "Update Profile",
                    "Ενημέρωση Προφίλ"
                ); ?>
            </a>
        </div>
    <?php else: ?>
        <div class="algorithm-note">
            <strong>
                <?php echo emText(
                    "How matching works",
                    "Πώς λειτουργεί η αντιστοίχιση"
                ); ?>
            </strong>
            <p>
                <?php
                echo emText(
                    "Each proposed community contains exactly 5 members. Fair Share is calculated from the relative EnergyMatch contribution scores, and a proposal is accepted only when every member's Fair Share is at least their Minimum Share.",
                    "Κάθε προτεινόμενη κοινότητα έχει ακριβώς 5 μέλη. Το Fair Share υπολογίζεται από τις σχετικές βαθμολογίες συνεισφοράς EnergyMatch και μια πρόταση γίνεται αποδεκτή μόνο όταν το Fair Share όλων είναι τουλάχιστον ίσο με το Minimum Share τους."
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- =========================================
         ALL ELIGIBLE MEMBERS
    ========================================== -->
    <div
        id="membersSection"
        class="<?php echo $initial_view === "community" ? "members-section-hidden" : ""; ?>"
    >
        <div
            class="matches-grid"
            id="matchesGrid"
        >

            <?php if ($matches_count > 0): ?>

                <?php foreach ($matches as $row): ?>
                    <div
                        class="match-card"
                        data-user-id="<?php echo (int) $row["user_id"]; ?>"
                        data-name="<?php echo safe($row["name"] . " " . $row["surname"]); ?>"
                        data-username="<?php echo safe($row["username"] ?? ""); ?>"
                        data-region="<?php echo safe($row["location"] ?? ""); ?>"
                    >
                        <div class="card-header">
                            <div>
                                <h3>
                                    <?php echo safe(
                                        $row["name"] . " " . $row["surname"]
                                    ); ?>
                                </h3>
                                <?php if (($row["username"] ?? "") !== ""): ?>
                                    <span class="member-username">@<?php echo safe($row["username"]); ?></span>
                                <?php endif; ?>
                                <span class="member-location">
                                    <i data-lucide="map-pin"></i>
                                    <?php
                                    echo safe(
                                        trim((string) ($row["location"] ?? "")) !== ""
                                            ? $row["location"]
                                            : emText(
                                                "Location not provided",
                                                "Δεν έχει δηλωθεί περιοχή"
                                            )
                                    );
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="member-value-row">
                            <div class="member-value-box">
                                <span>
                                    <?php echo emText(
                                        "Contribution Score",
                                        "Βαθμολογία Συνεισφοράς"
                                    ); ?>
                                </span>
                                <strong>
                                    <?php echo emNumber((float) $row["contribution_value"], 2); ?>
                                </strong>
                            </div>
                            <div class="member-value-box">
                                <span>
                                    <?php echo emText(
                                        "Minimum Share",
                                        "Ελάχιστο Ποσοστό"
                                    ); ?>
                                </span>
                                <strong>
                                    <?php echo emNumber((float) $row["minimum_share"], 2); ?>%
                                </strong>
                            </div>
                        </div>
                        <div class="contribution-tags">
                            <?php echo emContributionTags($row, $landEnergyByUser[(int) $row["user_id"]] ?? null); ?>
                        </div>
                        <a
                            href="view_profile.php?id=<?php echo (int) $row["user_id"]; ?>"
                            class="view-profile-link"
                        >
                            <button type="button">
                                <?php echo t("view_profile"); ?>
                            </button>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="handshake"></i></div>
                    <h2>
                        <?php echo emText(
                            "No eligible members yet",
                            "Δεν υπάρχουν ακόμη επιλέξιμα μέλη"
                        ); ?>
                    </h2>
                    <p>
                        <?php echo emText(
                            "Members need a saved contribution with a positive EnergyMatch contribution score.",
                            "Τα μέλη πρέπει να έχουν αποθηκευμένη συνεισφορά με θετική βαθμολογία EnergyMatch."
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <div
            id="noFilterResults"
            class="empty-state"
            style="display:none; max-width:700px; margin:30px auto;"
        >
            <div class="empty-icon"><i data-lucide="search-x"></i></div>
            <h2>
                <?php echo emText(
                    "No matching members found",
                    "Δεν βρέθηκαν μέλη"
                ); ?>
            </h2>
            <p>
                <?php echo emText(
                    "Try changing the search filters.",
                    "Δοκίμασε να αλλάξεις τα φίλτρα αναζήτησης."
                ); ?>
            </p>
        </div>
        <div
            class="pagination"
            id="pagination"
        >
            <button
                type="button"
                id="previousPage"
                class="pagination-arrow"
            >
                <i data-lucide="arrow-left"></i> <?php echo t("previous"); ?>
            </button>
            <div
                class="pagination-pages"
                id="paginationPages"
            ></div>
            <button
                type="button"
                id="nextPage"
                class="pagination-arrow"
            >
                <?php echo t("next"); ?> <i data-lucide="arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- =========================================
         COMMUNITY PROPOSALS
    ========================================== -->

    <section
        id="communitySection"
        class="community-section <?php echo $initial_view === "community" ? "active" : ""; ?>"
    >

        <?php if ($currentMember === null): ?>
            <div class="community-empty">
                <strong>
                    <?php echo emText(
                        "Complete your contribution profile first.",
                        "Ολοκλήρωσε πρώτα το προφίλ συνεισφοράς σου."
                    ); ?>
                </strong>
            </div>
        <?php elseif (count($community_proposals) === 0): ?>
            <div class="community-empty">
                <strong>
                    <?php echo emText(
                        "No valid 5-member community was found in this result set.",
                        "Δεν βρέθηκε έγκυρη 5μελής κοινότητα σε αυτό το σύνολο αποτελεσμάτων."
                    ); ?>
                </strong>
                <p>
                    <?php
                    if (count($eligibleMembers) < 5) {
                        echo emText(
                            "At least 5 eligible users with positive contribution scores are required.",
                            "Χρειάζονται τουλάχιστον 5 επιλέξιμοι χρήστες με θετική βαθμολογία συνεισφοράς."
                        );
                    } else {
                        echo emText(
                            "The available combinations do not currently satisfy every member's Minimum Share constraint.",
                            "Οι διαθέσιμοι συνδυασμοί δεν ικανοποιούν αυτή τη στιγμή τον περιορισμό Minimum Share όλων των μελών."
                        );
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>

            <?php foreach ($community_proposals as $proposalIndex => $proposal): ?>
                <?php
                $absoluteNumber =
                    $community_offset + $proposalIndex + 1;
                ?>
                <?php
                /* The first proposal is always the one with the largest
                   minimum fairness margin (maximin), see community_algorithm.php */
                $isBestProposal = $absoluteNumber === 1;
                ?>
                <article class="community-proposal<?php echo $isBestProposal ? " is-best-proposal" : ""; ?>">
                    <div class="community-proposal-header">
                        <div>
                            <?php if ($isBestProposal): ?>
                                <span class="best-proposal-badge">
                                    <i data-lucide="star"></i>
                                    <?php echo t("best_proposal_badge"); ?>
                                </span>
                            <?php endif; ?>
                            <h2>
                                <?php echo emText(
                                    "Community Proposal",
                                    "Πρόταση Κοινότητας"
                                ); ?>
                                #<?php echo $absoluteNumber; ?>
                            </h2>
                            <p>
                                <?php echo emText(
                                    "Creator + 4 compatible members under the Fair Share rule.",
                                    "Δημιουργός + 4 συμβατά μέλη σύμφωνα με τον κανόνα Fair Share."
                                ); ?>
                            </p>
                            <?php if ($isBestProposal): ?>
                                <p class="best-proposal-note">
                                    <?php echo t("best_proposal_note"); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <span class="valid-badge">
                            <i data-lucide="badge-check"></i>
                            <?php echo emText(
                                "VALID",
                                "ΕΓΚΥΡΗ"
                            ); ?>
                        </span>
                    </div>
                    <div class="community-summary">
                        <div class="summary-box">
                            <span>
                                <?php echo emText(
                                    "Members",
                                    "Μέλη"
                                ); ?>
                            </span>
                            <strong>5</strong>
                        </div>
                        <div class="summary-box">
                            <span
                                class="metric-info"
                                tabindex="0"
                                data-tooltip="<?php echo safe(emText(
                                    "The sum of the EnergyMatch contribution scores of all 5 members.",
                                    "Το άθροισμα των βαθμολογιών συνεισφοράς EnergyMatch και των 5 μελών."
                                )); ?>"
                            >
                                <?php echo emText(
                                    "Total Group Score",
                                    "Συνολική Βαθμολογία Ομάδας"
                                ); ?>
                                <i class="info-dot">i</i>
                            </span>
                            <strong
                                class="metric-info"
                                tabindex="0"
                                data-tooltip="<?php echo safe(emText(
                                    "This is the total EnergyMatch contribution score of the 5-member team, not a monetary amount.",
                                    "Αυτή είναι η συνολική βαθμολογία συνεισφοράς EnergyMatch της 5μελούς ομάδας, όχι χρηματικό ποσό."
                                )); ?>"
                            >
                                <?php echo emNumber((float) $proposal["total_group_value"], 2); ?>
                            </strong>
                        </div>
                        <div class="summary-box">
                            <span
                                class="metric-info"
                                tabindex="0"
                                data-tooltip="<?php echo safe(emText(
                                    "The sum of the minimum participation percentages requested by all 5 members. It must not exceed 100%.",
                                    "Το άθροισμα των ελάχιστων ποσοστών συμμετοχής που ζητούν και τα 5 μέλη. Δεν πρέπει να ξεπερνά το 100%."
                                )); ?>"
                            >
                                <?php echo emText(
                                    "Sum of Minimum Shares",
                                    "Άθροισμα Minimum Shares"
                                ); ?>
                                <i class="info-dot">i</i>
                            </span>
                            <strong
                                class="metric-info"
                                tabindex="0"
                                data-tooltip="<?php echo safe(emText(
                                    "This percentage combines the Minimum Share requirements of all members in this proposal.",
                                    "Αυτό το ποσοστό συνδυάζει τα Minimum Share που ζητούν όλα τα μέλη αυτής της πρότασης."
                                )); ?>"
                            >
                                <?php echo emNumber((float) $proposal["total_minimum_share"], 2); ?>%
                            </strong>
                        </div>
                        <div class="summary-box">
                            <span
                                class="metric-info"
                                tabindex="0"
                                data-tooltip="<?php echo safe(emText(
                                    "The smallest Margin among the 5 members. Higher values mean every member is further above their requested minimum.",
                                    "Το μικρότερο Margin ανάμεσα στα 5 μέλη. Όσο μεγαλύτερο είναι, τόσο περισσότερο όλα τα μέλη βρίσκονται πάνω από το ελάχιστο ποσοστό που ζήτησαν."
                                )); ?>"
                            >
                                <?php echo emText(
                                    "Minimum Fairness Margin",
                                    "Ελάχιστο Περιθώριο Fairness"
                                ); ?>
                                <i class="info-dot">i</i>
                            </span>
                            <strong
                                class="metric-info"
                                tabindex="0"
                                data-tooltip="<?php echo safe(emText(
                                    "This is the lowest individual Margin in the team and is used to rank valid proposals.",
                                    "Αυτό είναι το χαμηλότερο ατομικό Margin στην ομάδα και χρησιμοποιείται για την κατάταξη των έγκυρων προτάσεων."
                                )); ?>"
                            >
                                +<?php echo emNumber((float) $proposal["minimum_fairness_margin"], 2); ?>%
                            </strong>
                            <small class="summary-box-note">
                                <?php echo emText(
                                    "Used to rank valid communities",
                                    "Χρησιμοποιείται για την κατάταξη των έγκυρων κοινοτήτων"
                                ); ?>
                            </small>
                        </div>
                    </div>
                    <div class="community-members-table-wrap">
                        <table class="community-members-table">
                            <thead>
                                <tr>
                                    <th>
                                        <?php echo emText("Member", "Μέλος"); ?>
                                    </th>
                                    <th>
                                        <span
                                            class="metric-info"
                                            tabindex="0"
                                            data-tooltip="<?php echo safe(emText(
                                                "The EnergyMatch contribution score of this member, not necessarily a monetary amount.",
                                                "Η βαθμολογία συνεισφοράς EnergyMatch αυτού του μέλους, όχι απαραίτητα χρηματικό ποσό."
                                            )); ?>"
                                        >
                                            <?php echo emText(
                                                "EnergyMatch Contribution Score",
                                                "Βαθμολογία Συνεισφοράς EnergyMatch"
                                            ); ?>
                                            <i class="info-dot">i</i>
                                        </span>
                                    </th>
                                    <th>
                                        <span
                                            class="metric-info"
                                            tabindex="0"
                                            data-tooltip="<?php echo safe(emText(
                                                "The minimum participation percentage this member is willing to accept.",
                                                "Το ελάχιστο ποσοστό συμμετοχής που είναι διατεθειμένο να αποδεχτεί αυτό το μέλος."
                                            )); ?>"
                                        >
                                            <?php echo emText(
                                                "Minimum Share",
                                                "Minimum Share"
                                            ); ?>
                                            <i class="info-dot">i</i>
                                        </span>
                                    </th>
                                    <th>
                                        <span
                                            class="metric-info"
                                            tabindex="0"
                                            data-tooltip="<?php echo safe(emText(
                                                "The member's calculated participation share: their contribution score divided by the Total Group Score.",
                                                "Το υπολογισμένο ποσοστό συμμετοχής του μέλους: η βαθμολογία συνεισφοράς του διαιρεμένη με τη συνολική βαθμολογία ομάδας."
                                            )); ?>"
                                        >
                                            Fair Share
                                            <i class="info-dot">i</i>
                                        </span>
                                    </th>
                                    <th>
                                        <span
                                            class="metric-info"
                                            tabindex="0"
                                            data-tooltip="<?php echo safe(emText(
                                                "Fair Share minus Minimum Share. A positive Margin means the member receives more than their requested minimum.",
                                                "Fair Share μείον Minimum Share. Θετικό Margin σημαίνει ότι το μέλος λαμβάνει μεγαλύτερο ποσοστό από το ελάχιστο που ζήτησε."
                                            )); ?>"
                                        >
                                            <?php echo emText(
                                                "Margin",
                                                "Περιθώριο"
                                            ); ?>
                                            <i class="info-dot">i</i>
                                        </span>
                                    </th>
                                    <th>
                                        <?php echo emText(
                                            "Result",
                                            "Αποτέλεσμα"
                                        ); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php foreach ($proposal["members"] as $member): ?>
                                    <tr>
                                        <td>
                                            <span class="member-name-main">
                                                <?php echo safe(
                                                    $member["name"] . " " . $member["surname"]
                                                ); ?>
                                            </span>

                                            <?php if ((int) $member["user_id"] === $user_id): ?>
                                                <span class="you-badge">
                                                    <?php echo emText(
                                                        "You",
                                                        "Εσύ"
                                                    ); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if (trim((string) $member["location"]) !== ""): ?>
                                                <div class="member-location">
                                                    <i data-lucide="map-pin"></i>
                                                    <?php echo safe($member["location"]); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php $previewItems = contributionPreviewItems($member); ?>

                                            <?php if (!empty($previewItems)): ?>
                                                <div class="contribution-preview">

                                                    <?php foreach ($previewItems as $previewItem): ?>
                                                        <span class="contribution-preview-item">
                                                            <i data-lucide="<?php echo $previewItem["icon"]; ?>"></i>
                                                            <?php echo safe($previewItem["text"]); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span
                                                class="metric-info contribution-value-main"
                                                tabindex="0"
                                                data-tooltip="<?php echo safe(emText(
                                                    "The EnergyMatch contribution score of this member. The preview under the member's name shows what they offer.",
                                                    "Η βαθμολογία συνεισφοράς EnergyMatch αυτού του μέλους. Η προεπισκόπηση δείχνει τι προσφέρει."
                                                )); ?>"
                                            >
                                                <?php echo emNumber((float) $member["contribution_value"], 2); ?>
                                            </span>
                                            <?php $proposalLandEnergy = $landEnergyByUser[(int) $member["user_id"]] ?? null; ?>

                                            <?php if ($proposalLandEnergy !== null): ?>
                                                <div class="land-energy-table">
                                                    <span>Solar Score: <?php echo $proposalLandEnergy["solar_score"] !== null ? emNumber($proposalLandEnergy["solar_score"], 2) . "%" : emText("Not yet calculated", "Δεν έχει υπολογιστεί"); ?></span>
                                                    <span><?php echo t("land_value_title"); ?>:</span>
                                                    <strong><?php echo $proposalLandEnergy["land_value"] !== null ? emNumber($proposalLandEnergy["land_value"], 2) . " €" : t("land_not_calculated"); ?></strong>

                                                    <?php if ($proposalLandEnergy["location_factor"] !== null): ?>
                                                        <span>f = <?php echo emNumber($proposalLandEnergy["location_factor"], 3); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span
                                                class="metric-info"
                                                tabindex="0"
                                                data-tooltip="<?php echo safe(emText(
                                                    "The minimum participation share this member is willing to accept.",
                                                    "Το ελάχιστο ποσοστό συμμετοχής που είναι διατεθειμένο να αποδεχτεί αυτό το μέλος."
                                                )); ?>"
                                            >
                                                <?php echo emNumber((float) $member["minimum_share"], 2); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <strong
                                                class="metric-info"
                                                tabindex="0"
                                                data-tooltip="<?php echo safe(emText(
                                                    "This member's calculated share based on their contribution score relative to the total group score.",
                                                    "Το υπολογισμένο ποσοστό αυτού του μέλους με βάση τη βαθμολογία συνεισφοράς του σε σχέση με τη συνολική βαθμολογία ομάδας."
                                                )); ?>"
                                            >
                                                <?php echo emNumber((float) $member["fair_share"], 2); ?>%
                                            </strong>
                                            <span class="share-bar" aria-hidden="true">
                                                <span style="width: <?php echo min(100, max(0, (float) $member["fair_share"])); ?>%"></span>
                                                <em style="left: <?php echo min(100, max(0, (float) $member["minimum_share"])); ?>%"></em>
                                            </span>
                                        </td>
                                        <td>
                                            <strong
                                                class="metric-info margin-positive"
                                                tabindex="0"
                                                data-tooltip="<?php echo safe(emText(
                                                    "The difference between this member's Fair Share and Minimum Share.",
                                                    "Η διαφορά ανάμεσα στο Fair Share και το Minimum Share αυτού του μέλους."
                                                )); ?>"
                                            >
                                                +<?php echo emNumber((float) $member["fairness_margin"], 2); ?>%
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="pass-badge">
                                                <i data-lucide="check"></i>
                                                PASS
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="proposal-action-bar">
                        <div class="proposal-action-copy">
                            <strong>
                                <?php echo emText(
                                    "Ready to create this community?",
                                    "Έτοιμος να δημιουργήσεις αυτή την κοινότητα;"
                                ); ?>
                            </strong>
                            <span>
                                <?php echo emText(
                                    "The other 4 members will receive an invitation and the community will remain pending until they respond.",
                                    "Τα άλλα 4 μέλη θα λάβουν πρόσκληση και η κοινότητα θα παραμείνει σε αναμονή μέχρι να απαντήσουν."
                                ); ?>
                            </span>
                        </div>

                        <form
                            method="POST"
                            action="create_community.php"
                            class="choose-community-form"
                        >
                            <?php echo csrfField(); ?>

                            <?php foreach ($proposal["members"] as $proposalMember): ?>

                                <?php if ((int) $proposalMember["user_id"] !== $user_id): ?>
                                    <input
                                        type="hidden"
                                        name="selected_members[]"
                                        value="<?php echo (int) $proposalMember["user_id"]; ?>"
                                    >
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <button
                                type="submit"
                                class="choose-community-btn"
                            >
                                <?php echo emText(
                                    "Choose This Community",
                                    "Επίλεξε Αυτή την Κοινότητα"
                                ); ?>
                                <i data-lucide="chevron-right"></i>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="community-nav">
                <div class="community-nav-side">

                    <?php if ($previous_community_offset !== null): ?>
                        <a
                            class="secondary"
                            href="matches.php?view=community&community_offset=<?php echo (int) $previous_community_offset; ?>"
                        >
                            <i data-lucide="arrow-left"></i>
                            <?php echo emText(
                                "Previous Communities",
                                "Προηγούμενες Κοινότητες"
                            ); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="community-range">
                    <?php
                    $from = $community_offset + 1;
                    $to = $community_offset + count($community_proposals);
                    echo safe(
                        emText(
                            "Showing proposals {$from}-{$to} of {$total_valid_communities}",
                            "Εμφάνιση προτάσεων {$from}-{$to} από {$total_valid_communities}"
                        )
                    );
                    ?>
                </div>
                <div class="community-nav-side">

                    <?php if ($has_more_communities && $next_community_offset !== null): ?>
                        <a
                            href="matches.php?view=community&community_offset=<?php echo (int) $next_community_offset; ?>"
                        >
                            <?php echo emText(
                                "Find More Communities",
                                "Βρες Περισσότερες Κοινότητες"
                            ); ?>
                            <i data-lucide="arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php include "chatbot.php"; ?>

<script src="js/chatbot.js?v=<?php echo ASSET_VERSION; ?>"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const tooltip = document.createElement("div");
    tooltip.className = "floating-metric-tooltip";
    document.body.appendChild(tooltip);
    let activeTarget = null;

    function positionTooltip(target) {
        if (!target || !tooltip.classList.contains("visible")) {
            return;
        }
        const rect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const gap = 9;
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        left = Math.max(12, Math.min(left, window.innerWidth - tooltipRect.width - 12));
        let top = rect.top - tooltipRect.height - gap;
        if (top < 10) {
            top = rect.bottom + gap;
        }
        tooltip.style.left = left + "px";
        tooltip.style.top = top + "px";
    }

    function showTooltip(target) {
        const text = target.getAttribute("data-tooltip");
        if (!text) {
            return;
        }
        activeTarget = target;
        tooltip.textContent = text;
        tooltip.classList.add("visible");
        positionTooltip(target);
    }

    function hideTooltip(target) {
        if (target && activeTarget !== target) {
            return;
        }
        activeTarget = null;
        tooltip.classList.remove("visible");
    }
    document.querySelectorAll(".metric-info[data-tooltip]").forEach(function (item) {
        item.addEventListener("mouseenter", function () {
            showTooltip(item);
        });
        item.addEventListener("mouseleave", function () {
            hideTooltip(item);
        });
        item.addEventListener("focus", function () {
            showTooltip(item);
        });
        item.addEventListener("blur", function () {
            hideTooltip(item);
        });
    });
    window.addEventListener("scroll", function () {
        if (activeTarget) {
            positionTooltip(activeTarget);
        }
    }, true);
    window.addEventListener("resize", function () {
        if (activeTarget) {
            positionTooltip(activeTarget);
        }
    });
});
const initialView = <?php echo json_encode($initial_view); ?>;
const showAllButton =
    document.getElementById("showAllMembers");
const findCommunityButton =
    document.getElementById("findCommunityBtn");
const membersSection =
    document.getElementById("membersSection");
const communitySection =
    document.getElementById("communitySection");
const memberFilters =
    document.getElementById("memberFilters");
const searchInput =
    document.getElementById("userSearch");
const regionFilter =
    document.getElementById("regionFilter");
const previousPageButton =
    document.getElementById("previousPage");
const nextPageButton =
    document.getElementById("nextPage");
const pagination =
    document.getElementById("pagination");
const paginationPages =
    document.getElementById("paginationPages");
const noFilterResults =
    document.getElementById("noFilterResults");
const matchCards = Array.from(
    document.querySelectorAll(".match-card")
);
const membersPerPage = 6;
let currentPage = 1;

function normalizeText(value)
{
    return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
}

function getFilteredCards()
{
    const searchValue =
        normalizeText(searchInput ? searchInput.value : "");
    const regionValue =
        normalizeText(regionFilter ? regionFilter.value : "");
    return matchCards.filter(function(card) {
        const userName =
            normalizeText(card.dataset.name);
        const userRegion =
            normalizeText(card.dataset.region);
        const userUsername =
            normalizeText(card.dataset.username).replace(/^@/, "");
        const searchUsername = searchValue.replace(/^@/, "");
        const matchesSearch =
            searchValue === ""
            || userName.includes(searchValue)
            || (searchUsername !== "" && userUsername.includes(searchUsername));
        const matchesRegion =
            regionValue === ""
            || userRegion === regionValue;
        return matchesSearch && matchesRegion;
    });
}

function renderPagination(totalPages)
{
    if (!pagination || !paginationPages) {
        return;
    }
    paginationPages.innerHTML = "";
    if (totalPages <= 1) {
        pagination.style.display = "none";
        return;
    }
    pagination.style.display = "flex";
    for (
        let pageNumber = 1;
        pageNumber <= totalPages;
        pageNumber++
    ) {
        const pageButton =
            document.createElement("button");
        pageButton.type = "button";
        pageButton.className = "pagination-number";
        pageButton.textContent = pageNumber;
        if (pageNumber === currentPage) {
            pageButton.classList.add("active-page");
        }
        pageButton.addEventListener(
            "click",
            function() {
                currentPage = pageNumber;
                renderMembers();
                const grid =
                    document.getElementById("matchesGrid");
                if (grid) {
                    grid.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
                }
            }
        );
        paginationPages.appendChild(pageButton);
    }
    previousPageButton.disabled =
        currentPage === 1;
    nextPageButton.disabled =
        currentPage === totalPages;
}

function renderMembers()
{
    const filteredCards = getFilteredCards();
    matchCards.forEach(function(card) {
        card.style.display = "none";
    });
    if (noFilterResults) {
        noFilterResults.style.display =
            filteredCards.length === 0
                ? "block"
                : "none";
    }
    const totalPages = Math.max(
        1,
        Math.ceil(filteredCards.length / membersPerPage)
    );
    if (currentPage > totalPages) {
        currentPage = totalPages;
    }
    const startIndex =
        (currentPage - 1) * membersPerPage;
    const endIndex =
        startIndex + membersPerPage;
    filteredCards
        .slice(startIndex, endIndex)

        .forEach(function(card) {
            card.style.display = "";
        });
    renderPagination(totalPages);
}

function showMembersView()
{
    membersSection.classList.remove(
        "members-section-hidden"
    );
    communitySection.classList.remove("active");
    if (memberFilters) {
        memberFilters.classList.remove(
            "community-hidden"
        );
    }
    showAllButton.classList.add(
        "active-view-btn"
    );
    findCommunityButton.classList.remove(
        "active-view-btn"
    );
    renderMembers();
}

function showCommunityView()
{
    membersSection.classList.add(
        "members-section-hidden"
    );
    communitySection.classList.add("active");
    if (memberFilters) {
        memberFilters.classList.add(
            "community-hidden"
        );
    }
    showAllButton.classList.remove(
        "active-view-btn"
    );
    findCommunityButton.classList.add(
        "active-view-btn"
    );
}
if (showAllButton) {
    showAllButton.addEventListener(
        "click",
        showMembersView
    );
}
if (findCommunityButton) {
    findCommunityButton.addEventListener(
        "click",
        showCommunityView
    );
}
if (searchInput) {
    searchInput.addEventListener(
        "input",
        function() {
            currentPage = 1;
            renderMembers();
        }
    );
}
if (regionFilter) {
    regionFilter.addEventListener(
        "change",
        function() {
            currentPage = 1;
            renderMembers();
        }
    );
}
if (previousPageButton) {
    previousPageButton.addEventListener(
        "click",
        function() {
            if (currentPage > 1) {
                currentPage--;
                renderMembers();
            }
        }
    );
}
if (nextPageButton) {
    nextPageButton.addEventListener(
        "click",
        function() {
            const totalPages = Math.ceil(
                getFilteredCards().length
                / membersPerPage
            );
            if (currentPage < totalPages) {
                currentPage++;
                renderMembers();
            }
        }
    );
}
renderMembers();
if (initialView === "community") {
    showCommunityView();
}

/* =========================================
   SEARCH HISTORY (stored in the browser, per user)
   A search is saved on Enter, or after the user
   stops typing for 1.5s (min. 2 characters).
========================================= */
(function () {
    const HISTORY_KEY = "energymatch_search_history_<?php echo (int) $user_id; ?>";
    const HISTORY_LIMIT = 10;
    const SAVE_DELAY_MS = 1500;
    const MIN_LENGTH = 2;
    const wrapper = document.getElementById("searchHistoryWrapper");
    const toggle = document.getElementById("searchHistoryToggle");
    const panel = document.getElementById("searchHistoryPanel");
    const list = document.getElementById("searchHistoryList");
    const emptyMessage = document.getElementById("searchHistoryEmpty");
    const clearButton = document.getElementById("searchHistoryClear");
    if (!wrapper || !toggle || !panel || !list || !searchInput) {
        return;
    }
    const deleteLabel = <?php echo json_encode(t("search_history_remove")); ?>;
    let saveTimer = null;

    /* localStorage can fail (private mode, blocked storage),
       so every access is wrapped in try/catch. */

    function loadHistory() {
        try {
            const raw = localStorage.getItem(HISTORY_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function storeHistory(history) {
        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        } catch (error) {
            /* Storage unavailable: history simply isn't kept. */
        }
    }

    function saveSearch(term) {
        const cleanTerm = term.trim();
        if (cleanTerm.length < MIN_LENGTH) {
            return;
        }

        /* Remove duplicates (case-insensitive), newest goes first. */
        const history = loadHistory().filter(function (item) {
            return item.toLowerCase() !== cleanTerm.toLowerCase();
        });
        history.unshift(cleanTerm);
        storeHistory(history.slice(0, HISTORY_LIMIT));
    }

    function removeSearch(term) {
        storeHistory(
            loadHistory().filter(function (item) {
                return item !== term;
            })
        );
        renderHistory();
    }

    function renderHistory() {
        const history = loadHistory();
        list.innerHTML = "";
        history.forEach(function (term) {
            const item = document.createElement("li");
            item.className = "search-history-item";
            const termButton = document.createElement("button");
            termButton.type = "button";
            termButton.className = "search-history-term";
            termButton.textContent = term;   /* textContent: safe against HTML injection */
            termButton.addEventListener("click", function () {
                searchInput.value = term;
                currentPage = 1;
                renderMembers();
                saveSearch(term);
                closePanel();
                searchInput.focus();
            });
            const removeButton = document.createElement("button");
            removeButton.type = "button";
            removeButton.className = "search-history-remove";
            removeButton.innerHTML = '<i data-lucide="x"></i>';
            removeButton.title = deleteLabel;
            removeButton.setAttribute("aria-label", deleteLabel + ": " + term);
            removeButton.addEventListener("click", function (event) {
                event.stopPropagation();
                removeSearch(term);
            });
            item.appendChild(termButton);
            item.appendChild(removeButton);
            list.appendChild(item);
        });
        if (window.emRefreshIcons) {
            window.emRefreshIcons();
        }
        const hasHistory = history.length > 0;
        emptyMessage.hidden = hasHistory;
        clearButton.hidden = !hasHistory;
    }

    function openPanel() {
        renderHistory();
        panel.hidden = false;
        toggle.setAttribute("aria-expanded", "true");
        toggle.classList.add("active");
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute("aria-expanded", "false");
        toggle.classList.remove("active");
    }
    toggle.addEventListener("click", function (event) {
        event.stopPropagation();
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });
    clearButton.addEventListener("click", function (event) {
        event.stopPropagation();
        storeHistory([]);
        renderHistory();
    });

    /* Save after a pause in typing. */
    searchInput.addEventListener("input", function () {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            saveSearch(searchInput.value);
        }, SAVE_DELAY_MS);
    });

    /* Save immediately on Enter; close panel on Escape. */
    searchInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
            event.preventDefault();
            clearTimeout(saveTimer);
            saveSearch(searchInput.value);
            closePanel();
        }
        if (event.key === "Escape") {
            closePanel();
        }
    });

    /* Close when clicking anywhere outside the search box. */
    document.addEventListener("click", function (event) {
        if (!wrapper.contains(event.target)) {
            closePanel();
        }
    });
})();
</script>
</body>
</html>
