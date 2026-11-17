<?php
session_start();
require_once __DIR__ . "/remember_me.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}

include "lang.php";
require_once "db.php";

$username = $_SESSION["username"];
$userId = (int) $_SESSION["user_id"];
$displayName = trim((string) ($_SESSION["name"] ?? "")) !== "" ? $_SESSION["name"] : $username;

/* =========================================
   DATA FOR THE DASHBOARD
   (read only - nothing is changed here)
========================================= */

/* Profile: location + minimum share */
$profileLocation = "";
$profileMinimumShare = null;

$statement = $conn->prepare("SELECT location, minimum_share FROM energy_profiles WHERE user_id = ? LIMIT 1");
$statement->bind_param("i", $userId);
$statement->execute();

if ($row = $statement->get_result()->fetch_assoc()) {
    $profileLocation = trim((string) $row["location"]);
    $profileMinimumShare = $row["minimum_share"] !== null ? (float) $row["minimum_share"] : null;
}

$statement->close();

/* Contributions: total value + kinds */
$totalValue = 0.0;
$contributionKinds = [];

$statement = $conn->prepare("SELECT contribution_type, estimated_value FROM user_contributions WHERE user_id = ?");
$statement->bind_param("i", $userId);
$statement->execute();
$result = $statement->get_result();

while ($row = $result->fetch_assoc()) {
    $totalValue += (float) $row["estimated_value"];
    $contributionKinds[$row["contribution_type"]] = true;
}

$statement->close();

/* Communities of the user */
$communityCounts = [
    "active" => 0,          /* accepted member of an active community */
    "waiting" => 0,         /* accepted, community still pending */
    "invitations" => 0,     /* invited, has not answered yet */
];

$statement = $conn->prepare("
    SELECT c.status, cm.invitation_status, COUNT(*) AS total
    FROM community_members cm
    INNER JOIN communities c ON c.id = cm.community_id
    WHERE cm.user_id = ?
      AND c.status IN ('pending', 'active')
    GROUP BY c.status, cm.invitation_status
");
$statement->bind_param("i", $userId);
$statement->execute();
$result = $statement->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row["status"] === "active" && $row["invitation_status"] === "accepted") {
        $communityCounts["active"] += (int) $row["total"];
    } elseif ($row["status"] === "pending" && $row["invitation_status"] === "accepted") {
        $communityCounts["waiting"] += (int) $row["total"];
    } elseif ($row["status"] === "pending" && $row["invitation_status"] === "pending") {
        $communityCounts["invitations"] += (int) $row["total"];
    }
}

$statement->close();

/* =========================================
   "YOUR STEPS"
   1 profile -> 2 find a community -> 3 invitations -> 4 active community
========================================= */

$hasProfile = count($contributionKinds) > 0;
$hasActive = $communityCounts["active"] > 0;
$hasCommunity = $hasActive || $communityCounts["waiting"] > 0;

$steps = [
    [
        "done" => $hasProfile,
        "icon" => "user-round-check",
        "title" => t("dash_step_profile"),
        "text" => $hasProfile
            ? sprintf(t("dash_step_profile_done"), count($contributionKinds))
            : t("dash_step_profile_todo"),
        "link" => "profile.php",
        "link_text" => $hasProfile ? t("view_profile") : t("dash_step_profile_btn"),
    ],
    [
        "done" => $hasCommunity,
        "icon" => "users-round",
        "title" => t("dash_step_find"),
        "text" => $hasCommunity ? t("dash_step_find_done") : t("dash_step_find_todo"),
        "link" => "matches.php",
        "link_text" => t("find_matches"),
    ],
    [
        "done" => $hasActive,
        "icon" => "mail-check",
        "title" => t("dash_step_invitations"),
        "text" => $communityCounts["invitations"] > 0
            ? sprintf(t("dash_step_invitations_open"), $communityCounts["invitations"])
            : ($communityCounts["waiting"] > 0 ? t("dash_step_invitations_waiting") : t("dash_step_invitations_todo")),
        "link" => $communityCounts["invitations"] > 0 ? "notifications.php" : "accepted_invitations.php",
        "link_text" => $communityCounts["invitations"] > 0 ? t("nav_notifications") : t("nav_accepted_invitations"),
    ],
    [
        "done" => $hasActive,
        "icon" => "sun",
        "title" => t("dash_step_active"),
        "text" => $hasActive ? t("dash_step_active_done") : t("dash_step_active_todo"),
        "link" => "community.php",
        "link_text" => t("nav_my_community"),
    ],
];

/* The current step is the first one that is not done */
$currentStep = count($steps);

foreach ($steps as $index => $step) {
    if (!$step["done"]) {
        $currentStep = $index;
        break;
    }
}

$kindLabels = [
    "capital" => t("dash_kind_capital"),
    "land" => t("dash_kind_land"),
    "manual_labor" => t("dash_kind_labor"),
    "skilled_labor" => t("dash_kind_labor"),
    "equipment" => t("dash_kind_equipment"),
];

$kindText = implode(" · ", array_unique(array_map(function ($kind) use ($kindLabels) {
    return $kindLabels[$kind] ?? $kind;
}, array_keys($contributionKinds))));

$isGreek = ($_SESSION["language"] ?? "en") === "gr";

function dashMoney($value, $isGreek)
{
    return $isGreek
        ? number_format($value, 2, ",", ".") . " €"
        : "€" . number_format($value, 2, ".", ",");
}

function dashPercent($value, $isGreek)
{
    return ($isGreek ? number_format($value, 2, ",", ".") : number_format($value, 2, ".", ",")) . "%";
}

