<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/account_rules.php";
include "db.php";
include "lang.php";

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}
$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| LOAD USER DATA FROM DATABASE
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        id,
        name,
        surname,
        address,
        region,
        city,
        postal_code,
        phone,
        email,
        username
    FROM users
    WHERE id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error.");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    session_destroy();
    header("Location: sign_in.php");
    exit();
}
$user = $result->fetch_assoc();
$stmt->close();

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function safe($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
$status = $_GET["status"] ?? "";
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
    <link rel="stylesheet" href="css/settings.css?v=<?php echo ASSET_VERSION; ?>">
    <title>
        <?php echo t("settings"); ?> - EnergyMatch
    </title>
</head>
<body>

<section class="hero-section">
    <?php include "navbar.php"; ?>

    <!-- MAIN -->
    <div class="settings-container">

        <!-- HEADER -->
        <div class="settings-header">
            <h1>
                <i data-lucide="settings"></i> <?php echo t("account_settings"); ?>
            </h1>
            <p>
                <?php echo t("settings_intro"); ?>
            </p>
        </div>

        <!-- SUCCESS / ERROR MESSAGES -->
        <?php if ($status === "updated"): ?>
            <div class="message success-message">
                <i data-lucide="check"></i> <?php echo t("account_updated"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "username_exists"): ?>
            <div class="message error-message">
                <?php echo t("username_exists"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "email_exists"): ?>
            <div class="message error-message">
                <?php echo t("email_exists"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "invalid_email"): ?>
            <div class="message error-message">
                <?php echo t("invalid_email"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "invalid_name"): ?>
            <div class="message error-message">
                <?php echo t("register_error_name"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "invalid_phone"): ?>
            <div class="message error-message">
                <?php echo t("register_error_phone"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "invalid_postal_code"): ?>
            <div class="message error-message">
                <?php echo t("register_error_postal_code"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "invalid_place"): ?>
            <div class="message error-message">
                <?php echo t("register_error_place"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "empty_fields"): ?>
            <div class="message error-message">
                <?php echo t("settings_empty_fields"); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === "error"): ?>
            <div class="message error-message">
                <?php echo t("settings_error"); ?>
            </div>
        <?php endif; ?>

        <!-- PERSONAL INFORMATION -->
        <div class="settings-card">
            <div class="card-title">
                <div class="card-icon">
                    <i data-lucide="user-round"></i>
                </div>
                <div>
                    <h2>
                        <?php echo t("personal_information"); ?>
                    </h2>
                    <p>
                        <?php echo t("personal_information_description"); ?>
                    </p>
                </div>
            </div>

            <form
                action="update_account.php"
                method="POST"
            >
                <?php echo csrfField(); ?>
                <div class="form-grid">

                    <!-- NAME -->
                    <div class="input-group">
                        <label>
                            <?php echo t("register_name"); ?>
                        </label>
                        <input
                            type="text"
                            name="name"
                            value="<?php echo safe($user["name"]); ?>"
                            required
                            pattern="<?php echo EM_PERSON_NAME_PATTERN; ?>"
                            data-pattern-message="<?php echo htmlspecialchars(t("register_error_name"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                    </div>

                    <!-- SURNAME -->
                    <div class="input-group">
                        <label>
                            <?php echo t("register_surname"); ?>
                        </label>
                        <input
                            type="text"
                            name="surname"
                            value="<?php echo safe($user["surname"]); ?>"
                            required
                            pattern="<?php echo EM_PERSON_NAME_PATTERN; ?>"
                            data-pattern-message="<?php echo htmlspecialchars(t("register_error_name"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                    </div>

                    <!-- USERNAME -->
                    <div class="input-group">
                        <label>
                            <?php echo t("username"); ?>
                        </label>
                        <!-- chosen at registration, cannot be changed (not sent with the form) -->
                        <input
                            type="text"
                            id="username"
                            value="<?php echo safe($user["username"]); ?>"
                            class="readonly-field"
                            readonly
                            aria-describedby="usernameLockedHint"
                        >
                        <small class="field-hint" id="usernameLockedHint">
                            <i data-lucide="lock"></i> <?php echo t("username_locked_hint"); ?>
                        </small>
                    </div>

                    <!-- EMAIL -->
                    <div class="input-group">
                        <label>
                            <?php echo t("register_email"); ?>
                        </label>
                        <input
                            type="email"
                            name="email"
                            value="<?php echo safe($user["email"]); ?>"
                            required
                            pattern="<?php echo EM_EMAIL_PATTERN; ?>"
                            data-pattern-message="<?php echo htmlspecialchars(t("register_error_email_format"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                    </div>

                    <!-- PHONE -->
                    <div class="input-group">
                        <label>
                            <?php echo t("phone"); ?>
                        </label>
                        <input
                            type="text"
                            name="phone"
                            value="<?php echo safe($user["phone"]); ?>"
                            required
                            pattern="<?php echo EM_PHONE_PATTERN; ?>"
                            data-pattern-message="<?php echo htmlspecialchars(t("register_error_phone"), ENT_QUOTES, "UTF-8"); ?>"
                            inputmode="numeric"
                            maxlength="10"
                        >
                    </div>

                    <!-- ADDRESS -->
                    <div class="input-group">
                        <label>
                            <?php echo t("register_address"); ?>
                        </label>
                        <input
                            type="text"
                            name="address"
                            value="<?php echo safe($user["address"]); ?>"
                            required
                        >
                    </div>

                    <!-- REGION -->
                    <div class="input-group">
                        <label>
                            <?php echo t("register_region"); ?>
                        </label>
                        <input
                            type="text"
                            name="region"
                            value="<?php echo safe($user["region"]); ?>"
                            required
                            pattern="<?php echo EM_PLACE_PATTERN; ?>"
                            data-pattern-message="<?php echo htmlspecialchars(t("register_error_place"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                    </div>

                    <!-- CITY -->
                    <div class="input-group">
                        <label>
                            <?php echo t("register_city"); ?>
                        </label>
                        <input
                            type="text"
                            name="city"
                            value="<?php echo safe($user["city"]); ?>"
                            required
                            pattern="<?php echo EM_PLACE_PATTERN; ?>"
                            data-pattern-message="<?php echo htmlspecialchars(t("register_error_place"), ENT_QUOTES, "UTF-8"); ?>"
                        >
                    </div>

                    <!-- POSTAL CODE -->
                    <div class="input-group full-width">
                        <label>
                            <?php echo t("register_postal_code"); ?>
                        </label>
                        <input
                            type="text"
                            name="postal_code"
                            value="<?php echo safe($user["postal_code"]); ?>"
                            required
                            pattern="<?php echo EM_POSTAL_CODE_PATTERN; ?>"
                            data-pattern-message="<?php echo htmlspecialchars(t("register_error_postal_code"), ENT_QUOTES, "UTF-8"); ?>"
                            inputmode="numeric"
                            maxlength="5"
                        >
                    </div>
                </div>
                <div class="form-actions">
                    <button
                        type="submit"
                        class="save-btn"
                    >
                        <?php echo t("save_changes"); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- SECURITY PREVIEW -->
        <!-- SECURITY / CHANGE PASSWORD -->
    <div class="settings-card">
        <div class="card-title">
            <div class="card-icon">
                <i data-lucide="lock"></i>
            </div>
            <div>
                <h2>
                    <?php echo t("change_password"); ?>
                </h2>
                <p>
                    <?php echo t("security_description"); ?>
                </p>
            </div>
        </div>

    <?php if ($status === "password_changed"): ?>
        <div class="message success-message">
            <i data-lucide="check"></i> <?php echo t("password_changed_success"); ?>
        </div>
    <?php endif; ?>

    <?php if ($status === "wrong_current_password"): ?>
        <div class="message error-message">
            <?php echo t("wrong_current_password"); ?>
        </div>
    <?php endif; ?>

    <?php if ($status === "passwords_not_match"): ?>
        <div class="message error-message">
            <?php echo t("passwords_not_match"); ?>
        </div>
    <?php endif; ?>

    <?php if ($status === "password_too_short"): ?>
        <div class="message error-message">
            <?php echo t("password_too_short"); ?>
        </div>
    <?php endif; ?>

    <form
        action="change_password.php"
        method="POST"
    >
        <?php echo csrfField(); ?>
        <div class="password-grid">
            <div class="input-group">
                <label>
                    <?php echo t("current_password"); ?>
                </label>
                <input
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >
            </div>
            <div class="input-group">
                <label>
                    <?php echo t("new_password"); ?>
                </label>
                <input
                    type="password"
                    name="new_password"
                    autocomplete="new-password"
                    required
                >
            </div>
            <div class="input-group">
                <label>
                    <?php echo t("confirm_new_password"); ?>
                </label>
                <input
                    type="password"
                    name="confirm_new_password"
                    autocomplete="new-password"
                    required
                >
            </div>
        </div>
        <div class="form-actions">
            <button
                type="submit"
                class="save-btn"
            >
                <?php echo t("change_password_button"); ?>
            </button>
        </div>
    </form>
