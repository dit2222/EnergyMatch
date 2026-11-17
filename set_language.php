<?php

session_start();
require_once __DIR__ . "/remember_me.php";


/*
|--------------------------------------------------------------------------
| Επιτρεπόμενες γλώσσες
|--------------------------------------------------------------------------
*/

$allowed_languages = [
    "en",
    "gr"
];


$language =
    $_GET["lang"] ?? "en";


if(
    in_array(
        $language,
        $allowed_languages,
        true
    )
){

    $_SESSION["language"] =
        $language;

    /* remember the choice for one year (read by lang.php) */
    setcookie("em_language", $language, [
        "expires" => time() + 365 * 24 * 60 * 60,
        "path" => "/",
        "samesite" => "Lax",
    ]);

}


/*
|--------------------------------------------------------------------------
| Επιστροφή στην προηγούμενη σελίδα
|--------------------------------------------------------------------------
*/

$previous_page =
    $_SERVER["HTTP_REFERER"]
    ?? "dashboard.php";


header(
    "Location: " .
    $previous_page
);

exit();

?>