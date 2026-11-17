<?php

session_start();
require_once __DIR__ . "/remember_me.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();

include "db.php";
require_once __DIR__ . "/account_rules.php";


/*
|--------------------------------------------------------------------------
| CHECK IF USER IS LOGGED IN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| ALLOW ONLY POST REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: settings.php");
    exit();
}


$user_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/

$name = trim($_POST["name"] ?? "");
$surname = trim($_POST["surname"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");
$region = trim($_POST["region"] ?? "");
$city = trim($_POST["city"] ?? "");
$postal_code = trim($_POST["postal_code"] ?? "");


/*
|--------------------------------------------------------------------------
| CHECK REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $name === "" ||
    $surname === "" ||
    $email === "" ||
    $phone === "" ||
    $address === "" ||
    $region === "" ||
    $city === "" ||
    $postal_code === ""
) {
    header("Location: settings.php?status=empty_fields");
    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK EMAIL FORMAT
|--------------------------------------------------------------------------
*/

if (!emEmailIsValid($email)) {
    header("Location: settings.php?status=invalid_email");
    exit();
}

/* same rules as the registration (account_rules.php) */
if (!emPersonNameIsValid($name) || !emPersonNameIsValid($surname)) {
    header("Location: settings.php?status=invalid_name");
    exit();
}

if (!emPostalCodeIsValid($postal_code)) {
    header("Location: settings.php?status=invalid_postal_code");
    exit();
}

if (!emPlaceIsValid($city) || !emPlaceIsValid($region)) {
    header("Location: settings.php?status=invalid_place");
    exit();
}

if (!emPhoneIsValid($phone)) {
    header("Location: settings.php?status=invalid_phone");
    exit();
}


/*
|--------------------------------------------------------------------------
| USERNAME
|--------------------------------------------------------------------------
|
| Το username ορίζεται στην εγγραφή και δεν αλλάζει: δεν διαβάζεται από
| τη φόρμα και δεν ενημερώνεται εδώ (ακόμα κι αν σταλεί).
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CHECK IF EMAIL EXISTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT id
    FROM users
    WHERE email = ?
    AND id != ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    header("Location: settings.php?status=error");
    exit();
}

$stmt->bind_param("si", $email, $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $stmt->close();
    $conn->close();

    header("Location: settings.php?status=email_exists");
    exit();
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| UPDATE USER IN DATABASE
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE users
    SET
        name = ?,
        surname = ?,
        address = ?,
        region = ?,
        city = ?,
        postal_code = ?,
        phone = ?,
        email = ?
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    header("Location: settings.php?status=error");
    exit();
}

$stmt->bind_param(
    "ssssssssi",
    $name,
    $surname,
    $address,
    $region,
    $city,
    $postal_code,
    $phone,
    $email,
    $user_id
);


/*
|--------------------------------------------------------------------------
| EXECUTE UPDATE
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    $stmt->close();
    $conn->close();

    header("Location: settings.php?status=updated");
    exit();
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

$stmt->close();
$conn->close();

header("Location: settings.php?status=error");
exit();

?>