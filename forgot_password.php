<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
include "lang.php";
require_once __DIR__ . "/password_reset.php";

/*
|--------------------------------------------------------------------------
| FORGOT PASSWORD - step 1: the user gives the account e-mail
|--------------------------------------------------------------------------
| No e-mail is sent. If an account with this e-mail exists, a one-time
| reset link (valid for 1 hour) is created and the user goes straight
| to reset_password.php to choose a new password.
*/

$formError = "";
$emailValue = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrfVerify();
    $emailValue = trim((string) ($_POST["email"] ?? ""));

    if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $formError = t("forgot_invalid_email");
    } else {
        try {
            include "db.php";

            $statement = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $statement->bind_param("s", $emailValue);
            $statement->execute();
            $user = $statement->get_result()->fetch_assoc();
            $statement->close();

            if ($user) {
                $token = emResetCreate($conn, (int) $user["id"]);
                $conn->close();

                header("Location: reset_password.php?token=" . $token);
                exit();
            }

            $conn->close();
            $formError = t("forgot_no_account");
        } catch (Throwable $error) {
            error_log("EnergyMatch forgot password: " . $error->getMessage());
            $formError = t("forgot_server_error");
        }
    }
}

$authSideTitle = t("forgot_side_title");
$authSideText = t("forgot_side_text");
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/auth.css?v=<?php echo ASSET_VERSION; ?>">

    <title><?php echo t("forgot_title"); ?> - EnergyMatch</title>
</head>
<body>

<?php
$forceGuestNavbar = true;
include "navbar.php";
?>

<div class="auth-shell">

    <?php include "auth_side.php"; ?>

    <main class="auth-main">
        <div class="auth-card">

            <h1><?php echo t("forgot_title"); ?></h1>
            <p class="auth-lead"><?php echo t("forgot_lead"); ?></p>

            <?php if ($formError !== ""): ?>
                <div class="auth-error"><?php echo $formError; ?></div>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST">
                <?php echo csrfField(); ?>

                <div class="auth-field">
                    <label for="email"><?php echo t("register_email"); ?></label>
                    <div class="auth-input">
                        <i data-lucide="mail"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            autocomplete="email"
                            placeholder="name@example.com"
                            value="<?php echo htmlspecialchars($emailValue, ENT_QUOTES, "UTF-8"); ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <button type="submit" class="ui-btn ui-btn-dark auth-submit">
                    <i data-lucide="arrow-right"></i>
                    <?php echo t("forgot_continue"); ?>
                </button>

                <p class="auth-switch">
                    <a href="sign_in.php"><?php echo t("forgot_back_to_signin"); ?></a>
                </p>
            </form>

        </div>
    </main>

</div>

</body>
</html>