</div>

<!-- DANGER ZONE -->
<div class="settings-card danger-card">
    <div class="card-title">
        <div class="card-icon danger-icon">
            <i data-lucide="triangle-alert"></i>
        </div>
        <div>
            <h2 class="danger-title">
                <?php echo t("danger_zone"); ?>
            </h2>
            <p>
                <?php echo t("delete_account_description"); ?>
            </p>
        </div>
    </div>

    <?php if ($status === "delete_confirmation_error"): ?>
        <div class="message error-message">
            <?php echo t("delete_confirmation_error"); ?>
        </div>
    <?php endif; ?>

    <?php if ($status === "delete_error"): ?>
        <div class="message error-message">
            <?php echo t("delete_account_error"); ?>
        </div>
    <?php endif; ?>
    <div class="danger-content">
        <div class="danger-warning">
            <strong>
                <?php echo t("delete_warning_title"); ?>
            </strong>
            <p>
                <?php echo t("delete_warning_text"); ?>
            </p>
        </div>

        <form
            action="delete_account.php"
            method="POST"
            onsubmit="return confirmDeleteAccount();"
        >
            <?php echo csrfField(); ?>
            <div class="input-group">
                <label>
                    <?php echo t("delete_confirmation_label"); ?>
                </label>
                <input
                    type="text"
                    name="delete_confirmation"
                    id="delete_confirmation"
                    placeholder="DELETE"
                    autocomplete="off"
                    required
                >
            </div>
            <div class="form-actions">
                <button
                    type="submit"
                    class="delete-account-btn"
                >
                    <?php echo t("delete_account"); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>

