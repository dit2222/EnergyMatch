<?php
session_start();
require_once __DIR__ . "/remember_me.php";
include "lang.php";

$isGreek = ($_SESSION["language"] ?? "en") === "gr";

function aboutNumber($value)
{
    global $isGreek;
    return $isGreek
        ? number_format((float) $value, 2, ",", ".")
        : number_format((float) $value, 2, ".", ",");
}

/*
 * Worked example: the best group of the demo users
 * (values from the current valuation rules, total 20,916.19 €).
 */
$exampleTotal = 20916.19;
$exampleMembers = [
    ["icon" => "coins",     "name" => "abt_ex1_name", "what" => "abt_ex1_what", "value" => 5000.00, "minimum" => 15],
    ["icon" => "hard-hat",  "name" => "abt_ex2_name", "what" => "abt_ex2_what", "value" => 4088.00, "minimum" => 10],
    ["icon" => "wrench",    "name" => "abt_ex3_name", "what" => "abt_ex3_what", "value" => 4121.09, "minimum" => 8],
    ["icon" => "battery",   "name" => "abt_ex4_name", "what" => "abt_ex4_what", "value" => 3169.60, "minimum" => 10],
    ["icon" => "ruler",     "name" => "abt_ex5_name", "what" => "abt_ex5_what", "value" => 4537.50, "minimum" => 12],
];
?>
<!DOCTYPE html>
<html lang="<?php echo $isGreek ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/aboutus.css?v=<?php echo ASSET_VERSION; ?>">

    <title><?php echo t("about_us"); ?> - EnergyMatch</title>
</head>
<body>

<section class="hero-section">
    <?php include "navbar.php"; ?>

    <div class="about-container">

        <!-- HEADER -->
        <div class="about-header">
            <h1><?php echo t("abt_title"); ?></h1>
            <p><?php echo t("abt_intro"); ?></p>
        </div>

        <!-- MISSION / FAIRNESS / VISION -->
        <div class="about-grid">

            <div class="about-card">
                <div class="icon"><i data-lucide="users-round"></i></div>
                <h2><?php echo t("abt_mission_title"); ?></h2>
                <p><?php echo t("abt_mission_1"); ?></p>
                <p><?php echo t("abt_mission_2"); ?></p>
            </div>

            <div class="about-card">
                <div class="icon"><i data-lucide="scale"></i></div>
                <h2><?php echo t("abt_fair_title"); ?></h2>
                <p><?php echo t("abt_fair_1"); ?></p>
                <p><?php echo t("abt_fair_2"); ?></p>
            </div>

            <div class="about-card">
                <div class="icon"><i data-lucide="sun"></i></div>
                <h2><?php echo t("abt_vision_title"); ?></h2>
                <p><?php echo t("abt_vision_1"); ?></p>
                <p><?php echo t("abt_vision_2"); ?></p>
            </div>
        </div>

        <!-- THE IDEA -->
        <div class="story-section">
            <div class="story-content">
                <span class="small-title"><?php echo t("abt_idea_label"); ?></span>
                <h2><?php echo t("abt_idea_title"); ?></h2>
                <p><?php echo t("abt_idea_1"); ?></p>
                <p><?php echo t("abt_idea_2"); ?></p>
                <p class="story-note"><?php echo t("abt_idea_3"); ?></p>
            </div>
        </div>

        <!-- HOW IT WORKS -->
        <div class="how-section">
            <div class="section-title">
                <h2><?php echo t("abt_how_title"); ?></h2>
                <p><?php echo t("abt_how_subtitle"); ?></p>
            </div>

            <div class="steps">
                <?php for ($step = 1; $step <= 4; $step++): ?>
                    <div class="step-card">
                        <div class="step-number"><?php echo $step; ?></div>
                        <h3><?php echo t("abt_step{$step}_title"); ?></h3>
                        <p><?php echo t("abt_step{$step}_text"); ?></p>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- EXAMPLE -->
        <div class="example-section">
            <div class="example-info">
                <span class="small-title"><?php echo t("abt_example_label"); ?></span>
                <h2><?php echo t("abt_example_title"); ?></h2>
                <p><?php echo t("abt_example_intro"); ?></p>
            </div>

            <div class="example-members">
                <?php foreach ($exampleMembers as $member): ?>
                    <?php $fairShare = $member["value"] / $exampleTotal * 100; ?>
                    <div class="member">
                        <span><i data-lucide="<?php echo $member["icon"]; ?>"></i></span>
                        <strong><?php echo t($member["name"]); ?></strong>
                        <p><?php echo t($member["what"]); ?></p>
                        <div class="member-numbers">
                            <small><?php echo t("abt_ex_value"); ?>: <?php echo aboutNumber($member["value"]); ?> €</small>
                            <b>Fair Share <?php echo aboutNumber($fairShare); ?>%</b>
                            <small><?php echo t("abt_ex_asks"); ?> <?php echo $member["minimum"]; ?>%</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="community-result">
                <span><i data-lucide="circle-check"></i></span>
                <div>
                    <strong><?php echo t("abt_example_result_title"); ?></strong>
                    <p><?php echo t("abt_example_result_text"); ?></p>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="about-footer-card">
            <h2><?php echo t("abt_cta_title"); ?></h2>
            <p><?php echo t("abt_cta_text"); ?></p>
            <a href="register_form.php" class="main-btn"><?php echo t("abt_cta_button"); ?></a>
        </div>
    </div>
</section>

</body>
</html>
