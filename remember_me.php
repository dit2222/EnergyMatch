<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - "REMEMBER ME" (stay signed in for 30 days)
|--------------------------------------------------------------------------
|
| How it works:
| 1. On login with the box ticked, a random token is created and saved
|    in the cookie "em_remember" (30 days). The cookie value is
|    selector:validator.
| 2. The database (table remember_tokens) keeps the selector and only a
|    HASH of the validator, so a copy of the database cannot be used to
|    sign in.
| 3. When the session is gone (browser closed) but the cookie exists,
|    emRememberAutoLogin() finds the row by selector, checks the
|    validator and signs the user in again. The validator is replaced
|    every time it is used.
| 4. Log out deletes the token of this browser; changing or resetting
|    the password deletes all tokens of the user.
|
| This file is included right after session_start() on every page.
| If the table does not exist yet, it simply does nothing.
|
*/

const EM_REMEMBER_COOKIE = "em_remember";
const EM_REMEMBER_DAYS = 30;

function emRememberSetCookie(string $value, int $expires): void
{
    setcookie(EM_REMEMBER_COOKIE, $value, [
        "expires" => $expires,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

function emRememberClearCookie(): void
{
    emRememberSetCookie("", time() - 3600);
    unset($_COOKIE[EM_REMEMBER_COOKIE]);
}

/* Splits the cookie into [selector, validator], or null if it is malformed */
function emRememberReadCookie(): ?array
{
    $value = (string) ($_COOKIE[EM_REMEMBER_COOKIE] ?? "");

    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $value, $parts)) {
        return null;
    }

    return [$parts[1], $parts[2]];
}

/* Creates a token for this browser (called by login.php) */
function emRememberIssue(mysqli $conn, int $userId): void
{
    try {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash = hash("sha256", $validator);
        $expires = time() + EM_REMEMBER_DAYS * 24 * 60 * 60;
        $days = EM_REMEMBER_DAYS;

        /* expiry uses the database clock, the same clock that checks it */
        $statement = $conn->prepare(
            "INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ? DAY)"
        );
        $statement->bind_param("issi", $userId, $selector, $hash, $days);
        $statement->execute();
        $statement->close();

        /* old, expired tokens of this user are removed */
        $cleanup = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < NOW()");
        $cleanup->bind_param("i", $userId);
        $cleanup->execute();
        $cleanup->close();

        emRememberSetCookie($selector . ":" . $validator, $expires);
    } catch (Throwable $error) {
        /* table missing or database error: login still works without "remember me" */
    }
}

/* Deletes the token of this browser (log out) */
function emRememberForgetThisBrowser(mysqli $conn): void
{
    $cookie = emRememberReadCookie();

    if ($cookie !== null) {
        try {
            $statement = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $statement->bind_param("s", $cookie[0]);
            $statement->execute();
            $statement->close();
        } catch (Throwable $error) {
            /* nothing to delete */
        }
    }

    emRememberClearCookie();
}

/* Deletes all tokens of a user (password changed or reset) */
function emRememberForgetUser(mysqli $conn, int $userId): void
{
    try {
        $statement = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $statement->bind_param("i", $userId);
        $statement->execute();
        $statement->close();
    } catch (Throwable $error) {
        /* nothing to delete */
    }
}

/* Signs the user in from the cookie when there is no session */
function emRememberAutoLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || isset($_SESSION["user_id"])) {
        return;
    }

    $cookie = emRememberReadCookie();

    if ($cookie === null) {
        if (isset($_COOKIE[EM_REMEMBER_COOKIE])) {
            emRememberClearCookie();
        }
        return;
    }

    [$selector, $validator] = $cookie;

    /*
     * The page's own connection ($conn from db.php) is used and left open:
     * pages load db.php with require_once, which would otherwise skip it.
     */
    global $conn;

    try {
        if (!($conn instanceof mysqli)) {
            require_once __DIR__ . "/db.php";
        }

        $statement = $conn->prepare(
            "SELECT rt.id, rt.validator_hash, u.id AS user_id, u.username, u.name, u.role
             FROM remember_tokens rt
             INNER JOIN users u ON u.id = rt.user_id
             WHERE rt.selector = ? AND rt.expires_at > NOW()
             LIMIT 1"
        );
        $statement->bind_param("s", $selector);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$row || !hash_equals($row["validator_hash"], hash("sha256", $validator))) {
            /* unknown, expired or wrong token */
            $delete = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $delete->bind_param("s", $selector);
            $delete->execute();
            $delete->close();
            emRememberClearCookie();
            return;
        }

        /* new validator for the same selector (an old copied cookie stops working) */
        $newValidator = bin2hex(random_bytes(32));
        $newHash = hash("sha256", $newValidator);
        $expires = time() + EM_REMEMBER_DAYS * 24 * 60 * 60;
        $days = EM_REMEMBER_DAYS;
        $tokenId = (int) $row["id"];

        $update = $conn->prepare("UPDATE remember_tokens SET validator_hash = ?, expires_at = NOW() + INTERVAL ? DAY WHERE id = ?");
        $update->bind_param("sii", $newHash, $days, $tokenId);
        $update->execute();
        $update->close();

        emRememberSetCookie($selector . ":" . $newValidator, $expires);

        /* the same session values as login.php */
        session_regenerate_id(true);

        $_SESSION["user_id"] = (int) $row["user_id"];
        $_SESSION["username"] = $row["username"];
        $_SESSION["name"] = $row["name"];
        $_SESSION["role"] = $row["role"] ?? "user";
    } catch (Throwable $error) {
        /* table missing or database error: continue as a guest */
    }
}

/*
 * Checked on every page of a signed-in user:
 *  - the account no longer exists (deleted) -> signed out, with a message;
 *  - the account is blocked after the 5th approved report (banned_at)
 *    -> every page leads to the farewell page (farewell.php), which shows
 *       the message once and deletes the account.
 */
function emEnsureAccountExists(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION["user_id"])) {
        return;
    }

    if (basename((string) ($_SERVER["PHP_SELF"] ?? "")) === "farewell.php") {
        return;
    }

    global $conn;

    try {
        if (!($conn instanceof mysqli)) {
            require_once __DIR__ . "/db.php";
        }

        $userId = (int) $_SESSION["user_id"];
        try {
            $statement = $conn->prepare("SELECT banned_at FROM users WHERE id = ?");
        } catch (Throwable $error) {
            $statement = false;
        }
        if (!$statement) {
            /* older database without banned_at */
            $statement = $conn->prepare("SELECT NULL AS banned_at FROM users WHERE id = ?");
        }
        $statement->bind_param("i", $userId);
        $statement->execute();
        $account = $statement->get_result()->fetch_assoc();
        $statement->close();
    } catch (Throwable $error) {
        return;
    }

    if ($account && $account["banned_at"] !== null) {
        header("Location: farewell.php");
        exit();
    }

    if ($account) {
        return;
    }

    $_SESSION = [];
    emRememberClearCookie();
    session_destroy();
    header("Location: sign_in.php?error=account_removed");
    exit();
}

emRememberAutoLogin();
emEnsureAccountExists();
