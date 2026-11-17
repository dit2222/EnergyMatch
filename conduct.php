<?php
session_start();
require_once __DIR__ . "/remember_me.php";
include "lang.php";
require_once __DIR__ . "/moderation.php";

/*
|--------------------------------------------------------------------------
| CODE OF CONDUCT
|--------------------------------------------------------------------------
| Accepted at registration. The reasons of a report (report_user.php)
| match these rules, and the warnings of moderation.php refer here.
*/
$conductRules = [
    ["icon" => "badge-check",   "title" => "conduct_rule1_title", "text" => "conduct_rule1_text"],
    ["icon" => "heart-handshake", "title" => "conduct_rule2_title", "text" => "conduct_rule2_text"],
    ["icon" => "shield",        "title" => "conduct_rule3_title", "text" => "conduct_rule3_text"],
    ["icon" => "users-round",   "title" => "conduct_rule4_title", "text" => "conduct_rule4_text"],
    ["icon" => "lock",          "title" => "conduct_rule5_title", "text" => "conduct_rule5_text"],
];

/*
 * "Back" button: returns to the page the user came from (FAQ, register,
 * report form, notifications...). Only pages of this site are accepted;
 * otherwise it goes to the home page.
 */
$backHref = "index.php";
$referer = (string) ($_SERVER["HTTP_REFERER"] ?? "");
if ($referer !== "") {
    $refererParts = parse_url($referer);
    $sameHost = ($refererParts["host"] ?? "") === parse_url("http://" . ($_SERVER["HTTP_HOST"] ?? ""), PHP_URL_HOST);
    $refererPage = basename((string) ($refererParts["path"] ?? ""));
    if ($sameHost && preg_match('/^[A-Za-z0-9_]+\.php$/', $refererPage) && $refererPage !== "conduct.php") {
        $backHref = $refererPage . (isset($refererParts["query"]) ? "?" . $refererParts["query"] : "");
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/aboutus.css?v=<?php echo ASSET_VERSION; ?>">

    <title><?php echo t("conduct_title"); ?> - EnergyMatch</title>
</head>
<body>

<section class="hero-section">
    <?php include "navbar.php"; ?>

    <div class="about-container">

        <div class="conduct-back">
            <a href="<?php echo htmlspecialchars($backHref, ENT_QUOTES, "UTF-8"); ?>" class="ui-btn ui-btn-ghost" id="conductBack">
                <i data-lucide="arrow-left"></i>
                <?php echo t("conduct_back"); ?>
            </a>
        </div>

        <div class="about-header">
            <h1><?php echo t("conduct_title"); ?></h1>
            <p><?php echo t("conduct_intro"); ?></p>
        </div>

        <div class="conduct-rules">
            <?php foreach ($conductRules as $number => $rule): ?>
                <div class="about-card conduct-rule">
                    <div class="icon"><i data-lucide="<?php echo $rule["icon"]; ?>"></i></div>
                    <h2><?php echo ($number + 1) . ". " . t($rule["title"]); ?></h2>
                    <p><?php echo t($rule["text"]); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="story-section">
            <div class="story-content">
                <span class="small-title"><?php echo t("conduct_reports_label"); ?></span>
                <h2><?php echo t("conduct_reports_title"); ?></h2>
                <ol class="conduct-steps">
                    <li><?php echo t("conduct_step1"); ?></li>
                    <li><?php echo t("conduct_step2"); ?></li>
                    <li><?php echo str_replace("{limit}", (string) EM_WARNINGS_LIMIT, t("conduct_step3")); ?></li>
                    <li><?php echo str_replace("{limit}", (string) EM_WARNINGS_LIMIT, t("conduct_step4")); ?></li>
                </ol>
                <p class="story-note"><?php echo t("conduct_note"); ?></p>
            </div>
        </div>

    </div>
</section>

<script>
/*
 * Back: the browser's own "back" keeps what was typed (e.g. the register
 * form). A tab opened only for this page (from the register form) is closed.
 */
document.getElementById("conductBack").addEventListener("click", function (event) {
    const cameFromSite = document.referrer !== "" && new URL(document.referrer).origin === window.location.origin;

    if (cameFromSite && window.history.length > 1) {
        event.preventDefault();
        window.history.back();
    } else if (window.history.length <= 1) {
        event.preventDefault();
        const fallback = this.href;
        window.close();
        setTimeout(function () { window.location.href = fallback; }, 150);
    }
});
</script>

</body>
</html>
