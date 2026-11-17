<?php

session_start();
require_once __DIR__ . "/remember_me.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();

require_once "db.php";


/* =========================================
   ONLY POST REQUESTS
========================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: contacts.php");
    exit();

}


/* =========================================
   GET FORM DATA
========================================= */

$fullName = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$message = trim($_POST["message"] ?? "");


/* =========================================
   VALIDATION
========================================= */

if (
    $fullName === "" ||
    $email === "" ||
    $subject === "" ||
    $message === ""
) {

    header(
        "Location: contacts.php?error=empty_fields"
    );

    exit();

}


/* EMAIL VALIDATION */

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    header(
        "Location: contacts.php?error=invalid_email"
    );

    exit();

}


/* LENGTH VALIDATION */

if (
    mb_strlen($fullName) > 120 ||
    mb_strlen($email) > 150 ||
    mb_strlen($subject) > 200 ||
    mb_strlen($message) > 5000
) {

    header(
        "Location: contacts.php?error=too_long"
    );

    exit();

}


/* =========================================
   SAVE MESSAGE
========================================= */

/*
 * Signed-in users: the message also keeps the user id, so the user is
 * notified when the admin answers (migration_notifications.sql).
 * Without that migration the message is saved the old way.
 */
$senderUserId = isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;

try {
    $stmt = $conn->prepare("
        INSERT INTO contact_messages
        (
            user_id,
            full_name,
            email,
            subject,
            message
        )
        VALUES (?, ?, ?, ?, ?)
    ");
} catch (Throwable $error) {
    $stmt = false;
}

$stmtHasUser = (bool) $stmt;

if (!$stmt) {
    $stmt = $conn->prepare("
        INSERT INTO contact_messages
        (
            full_name,
            email,
            subject,
            message
        )
        VALUES (?, ?, ?, ?)
    ");
}


if (!$stmt) {

    error_log(
        "Contact prepare error: " .
        $conn->error
    );

    header(
        "Location: contacts.php?error=database"
    );

    exit();

}


if ($stmtHasUser) {
    $stmt->bind_param(
        "issss",
        $senderUserId,
        $fullName,
        $email,
        $subject,
        $message
    );
} else {
    $stmt->bind_param(
        "ssss",
        $fullName,
        $email,
        $subject,
        $message
    );
}


if (!$stmt->execute()) {

    error_log(
        "Contact execute error: " .
        $stmt->error
    );

    $stmt->close();
    $conn->close();

    header(
        "Location: contacts.php?error=database"
    );

    exit();

}


$stmt->close();

$conn->close();


/* =========================================
   SUCCESS
========================================= */

header(
    "Location: contacts.php?success=message_sent"
);

exit();

?>