$hour = (int) date("G");
$greeting = $hour < 12 ? t("dash_good_morning") : ($hour < 18 ? t("dash_good_afternoon") : t("dash_good_evening"));
?>

<!DOCTYPE html>

<html lang="<?php echo $isGreek ? "el" : "en"; ?>">

<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="css/chatbot.css?v=<?php echo ASSET_VERSION; ?>">

    <title>EnergyMatch</title>
</head>

<body>

    <?php include "navbar.php"; ?>

    <main class="ui-page dashboard-container">

        <!-- WELCOME -->

        <section class="ui-card welcome-card">

            <div>
                <span class="ui-eyebrow"><?php echo $greeting; ?></span>

                <h1>
                    <?php echo t("welcome"); ?>,
                    <?php echo htmlspecialchars($displayName, ENT_QUOTES, "UTF-8"); ?>
                </h1>

                <p><?php echo t("welcome_text"); ?></p>
            </div>

            <a href="matches.php?view=community" class="ui-btn ui-btn-primary">
                <i data-lucide="zap"></i>
                <?php echo t("dash_find_communities"); ?>
            </a>

        </section>

        <!-- KEY NUMBERS -->

        <section class="dashboard-stats">

            <a href="profile.php" class="ui-card ui-stat ui-stat-hero">
                <small><i data-lucide="wallet"></i><?php echo t("dash_total_value"); ?></small>
                <strong><?php echo dashMoney($totalValue, $isGreek); ?></strong>
                <span><?php echo $kindText !== "" ? htmlspecialchars($kindText, ENT_QUOTES, "UTF-8") : t("dash_no_contributions"); ?></span>
            </a>

            <a href="profile.php" class="ui-card ui-stat">
                <small><i data-lucide="percent"></i><?php echo t("dash_minimum_share"); ?></small>
                <strong><?php echo $profileMinimumShare !== null ? dashPercent($profileMinimumShare, $isGreek) : "—"; ?></strong>
                <span><?php echo t("dash_minimum_share_text"); ?></span>
            </a>

            <a href="profile.php" class="ui-card ui-stat">
                <small><i data-lucide="map-pin"></i><?php echo t("dash_region"); ?></small>
                <strong><?php echo $profileLocation !== "" ? htmlspecialchars($profileLocation, ENT_QUOTES, "UTF-8") : "—"; ?></strong>
                <span><?php echo t("dash_region_text"); ?></span>
            </a>

        </section>

        <!-- YOUR STEPS -->

        <section class="ui-card dashboard-steps">

            <div class="dashboard-steps-header">
                <div>
                    <h2><?php echo t("dash_steps_title"); ?></h2>
                    <p><?php echo t("dash_steps_text"); ?></p>
                </div>

                <span class="ui-badge <?php echo $currentStep === count($steps) ? "ui-badge-ok" : "ui-badge-warn"; ?>">
                    <i data-lucide="<?php echo $currentStep === count($steps) ? "badge-check" : "footprints"; ?>"></i>
                    <?php
                    echo $currentStep === count($steps)
                        ? t("dash_steps_all_done")
                        : sprintf(t("dash_step_of"), $currentStep + 1, count($steps));
                    ?>
                </span>
            </div>

            <ol class="steps-list">

                <?php foreach ($steps as $index => $step): ?>

                    <?php
                    $stateClass = $step["done"] ? "is-done" : ($index === $currentStep ? "is-current" : "");
                    ?>

                    <li class="step-item <?php echo $stateClass; ?>">

                        <span class="step-icon">
                            <i data-lucide="<?php echo $step["done"] ? "check" : $step["icon"]; ?>"></i>
                        </span>

                        <strong><?php echo $step["title"]; ?></strong>

                        <p><?php echo $step["text"]; ?></p>

                        <a href="<?php echo $step["link"]; ?>" class="step-link">
                            <?php echo $step["link_text"]; ?>
                            <i data-lucide="arrow-right"></i>
                        </a>

                    </li>

                <?php endforeach; ?>

            </ol>

        </section>

        <!-- HELP & INFORMATION -->

        <section class="dashboard-help">

            <div class="dashboard-help-header">
                <h2><?php echo t("dashboard_help_title"); ?></h2>
                <p><?php echo t("dashboard_help_text"); ?></p>
            </div>

            <div class="dashboard-help-cards">

                <a href="aboutus.php" class="ui-card help-card">
                    <span class="help-card-icon"><i data-lucide="info"></i></span>
                    <strong><?php echo t("about_us"); ?></strong>
                    <small><?php echo t("dashboard_about_text"); ?></small>
                    <span class="help-card-link"><?php echo t("dashboard_about_btn"); ?> <i data-lucide="arrow-right"></i></span>
                </a>

                <a href="FAQ.php" class="ui-card help-card">
                    <span class="help-card-icon"><i data-lucide="circle-help"></i></span>
                    <strong><?php echo t("faq"); ?></strong>
                    <small><?php echo t("dashboard_faq_text"); ?></small>
                    <span class="help-card-link"><?php echo t("dashboard_faq_btn"); ?> <i data-lucide="arrow-right"></i></span>
                </a>

                <a href="contacts.php" class="ui-card help-card">
                    <span class="help-card-icon"><i data-lucide="mail"></i></span>
                    <strong><?php echo t("contact_us"); ?></strong>
                    <small><?php echo t("dashboard_contact_text"); ?></small>
                    <span class="help-card-link"><?php echo t("dashboard_contact_btn"); ?> <i data-lucide="arrow-right"></i></span>
                </a>

            </div>

        </section>

    </main>

    <?php
    include "chatbot.php";
    ?>

    <script src="js/chatbot.js?v=<?php echo ASSET_VERSION; ?>"></script>

</body>

</html>
