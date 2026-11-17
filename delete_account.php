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
| ONLY POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: settings.php");
    exit();
}


$user_id = (int) $_SESSION["user_id"];

$confirmation = trim($_POST["delete_confirmation"] ?? "");


/*
|--------------------------------------------------------------------------
| CONFIRMATION CHECK
|--------------------------------------------------------------------------
*/

if ($confirmation !== "DELETE") {
    header("Location: settings.php?status=delete_confirmation_error");
    exit();
}


/*
|--------------------------------------------------------------------------
| DELETE THE ACCOUNT (moderation.php -> emDeleteAccount)
|--------------------------------------------------------------------------
|
| Το ίδιο με την αποχώρηση: κάθε ενεργή κοινότητα ή πρόταση σε αναμονή του
| χρήστη ακυρώνεται, τα άλλα μέλη ενημερώνονται ("member_account_deleted")
| και την κρατούν στο Ιστορικό τους. Μετά σβήνεται ο λογαριασμός με όλα
| τα δεδομένα του. Όλα γίνονται σε ένα transaction.
|
*/

require_once __DIR__ . "/moderation.php";

try {

    emDeleteAccount($conn, $user_id, "member_account_deleted", null);

    /*
    |--------------------------------------------------------------------------
    | DESTROY SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    emRememberClearCookie();

    $conn->close();


    /*
    |--------------------------------------------------------------------------
    | RETURN TO HOME PAGE
    |--------------------------------------------------------------------------
    */

    header("Location: index.php?status=account_deleted");
    exit();


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | SOMETHING FAILED -> RESTORE EVERYTHING
    |--------------------------------------------------------------------------
    */

    $conn->close();

    header("Location: settings.php?status=delete_error");
    exit();
}

?>