<?php

session_start();


// "Να με θυμάσαι": σβήνουμε το token αυτού του browser

if (isset($_COOKIE["em_remember"])) {

    require_once __DIR__ . "/remember_me.php";
    include __DIR__ . "/db.php";
    emRememberForgetThisBrowser($conn);
    $conn->close();

}


// Καθαρίζουμε όλα τα δεδομένα του session

$_SESSION = [];


// Διαγράφουμε και το session cookie

if(ini_get("session.use_cookies")){

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


// Καταστρέφουμε το session

session_destroy();


// Μεταφορά στην αρχική σελίδα

header("Location: index.php");

exit();

?>