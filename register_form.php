<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/account_rules.php";
include "lang.php";

/* Values typed before an error (register.php keeps them, passwords excepted) */
$registerOld = $_SESSION["register_old"] ?? [];
unset($_SESSION["register_old"]);

function registerOld(string $field): string
{
    global $registerOld;
    return htmlspecialchars((string) ($registerOld[$field] ?? ""), ENT_QUOTES, "UTF-8");
}

$registerErrors = [
    "empty_fields" => "register_error_empty",
    "invalid_email" => "register_error_email",
    "password_short" => "register_error_password_short",
    "password_mismatch" => "auth_password_mismatch",
    "account_exists" => "register_error_exists",
    "invalid_username" => "register_error_username",
    "username_taken" => "register_error_username_taken",
    "email_taken" => "register_error_email_taken",
    "invalid_name" => "register_error_name",
    "invalid_phone" => "register_error_phone",
    "invalid_postal_code" => "register_error_postal_code",
    "invalid_place" => "register_error_place",
];
$registerError = $registerErrors[$_GET["error"] ?? ""] ?? null;

$authSideTitle = t("auth_register_side_title");
$authSideText = t("auth_register_side_text");
?>
<!DOCTYPE html>
<html lang="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/auth.css?v=<?php echo ASSET_VERSION; ?>">

    <title><?php echo t("create_account"); ?> - EnergyMatch</title>
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

            <h1><?php echo t("create_account"); ?></h1>

            <?php if ($registerError !== null): ?>
                <div class="auth-error" id="register-error"><?php echo t($registerError); ?></div>
            <?php endif; ?>

            <!--
                One form in two steps (the same fields go to register.php).
                Without JavaScript both steps are shown one under the other.
            -->
            <form action="register.php" method="POST" id="registerForm" class="auth-steps-form" data-checking-message="<?php echo htmlspecialchars(t("avail_checking"), ENT_QUOTES, "UTF-8"); ?>">
                <?php echo csrfField(); ?>

                <!-- PROGRESS -->
                <div class="auth-progress" aria-hidden="true">
                    <div class="auth-progress-step is-current" data-progress="1">
                        <span class="auth-progress-bar"></span>
                        <span>1. <?php echo t("auth_step_account"); ?></span>
                    </div>
                    <div class="auth-progress-step" data-progress="2">
                        <span class="auth-progress-bar"></span>
                        <span>2. <?php echo t("auth_step_details"); ?></span>
                    </div>
                </div>

                <!-- STEP 1: ACCOUNT -->
                <section class="auth-step" data-step="1">

                    <div class="auth-field">
                        <label for="username"><?php echo t("auth_username"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="at-sign"></i>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?php echo registerOld("username"); ?>"
                                autocomplete="username"
                                pattern="<?php echo EM_USERNAME_PATTERN; ?>"
                                minlength="<?php echo EM_USERNAME_MIN; ?>"
                                maxlength="<?php echo EM_USERNAME_MAX; ?>"
                                spellcheck="false"
                                autocapitalize="off"
                                data-check-field="username"
                                data-invalid-message="<?php echo htmlspecialchars(t("register_error_username"), ENT_QUOTES, "UTF-8"); ?>"
                                data-taken-message="<?php echo htmlspecialchars(t("register_error_username_taken"), ENT_QUOTES, "UTF-8"); ?>"
                                data-free-message="<?php echo htmlspecialchars(t("avail_username_free"), ENT_QUOTES, "UTF-8"); ?>"
                                required
                            >
                        </div>
                        <small class="auth-hint"><?php echo t("auth_username_hint"); ?></small>
                        <small class="auth-availability" data-availability-for="username" aria-live="polite"></small>
                    </div>

                    <div class="auth-field">
                        <label for="email"><?php echo t("register_email"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="mail"></i>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php echo registerOld("email"); ?>"
                                autocomplete="email"
                                placeholder="name@example.com"
                                pattern="<?php echo EM_EMAIL_PATTERN; ?>"
                                data-invalid-message="<?php echo htmlspecialchars(t("register_error_email_format"), ENT_QUOTES, "UTF-8"); ?>"
                                data-check-field="email"
                                data-taken-message="<?php echo htmlspecialchars(t("register_error_email_taken"), ENT_QUOTES, "UTF-8"); ?>"
                                data-free-message="<?php echo htmlspecialchars(t("avail_email_free"), ENT_QUOTES, "UTF-8"); ?>"
                                required
                            >
                        </div>
                        <small class="auth-availability" data-availability-for="email" aria-live="polite"></small>
                    </div>

                    <div class="auth-field">
                        <label for="password"><?php echo t("password"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="lock"></i>
                            <input type="password" id="password" name="password" autocomplete="new-password" minlength="6" required>
                            <button type="button" class="auth-eye" data-show-password="password" aria-label="<?php echo t("auth_show_password"); ?>">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                        <small class="auth-hint"><?php echo t("auth_password_hint"); ?></small>
                    </div>

                    <div class="auth-field">
                        <label for="confirm_password"><?php echo t("confirm_password"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="lock"></i>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                minlength="6"
                                data-mismatch-message="<?php echo htmlspecialchars(t("auth_password_mismatch"), ENT_QUOTES, "UTF-8"); ?>"
                                required
                            >
                            <button type="button" class="auth-eye" data-show-password="confirm_password" aria-label="<?php echo t("auth_show_password"); ?>">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="button" class="ui-btn ui-btn-primary auth-submit" data-next-step>
                        <?php echo t("auth_continue"); ?>
                        <i data-lucide="arrow-right"></i>
                    </button>

                </section>

                <!-- STEP 2: PERSONAL DETAILS + ADDRESS -->
                <section class="auth-step" data-step="2">

                    <div class="auth-grid">
                        <div class="auth-field">
                            <label for="name"><?php echo t("register_name"); ?></label>
                            <div class="auth-input">
                                <i data-lucide="user-round"></i>
                                <input type="text" id="name" name="name" value="<?php echo registerOld("name"); ?>" pattern="<?php echo EM_PERSON_NAME_PATTERN; ?>" data-pattern-message="<?php echo htmlspecialchars(t("register_error_name"), ENT_QUOTES, "UTF-8"); ?>" autocomplete="given-name" required>
                            </div>
                        </div>

                        <div class="auth-field">
                            <label for="surname"><?php echo t("register_surname"); ?></label>
                            <div class="auth-input">
                                <i data-lucide="user-round"></i>
                                <input type="text" id="surname" name="surname" value="<?php echo registerOld("surname"); ?>" pattern="<?php echo EM_PERSON_NAME_PATTERN; ?>" data-pattern-message="<?php echo htmlspecialchars(t("register_error_name"), ENT_QUOTES, "UTF-8"); ?>" autocomplete="family-name" required>
                            </div>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="phone"><?php echo t("phone"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="phone"></i>
                            <input type="tel" id="phone" name="phone" value="<?php echo registerOld("phone"); ?>" autocomplete="tel" inputmode="numeric" maxlength="10" pattern="<?php echo EM_PHONE_PATTERN; ?>" placeholder="6912345678" data-pattern-message="<?php echo htmlspecialchars(t("register_error_phone"), ENT_QUOTES, "UTF-8"); ?>" required>
                        </div>
                        <small class="auth-hint"><?php echo t("phone_hint"); ?></small>
                    </div>

                    <div class="auth-field">
                        <label for="address"><?php echo t("register_address"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="house"></i>
                            <input type="text" id="address" name="address" value="<?php echo registerOld("address"); ?>" autocomplete="street-address" required>
                        </div>
                    </div>

                    <div class="auth-grid">
                        <div class="auth-field">
                            <label for="city"><?php echo t("register_city"); ?></label>
                            <div class="auth-input">
                                <i data-lucide="building-2"></i>
                                <input type="text" id="city" name="city" value="<?php echo registerOld("city"); ?>" pattern="<?php echo EM_PLACE_PATTERN; ?>" data-pattern-message="<?php echo htmlspecialchars(t("register_error_place"), ENT_QUOTES, "UTF-8"); ?>" autocomplete="address-level2" required>
                            </div>
                        </div>

                        <div class="auth-field">
                            <label for="postal_code"><?php echo t("register_postal_code"); ?></label>
                            <div class="auth-input">
                                <i data-lucide="mailbox"></i>
                                <input type="text" id="postal_code" name="postal_code" value="<?php echo registerOld("postal_code"); ?>" autocomplete="postal-code" inputmode="numeric" maxlength="5" pattern="<?php echo EM_POSTAL_CODE_PATTERN; ?>" placeholder="22100" data-pattern-message="<?php echo htmlspecialchars(t("register_error_postal_code"), ENT_QUOTES, "UTF-8"); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="region"><?php echo t("register_region"); ?></label>
                        <div class="auth-input">
                            <i data-lucide="map"></i>
                            <input type="text" id="region" name="region" value="<?php echo registerOld("region"); ?>" pattern="<?php echo EM_PLACE_PATTERN; ?>" data-pattern-message="<?php echo htmlspecialchars(t("register_error_place"), ENT_QUOTES, "UTF-8"); ?>" autocomplete="address-level1" required>
                        </div>
                    </div>

                    <label class="auth-check auth-terms" for="terms">
                        <input type="checkbox" id="terms" required>
                        <?php echo t("agree_terms"); ?>
                        <a href="conduct.php" target="_blank" rel="noopener"><?php echo t("conduct_link_text"); ?></a>
                    </label>

                    <div class="auth-actions">
                        <button type="button" class="ui-btn ui-btn-ghost" data-previous-step>
                            <i data-lucide="arrow-left"></i>
                            <?php echo t("auth_back"); ?>
                        </button>

                        <button type="submit" class="ui-btn ui-btn-primary auth-submit">
                            <i data-lucide="user-plus"></i>
                            <?php echo t("create_account"); ?>
                        </button>
                    </div>

                </section>

                <p class="auth-switch">
                    <?php echo t("already_account"); ?>
                    <a href="sign_in.php"><?php echo t("sign_in"); ?></a>
                </p>
            </form>

        </div>
    </main>

</div>

<script src="js/auth.js?v=<?php echo ASSET_VERSION; ?>"></script>

</body>
</html>
