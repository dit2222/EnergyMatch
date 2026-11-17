<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
include "lang.php";

/* =========================================
   PREFILL FOR LOGGED-IN USERS
   Name and email come from the user's account.
========================================= */
$prefillName = "";
$prefillEmail = "";
if (isset($_SESSION["user_id"])) {
    require_once "db.php";
    $prefillStmt = $conn->prepare(
        "SELECT name, surname, email FROM users WHERE id = ? LIMIT 1"
    );
    if ($prefillStmt) {
        $prefillUserId = (int) $_SESSION["user_id"];
        $prefillStmt->bind_param("i", $prefillUserId);
        $prefillStmt->execute();
        $prefillResult = $prefillStmt->get_result();
        if ($prefillRow = $prefillResult->fetch_assoc()) {
            $prefillName = trim(
                ($prefillRow["name"] ?? "") . " " . ($prefillRow["surname"] ?? "")
            );
            $prefillEmail = (string) ($prefillRow["email"] ?? "");
        }
        $prefillStmt->close();
    }
}
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
        href="css/contacts.css?v=<?php echo ASSET_VERSION; ?>"
    >
    <title>
        <?php echo t("contact_us"); ?> - EnergyMatch
    </title>
</head>
<body>

<section class="hero-section">
    <?php include "navbar.php"; ?>
    <div class="contact-container">
        <div class="contact-header">
            <h1>
                <?php echo t("contact_us"); ?>
            </h1>
            <p>
                <?php echo t("contact_intro"); ?>
            </p>
        </div>
        <div class="contact-content">
            <div class="contact-info">
                <h2>
                    <?php echo t("get_in_touch"); ?>
                </h2>
                <p>
                    <?php echo t("contact_description"); ?>
                </p>
                <div class="info-card">
                    <div class="info-icon">
                        <i data-lucide="mail"></i>
                    </div>
                    <div>
                        <h3>
                            <?php echo t("contact_email"); ?>
                        </h3>
                        <p>
                            info@energymatch.gr
                        </p>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon">
                        <i data-lucide="phone"></i>
                    </div>
                    <div>
                        <h3>
                            <?php echo t("phone"); ?>
                        </h3>
                        <p>
                            +30 210 000 0000
                        </p>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon">
                        <i data-lucide="map-pin"></i>
                    </div>
                    <div>
                        <h3>
                            <?php echo t("contact_location"); ?>
                        </h3>
                        <p>
                            <?php echo t("greece"); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="contact-form-card">

                <?php if (isset($_GET["success"]) && $_GET["success"] === "message_sent"): ?>
                    <div class="contact-success">
                        <i data-lucide="check"></i> <?php echo t("contact_message_sent"); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET["error"])): ?>
                    <div class="contact-error">
                        <?php
                        switch ($_GET["error"]) {
                            case "empty_fields":
                                echo t("contact_error_empty");
                                break;
                            case "invalid_email":
                                echo t("contact_error_email");
                                break;
                            case "too_long":
                                echo t("contact_error_long");
                                break;
                            default:
                                echo t("contact_error_general");
                                break;
                        }
                        ?>
                    </div>
                <?php endif; ?>
                <h2>
                    <?php echo t("send_us_message"); ?>
                </h2>

                <form
                    action="contact_submit.php"
                    method="POST"
                >
                    <?php echo csrfField(); ?>
                    <div class="input-group">
                        <label>
                            <?php echo t("full_name"); ?>
                        </label>
                        <input
                            type="text"
                            name="full_name"
                            value="<?php echo htmlspecialchars($prefillName, ENT_QUOTES, "UTF-8"); ?>"
                            placeholder="<?php echo t("enter_name"); ?>"
                            maxlength="120"
                            required
                        >
                    </div>
                    <div class="input-group">
                        <label>
                            <?php echo t("contact_email"); ?>
                        </label>
                        <input
                            type="email"
                            name="email"
                            value="<?php echo htmlspecialchars($prefillEmail, ENT_QUOTES, "UTF-8"); ?>"
                            placeholder="<?php echo t("enter_email"); ?>"
                            maxlength="150"
                            required
                        >
                    </div>
                    <div class="input-group">
                        <label>
                            <?php echo t("subject"); ?>
                        </label>
                        <input
                            type="text"
                            name="subject"
                            placeholder="<?php echo t("how_can_help"); ?>"
                            maxlength="200"
                            required
                        >
                    </div>
                    <div class="input-group">
                        <label>
                            <?php echo t("message"); ?>
                        </label>
                        <textarea
                            name="message"
                            placeholder="<?php echo t("write_message"); ?>"
                            maxlength="5000"
                            required
                        ></textarea>
                    </div>
                    <button
                        type="submit"
                        class="send-btn"
                    >
                        <?php echo t("send_message"); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
</body>
</html>
