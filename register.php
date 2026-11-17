<?php

session_start();
require_once __DIR__ . "/remember_me.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();

include "db.php";
require_once __DIR__ . "/account_rules.php";


// Ελέγχουμε ότι η σελίδα κλήθηκε μέσω POST

if($_SERVER["REQUEST_METHOD"] !== "POST"){

    header("Location: register_form.php");
    exit();

}


// Παίρνουμε τα δεδομένα από τη φόρμα

$name = trim($_POST["name"] ?? "");

$surname = trim($_POST["surname"] ?? "");

$address = trim($_POST["address"] ?? "");

$region = trim($_POST["region"] ?? "");

$city = trim($_POST["city"] ?? "");

$postal_code = trim($_POST["postal_code"] ?? "");

$phone = trim($_POST["phone"] ?? "");

$email = trim($_POST["email"] ?? "");

$username = trim($_POST["username"] ?? "");

$password = $_POST["password"] ?? "";

$confirm_password = $_POST["confirm_password"] ?? "";


/*
 * Σε σφάλμα επιστρέφουμε στη φόρμα με κωδικό σφάλματος (?error=...).
 * Τα στοιχεία που έγραψε ο χρήστης (εκτός από τους κωδικούς) κρατιούνται,
 * ώστε να μη χρειάζεται να τα ξαναγράψει.
 */
const REGISTER_MIN_PASSWORD = 6;

function registerFail(string $error): void
{
    $keep = ["name", "surname", "address", "region", "city", "postal_code", "phone", "email", "username"];
    $old = [];
    foreach ($keep as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ""));
    }
    $_SESSION["register_old"] = $old;

    header("Location: register_form.php?error=" . urlencode($error));
    exit();
}

// Έλεγχος ότι συμπληρώθηκαν τα βασικά πεδία

if(
    $name === ""
    ||
    $surname === ""
    ||
    $email === ""
    ||
    $username === ""
    ||
    $password === ""
    ||
    $phone === ""
    ||
    $address === ""
    ||
    $region === ""
    ||
    $city === ""
    ||
    $postal_code === ""
){

    registerFail("empty_fields");

}


// Όνομα και επώνυμο χωρίς ψηφία, τηλέφωνο ακριβώς 10 ψηφία (account_rules.php)

if(!emPersonNameIsValid($name) || !emPersonNameIsValid($surname)){

    registerFail("invalid_name");

}

if(!emPhoneIsValid($phone)){

    registerFail("invalid_phone");

}


// ΤΚ ακριβώς 5 ψηφία, πόλη και περιοχή μόνο γράμματα (account_rules.php)

if(!emPostalCodeIsValid($postal_code)){

    registerFail("invalid_postal_code");

}

if(!emPlaceIsValid($city) || !emPlaceIsValid($region)){

    registerFail("invalid_place");

}


// Username: μόνο λατινικοί χαρακτήρες, ψηφία ή "_", χωρίς κενά, 3 έως 15 (account_rules.php)

if(!emUsernameIsValid($username)){

    registerFail("invalid_username");

}


// Έλεγχος email

if(!emEmailIsValid($email)){

    registerFail("invalid_email");

}


// Ελάχιστο μήκος κωδικού (ίδιο με την αλλαγή και την επαναφορά κωδικού)
if(strlen($password) < REGISTER_MIN_PASSWORD){
    registerFail("password_short");
}

// Έλεγχος αν οι κωδικοί ταιριάζουν

if($password !== $confirm_password){

    registerFail("password_mismatch");

}


// Το username και το email ανήκουν σε έναν μόνο λογαριασμό (ξεχωριστό μήνυμα για το καθένα)

if(emAccountValueTaken($conn, "username", $username)){

    registerFail("username_taken");

}

if(emAccountValueTaken($conn, "email", $email)){

    registerFail("email_taken");

}


// Κρυπτογράφηση κωδικού

$hashed_password = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// Εισαγωγή χρήστη στη βάση

$sql = "

INSERT INTO users
(
    name,
    surname,
    address,
    region,
    city,
    postal_code,
    phone,
    email,
    username,
    password
)

VALUES
(
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?
)

";


$stmt = $conn->prepare($sql);


if(!$stmt){

    die("Database error.");

}


$stmt->bind_param(
    "ssssssssss",
    $name,
    $surname,
    $address,
    $region,
    $city,
    $postal_code,
    $phone,
    $email,
    $username,
    $hashed_password
);


// Δημιουργία λογαριασμού
// (αν δύο εγγραφές με ίδιο username/email γίνουν ταυτόχρονα, τη δεύτερη
//  τη σταματά το UNIQUE της βάσης και εμφανίζεται το ίδιο μήνυμα)

try {
    $created = $stmt->execute();
} catch (mysqli_sql_exception $error) {
    if ((int) $error->getCode() === 1062) {
        registerFail("account_exists");
    }
    throw $error;
}

if($created){


    $new_user_id = $conn->insert_id;

    $_SESSION["user_id"] = $new_user_id;

    $_SESSION["username"] = $username;

    $stmt->close();

    $conn->close();


    unset($_SESSION["register_old"]);
    header("Location: profile.php");

    exit();


}
else{


    echo "Σφάλμα κατά τη δημιουργία λογαριασμού.";


}


$stmt->close();

$conn->close();

?>