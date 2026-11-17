<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - PASSWORD RESET HELPERS
|--------------------------------------------------------------------------
| Used by forgot_password.php and reset_password.php.
|
| No e-mail is sent: after the user gives the account e-mail,
| forgot_password.php opens the reset page directly.
|
| 1. emResetCreate()  random token; only its HASH is saved in
|                     password_resets (valid for 1 hour, one use)
| 2. emResetFind()    checks the token of the reset page
|--------------------------------------------------------------------------
*/

const EM_RESET_MINUTES = 60;
const EM_RESET_MIN_PASSWORD = 6;

/* Creates a reset token for the user and returns it */
function emResetCreate(mysqli $conn, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash("sha256", $token);
    $minutes = EM_RESET_MINUTES;

    /* expiry uses the database clock, the same clock that checks it */
    $insert = $conn->prepare(
        "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)"
    );
    $insert->bind_param("isi", $userId, $hash, $minutes);
    $insert->execute();
    $insert->close();

    /* old links that were never used are removed */
    $cleanup = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY");
    $cleanup->execute();
    $cleanup->close();

    return $token;
}

/* Returns the row of a valid (not used, not expired) token, or null */
function emResetFind(mysqli $conn, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $hash = hash("sha256", $token);
    $statement = $conn->prepare(
        "SELECT pr.id, pr.user_id, u.name, u.email
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $statement->bind_param("s", $hash);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ?: null;
}
