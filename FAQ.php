<?php
session_start();
require_once __DIR__ . "/remember_me.php";
include "lang.php";

/*
 * Questions grouped by topic. Each answer is a list of paragraphs
 * (text keys); "list" adds bullet points under the paragraphs.
 */
$faqGroups = [
    ["title" => "faqn_cat_general", "icon" => "sun", "items" => [
        ["q" => "faqn_q1", "a" => ["faqn_a1"]],
        ["q" => "faqn_q2", "a" => ["faqn_a2"]],
        ["q" => "faqn_q3", "a" => ["faqn_a3"]],
    ]],
    ["title" => "faqn_cat_contrib", "icon" => "coins", "items" => [
        ["q" => "faqn_q4", "a" => ["faqn_a4"], "list" => ["faqn_a4_capital", "faqn_a4_land", "faqn_a4_manual", "faqn_a4_skilled", "faqn_a4_equipment"]],
        ["q" => "faqn_q5", "a" => ["faqn_a5_1", "faqn_a5_2"]],
        ["q" => "faqn_q6", "a" => ["faqn_a6_1", "faqn_a6_2"]],
        ["q" => "faqn_q7", "a" => ["faqn_a7_1", "faqn_a7_2"]],
        ["q" => "faqn_q8", "a" => ["faqn_a8"]],
    ]],
    ["title" => "faqn_cat_fair", "icon" => "scale", "items" => [
        ["q" => "faqn_q9", "a" => ["faqn_a9_1", "faqn_a9_2"]],
        ["q" => "faqn_q10", "a" => ["faqn_a10_1", "faqn_a10_2"]],
        ["q" => "faqn_q11", "a" => ["faqn_a11"]],
        ["q" => "faqn_q12", "a" => ["faqn_a12_1", "faqn_a12_2"]],
        ["q" => "faqn_q13", "a" => ["faqn_a13"]],
    ]],
    ["title" => "faqn_cat_community", "icon" => "users-round", "items" => [
        ["q" => "faqn_q14", "a" => ["faqn_a14"]],
        ["q" => "faqn_q15", "a" => ["faqn_a15"]],
        ["q" => "faqn_q16", "a" => ["faqn_a16"]],
        ["q" => "faqn_q17", "a" => ["faqn_a17"]],
    ]],
    ["title" => "faqn_cat_account", "icon" => "user-round", "items" => [
        ["q" => "faqn_q18", "a" => ["faqn_a18"]],
        ["q" => "faqn_q19", "a" => ["faqn_a19"]],
        ["q" => "faqn_q20", "a" => ["faqn_a20"]],
        ["q" => "faqn_q21", "a" => ["faqn_a21_1", "faqn_a21_2"]],
    ]],
];

/* "{conduct}" inside an answer becomes a link to the Code of conduct */
$conductLink = '<a class="faq-link" href="conduct.php">' . t("conduct_link_text") . '</a>';
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/FAQ.css?v=<?php echo ASSET_VERSION; ?>">

    <title><?php echo t("faq"); ?> - EnergyMatch</title>
</head>
<body>

<section class="hero-section">
    <?php include "navbar.php"; ?>

    <div class="faq-container">

        <div class="faq-header">
            <h1><?php echo t("faqn_title"); ?></h1>
            <p><?php echo t("faqn_intro"); ?></p>
        </div>

        <?php foreach ($faqGroups as $group): ?>
            <h2 class="faq-category">
                <i data-lucide="<?php echo $group["icon"]; ?>"></i>
                <?php echo t($group["title"]); ?>
            </h2>

            <div class="faq-list">
                <?php foreach ($group["items"] as $item): ?>
                    <div class="faq-item">
                        <button type="button" class="faq-question" aria-expanded="false">
                            <span><?php echo t($item["q"]); ?></span>
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <?php foreach ($item["a"] as $paragraph): ?>
                                <p><?php echo str_replace("{conduct}", $conductLink, t($paragraph)); ?></p>
                            <?php endforeach; ?>

                            <?php if (!empty($item["list"])): ?>
                                <ul>
                                    <?php foreach ($item["list"] as $point): ?>
                                        <li><?php echo t($point); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
/* One answer open at a time */
document.querySelectorAll(".faq-question").forEach(function (question) {
    question.addEventListener("click", function () {
        const item = this.parentElement;
        const currentlyOpen = document.querySelector(".faq-item.active");

        if (currentlyOpen && currentlyOpen !== item) {
            currentlyOpen.classList.remove("active");
            currentlyOpen.querySelector(".faq-question").setAttribute("aria-expanded", "false");
        }

        const open = item.classList.toggle("active");
        this.setAttribute("aria-expanded", open ? "true" : "false");
    });
});
</script>

</body>
</html>
