<?php

session_start();
require_once __DIR__ . "/remember_me.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();

include "db.php";


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    header("Location: sign_in.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| POST CHECK
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: settings.php");
    exit();

}


$user_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| FORM DATA
|--------------------------------------------------------------------------
*/

$current_password =
    $_POST["current_password"] ?? "";

$new_password =
    $_POST["new_password"] ?? "";

$confirm_new_password =
    $_POST["confirm_new_password"] ?? "";


/*
|--------------------------------------------------------------------------
| EMPTY FIELDS
|--------------------------------------------------------------------------
*/

if (
    $current_password === "" ||
    $new_password === "" ||
    $confirm_new_password === ""
) {

    header(
        "Location: settings.php?status=empty_fields"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| PASSWORD CONFIRMATION
|--------------------------------------------------------------------------
*/

if ($new_password !== $confirm_new_password) {

    header(
        "Location: settings.php?status=passwords_not_match"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| MINIMUM PASSWORD LENGTH
|--------------------------------------------------------------------------
*/

if (strlen($new_password) < 6) {

    header(
        "Location: settings.php?status=password_too_short"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| GET CURRENT PASSWORD HASH FROM DATABASE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT password
    FROM users
    WHERE id = ?
    LIMIT 1
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    header(
        "Location: settings.php?status=error"
    );

    exit();

}


$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();
    $conn->close();

    session_destroy();

    header("Location: sign_in.php");
    exit();

}


$user =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| VERIFY CURRENT PASSWORD
|--------------------------------------------------------------------------
*/

if (
    !password_verify(
        $current_password,
        $user["password"]
    )
) {

    $conn->close();

    header(
        "Location: settings.php?status=wrong_current_password"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| CREATE NEW PASSWORD HASH
|--------------------------------------------------------------------------
*/

$new_password_hash =
    password_hash(
        $new_password,
        PASSWORD_DEFAULT
    );


/*
|--------------------------------------------------------------------------
| UPDATE DATABASE
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE users
    SET password = ?
    WHERE id = ?
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    $conn->close();

    header(
        "Location: settings.php?status=error"
    );

    exit();

}


$stmt->bind_param(
    "si",
    $new_password_hash,
    $user_id
);


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    $stmt->close();

    /*
     * "Remember me": other browsers that stayed signed in are signed
     * out. This browser keeps it (new token) if it had it.
     */
    $hadRememberCookie = isset($_COOKIE["em_remember"]);
    emRememberForgetUser($conn, (int) $user_id);

    if ($hadRememberCookie) {
        emRememberIssue($conn, (int) $user_id);
    }

    $conn->close();

    header(
        "Location: settings.php?status=password_changed"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

$stmt->close();
$conn->close();


header(
    "Location: settings.php?status=error"
);

exit();

?>