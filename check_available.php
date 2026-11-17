<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - IS THIS USERNAME / E-MAIL FREE? (register form, live check)
|--------------------------------------------------------------------------
| GET check_available.php?field=username&value=maria
| Answer (JSON): {"valid": true|false, "taken": true|false}
| register.php checks the same things again on submit.
*/

session_start();
include "db.php";
require_once __DIR__ . "/account_rules.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$field = (string) ($_GET["field"] ?? "");
$value = trim((string) ($_GET["value"] ?? ""));

if ($field === "username") {
    $valid = emUsernameIsValid($value);
} elseif ($field === "email") {
    $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
} else {
    http_response_code(400);
    echo json_encode(["valid" => false, "taken" => false]);
    exit();
}

/* the signed-in user's own value is not "taken" */
$ownId = (int) ($_SESSION["user_id"] ?? 0);

echo json_encode([
    "valid" => $valid,
    "taken" => $valid && emAccountValueTaken($conn, $field, $value, $ownId),
]);
