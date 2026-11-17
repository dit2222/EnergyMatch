<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
include "lang.php";
require_once __DIR__ . "/password_reset.php";

/*
|--------------------------------------------------------------------------
| FORGOT PASSWORD - step 2: the link from the e-mail
|--------------------------------------------------------------------------
| The token must exist, not be used and not be older than 1 hour.
| After the new password is saved, the link is marked as used and every
| "remember me" login of the user is signed out.
*/

$token = (string) ($_POST["token"] ?? $_GET["token"] ?? "");
$formError = "";
$resetRow = null;

try {
    include "db.php";
    $resetRow = emResetFind($conn, $token);

    if ($resetRow !== null && $_SERVER["REQUEST_METHOD"] === "POST") {
        csrfVerify();

        $password = (string) ($_POST["password"] ?? "");
        $confirmPassword = (string) ($_POST["confirm_password"] ?? "");

        if (strlen($password) < EM_RESET_MIN_PASSWORD) {
            $formError = t("reset_password_too_short");
        } elseif ($password !== $confirmPassword) {
            $formError = t("auth_password_mismatch");
        } else {
            $userId = (int) $resetRow["user_id"];
            $resetId = (int) $resetRow["id"];
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $conn->begin_transaction();

            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $passwordHash, $userId);
            $update->execute();
            $update->close();

            /* this link is used; other open links of the user stop working */
            $used = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $used->bind_param("i", $resetId);
            $used->execute();
            $used->close();

            $others = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL");
            $others->bind_param("i", $userId);
            $others->execute();
            $others->close();

            $conn->commit();

            emRememberForgetUser($conn, $userId);
            $conn->close();

            header("Location: sign_in.php?reset=done");
            exit();
        }
    }

    $conn->close();
} catch (Throwable $error) {
    error_log("EnergyMatch reset password: " . $error->getMessage());
    $resetRow = null;
}

$authSideTitle = t("reset_side_title");
$authSideText = t("reset_side_text");
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/auth.css?v=<?php echo ASSET_VERSION; ?>">

    <title><?php echo t("reset_title"); ?> - EnergyMatch</title>
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

            <h1><?php echo t("reset_title"); ?></h1>

            <?php if ($resetRow === null): ?>

                <div class="auth-error"><?php echo t("reset_invalid_link"); ?></div>

                <a class="ui-btn ui-btn-dark auth-submit" href="forgot_password.php">
                    <i data-lucide="rotate-ccw"></i>
                    <?php echo t("reset_request_new"); ?>
                </a>

                <a class="auth-back" href="sign_in.php">
                    <i data-lucide="arrow-left"></i>
                    <?php echo t("forgot_back_to_signin"); ?>
                </a>

            <?php else: ?>

                <p class="auth-lead">
                    <?php echo t("reset_lead"); ?>
                    <strong><?php echo htmlspecialchars($resetRow["email"], ENT_QUOTES, "UTF-8"); ?></strong>
                </p>

                <?php if ($formError !== ""): ?>
                    <div class="auth-error"><?php echo $formError; ?></div>
                <?php endif; ?>

                <form action="reset_password.php" method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, "UTF-8"); ?>">

                    <div class="auth-field">
                        <label for="password"><?php echo t("reset_new_password"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="lock"></i>
                            <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?php echo EM_RESET_MIN_PASSWORD; ?>" required autofocus>
                            <button type="button" class="auth-eye" data-show-password="password" aria-label="<?php echo t("auth_show_password"); ?>">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="confirm_password"><?php echo t("confirm_password"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="<?php echo EM_RESET_MIN_PASSWORD; ?>" required>
                            <button type="button" class="auth-eye" data-show-password="confirm_password" aria-label="<?php echo t("auth_show_password"); ?>">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="ui-btn ui-btn-dark auth-submit">
                        <i data-lucide="key-round"></i>
                        <?php echo t("reset_save"); ?>
                    </button>
                </form>

            <?php endif; ?>

        </div>
    </main>

</div>

<script src="js/auth.js?v=<?php echo ASSET_VERSION; ?>"></script>

</body>
</html>
