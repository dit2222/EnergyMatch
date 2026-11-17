<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - CSRF PROTECTION
|--------------------------------------------------------------------------
|
| Protects every form that changes data (POST) against
| Cross-Site Request Forgery: another website making the user's
| browser submit a form to EnergyMatch without the user knowing.
|
| How it works:
| 1. Each session gets one random secret token (64 hex characters).
| 2. Every POST form includes it as a hidden field:  csrfField()
| 3. Every file that handles POST checks it first:   csrfVerify()
|    Another website cannot know the token, so its forged form fails.
|
| Usage
|   Form page:     require_once "csrf.php";
|                  <form method="POST" ...> <?php echo csrfField(); ?>
|
|   POST handler:  session_start();
|                  require_once "csrf.php";
|                  csrfVerify();
|
| Note: community.php, leave_community.php and report_user.php keep
| their own (older) tokens, which work the same way.
|
*/

const CSRF_SESSION_KEY = "csrf_token";
const CSRF_FIELD_NAME = "_csrf";

/* Returns the session token, creating it on first use. */
function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        empty($_SESSION[CSRF_SESSION_KEY])
        || !is_string($_SESSION[CSRF_SESSION_KEY])
    ) {
        $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[CSRF_SESSION_KEY];
}

/* Hidden input to print inside every POST form. */
function csrfField(): string
{
    return '<input type="hidden" name="' . CSRF_FIELD_NAME . '" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, "UTF-8") . '">';
}

/* True when the submitted token matches the session token. */
function csrfIsValid(): bool
{
    $posted = $_POST[CSRF_FIELD_NAME] ?? "";

    return is_string($posted)
        && $posted !== ""
        && hash_equals(csrfToken(), $posted);   // constant-time comparison
}

/* New token after login (the user's identity changed). */
function csrfRegenerate(): void
{
    $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
}

/*
 * Call at the top of every file that handles POST.
 * GET requests pass through untouched.
 * An invalid POST stops here with a 403 page.
 */
function csrfVerify(): void
{
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
        return;
    }

    if (csrfIsValid()) {
        return;
    }

    csrfFail();
}

function csrfFail(): void
{
    /* Load translations only if the page has not loaded them yet. */
    if (!function_exists("t")) {
        global $translations;
        include __DIR__ . "/lang.php";
    }

    /* "Go back" only to a page of this site. */
    $back = "index.php";
    $referer = $_SERVER["HTTP_REFERER"] ?? "";
    $host = $_SERVER["HTTP_HOST"] ?? "";

    if ($referer !== "" && $host !== "" && parse_url($referer, PHP_URL_HOST) === parse_url("http://" . $host, PHP_URL_HOST)) {
        $back = $referer;
    }

    http_response_code(403);

    $lang = ($_SESSION["language"] ?? "en") === "gr" ? "el" : "en";
    $title = htmlspecialchars(t("csrf_error_title"), ENT_QUOTES, "UTF-8");
    $text = htmlspecialchars(t("csrf_error_text"), ENT_QUOTES, "UTF-8");
    $button = htmlspecialchars(t("csrf_error_back"), ENT_QUOTES, "UTF-8");
    $backUrl = htmlspecialchars($back, ENT_QUOTES, "UTF-8");

    echo <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - EnergyMatch</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3faf3; font-family: Arial, sans-serif; padding: 20px; box-sizing: border-box; }
        .box { max-width: 460px; background: white; border-radius: 22px; padding: 35px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .icon { font-size: 40px; }
        h1 { color: #2e7d32; font-size: 22px; margin: 12px 0; }
        p { color: #555; line-height: 1.5; }
        a { display: inline-block; margin-top: 15px; padding: 12px 26px; background: #2e7d32; color: white; text-decoration: none; border-radius: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">🔒</div>
        <h1>{$title}</h1>
        <p>{$text}</p>
        <a href="{$backUrl}">{$button}</a>
    </div>
</body>
</html>
HTML;

    exit;
}
