<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
include "lang.php";

/* Already signed in (e.g. through "remember me"): go straight in */
if (isset($_SESSION["user_id"])) {
    header("Location: " . (($_SESSION["role"] ?? "user") === "admin" ? "admin_dashboard.php" : "dashboard.php"));
    exit();
}

$authSideTitle = t("auth_signin_side_title");
$authSideText = t("auth_signin_side_text");
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/auth.css?v=<?php echo ASSET_VERSION; ?>">

    <title><?php echo t("sign_in"); ?> - EnergyMatch</title>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="auth-shell">

    <?php include "auth_side.php"; ?>

    <main class="auth-main">
        <div class="auth-card">

            <h1><?php echo t("sign_in"); ?></h1>
            <p class="auth-lead"><?php echo t("auth_signin_lead"); ?></p>

            <!-- Login errors (filled in by the script below) -->
            <div id="login-error" class="auth-error" hidden></div>

            <?php if (($_GET["reset"] ?? "") === "done"): ?>
                <div class="auth-success">
                    <i data-lucide="circle-check"></i>
                    <?php echo t("reset_done_message"); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <?php echo csrfField(); ?>

                <div class="auth-field">
                    <label for="username"><?php echo t("username_email"); ?></label>
                    <div class="auth-input">
                        <i data-lucide="user-round"></i>
                        <input type="text" id="username" name="username" autocomplete="username" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password"><?php echo t("password"); ?></label>
                    <div class="auth-input">
                        <i data-lucide="lock"></i>
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="auth-eye" data-show-password="password" aria-label="<?php echo t("auth_show_password"); ?>">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-options">
                    <label class="auth-check" for="remember">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <?php echo t("remember_me"); ?>
                    </label>
                    <a href="forgot_password.php"><?php echo t("forgot_password"); ?></a>
                </div>

                <button type="submit" class="ui-btn ui-btn-dark auth-submit">
                    <i data-lucide="log-in"></i>
                    <?php echo t("sign_in"); ?>
                </button>

                <p class="auth-switch">
                    <?php echo t("no_account"); ?>
                    <a href="register_form.php"><?php echo t("create_one"); ?></a>
                </p>
            </form>

        </div>
    </main>

</div>

<script src="js/auth.js?v=<?php echo ASSET_VERSION; ?>"></script>

<script>
/* Login error messages (?error=... from login.php) */
const currentLanguage = <?php echo json_encode($_SESSION["language"] ?? "en"); ?>;
const error = new URLSearchParams(window.location.search).get("error");
const errorBox = document.getElementById("login-error");

/* An unknown user and a wrong password get the same message on purpose */
const waitMinutes = Math.max(1, parseInt(new URLSearchParams(window.location.search).get("wait"), 10) || 1);
const loginMessages = {
    en: {
        invalid_credentials: "The username / e-mail or the password is not correct.",
        too_many_attempts: "Too many failed attempts. Please try again in " + waitMinutes + " minute" + (waitMinutes === 1 ? "" : "s") + ".",
        empty_fields: "Please fill in all fields.",
        account_removed: "This account no longer exists. It was deleted, e.g. after 5 approved reports."
    },
    gr: {
        invalid_credentials: "Το όνομα χρήστη / email ή ο κωδικός δεν είναι σωστά.",
        too_many_attempts: "Πολλές αποτυχημένες προσπάθειες. Δοκίμασε ξανά σε " + waitMinutes + (waitMinutes === 1 ? " λεπτό." : " λεπτά."),
        empty_fields: "Συμπλήρωσε όλα τα πεδία.",
        account_removed: "Αυτός ο λογαριασμός δεν υπάρχει πια. Διαγράφηκε, π.χ. μετά από 5 εγκεκριμένες αναφορές."
    }
};
/* old links */
loginMessages.en.wrong_password = loginMessages.en.user_not_found = loginMessages.en.invalid_credentials;
loginMessages.gr.wrong_password = loginMessages.gr.user_not_found = loginMessages.gr.invalid_credentials;

if (error && loginMessages[currentLanguage] && loginMessages[currentLanguage][error]) {
    errorBox.textContent = loginMessages[currentLanguage][error];
    errorBox.hidden = false;
}
</script>

</body>
</html>