function confirmDeleteAccount() {
    const confirmation =
        document.getElementById("delete_confirmation").value;
    if (confirmation !== "DELETE") {
        alert(
            "<?php echo addslashes(t("type_delete_first")); ?>"
        );
        return false;
    }
    return confirm(
        "<?php echo addslashes(t("final_delete_confirmation")); ?>"
    );
}
</script>

        <!-- LANGUAGE -->
        <div class="settings-card small-card">
            <div class="card-title">
                <div class="card-icon">
                    <i data-lucide="globe"></i>
                </div>
                <div>
                    <h2>
                        <?php echo t("language_setting"); ?>
                    </h2>
                    <p>
                        <?php echo t("language_description"); ?>
                    </p>
                </div>
            </div>
            <div class="language-options">
                <a
                    href="set_language.php?lang=en"
                    class="<?php
                        echo ($_SESSION["language"] ?? "en") === "en"
                            ? "selected-language"
                            : "";
                    ?>"
                >
                    English
                </a>
                <a
                    href="set_language.php?lang=gr"
                    class="<?php
                        echo ($_SESSION["language"] ?? "en") === "gr"
                            ? "selected-language"
                            : "";
                    ?>"
                >
                    Ελληνικά
                </a>
            </div>
        </div>
    </div>
</section>
</body>
</html>
