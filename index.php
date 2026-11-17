<?php
session_start();
require_once __DIR__ . "/remember_me.php";
include "lang.php";
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>
    <meta charset="UTF-8">
    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <link
        rel="stylesheet"
        href="css/style.css?v=<?php echo ASSET_VERSION; ?>"
    >
    <title>EnergyMatch</title>
</head>
<body>

<?php include "navbar.php"; ?>

<section class="hero-section">
    <div class="hero-content">
        <div class="hero-text">
            <span class="ui-eyebrow"><?php echo t("home_eyebrow"); ?></span>
            <h1>
                <?php echo t("Energy"); ?>
                <span>
                    <?php echo t("Match"); ?>
                </span>
            </h1>
            <p>
                <?php echo t("home_description"); ?>
            </p>
            <div class="hero-actions">
                <a href="register_form.php" class="ui-btn ui-btn-primary">
                    <i data-lucide="user-plus"></i>
                    <?php echo t("create_account"); ?>
                </a>
                <a
                    href="aboutus.php"
                    class="btn"
                >
                    <?php echo t("read_more"); ?>
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
            <ul class="hero-points">
                <li><i data-lucide="users-round"></i><?php echo t("home_point_match"); ?></li>
                <li><i data-lucide="scale"></i><?php echo t("home_point_fair"); ?></li>
                <li><i data-lucide="sun"></i><?php echo t("home_point_solar"); ?></li>
            </ul>
        </div>
        <div class="hero-image">
            <img
                src="media/back1.jpeg"
                alt="Energy Match"
            >
        </div>
    </div>
</section>
</body>
</html>
