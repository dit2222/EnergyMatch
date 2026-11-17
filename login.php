<?php

session_start();

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();

include "db.php";


// Παίρνουμε τα στοιχεία από τη φόρμα

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';


// Αν κάποιο πεδίο είναι κενό

if ($username === '' || $password === '') {

    header("Location: sign_in.php?error=empty_fields");
    exit();

}


/*
|--------------------------------------------------------------------------
| ΟΡΙΟ ΑΠΟΤΥΧΗΜΕΝΩΝ ΠΡΟΣΠΑΘΕΙΩΝ (πίνακας login_attempts)
|--------------------------------------------------------------------------
| 5 λάθος προσπάθειες για το ίδιο όνομα χρήστη (ή 20 από την ίδια
| διεύθυνση IP) μέσα σε 5 λεπτά -> η σύνδεση μπλοκάρεται μέχρι να
| περάσουν τα 5 λεπτά. Αν ο πίνακας δεν υπάρχει, η σύνδεση δουλεύει
| κανονικά χωρίς όριο.
*/

const LOGIN_MAX_PER_USER = 5;
const LOGIN_MAX_PER_IP = 20;
const LOGIN_WINDOW_MINUTES = 5;

$loginKey = mb_strtolower($username, "UTF-8");
$loginIp = substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);

/* Seconds until a new attempt is allowed (0 = allowed) */
function loginWaitSeconds(mysqli $conn, string $loginKey, string $loginIp): int
{
    try {
        $statement = $conn->prepare(
            "SELECT
                 SUM(username = ?) AS user_failures,
                 SUM(ip_address = ?) AS ip_failures,
                 TIMESTAMPDIFF(SECOND, NOW(), MIN(attempted_at) + INTERVAL ? MINUTE) AS wait_seconds
             FROM login_attempts
             WHERE attempted_at > NOW() - INTERVAL ? MINUTE
               AND (username = ? OR ip_address = ?)"
        );
        $window = LOGIN_WINDOW_MINUTES;
        $statement->bind_param("ssiiss", $loginKey, $loginIp, $window, $window, $loginKey, $loginIp);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ((int) $row["user_failures"] >= LOGIN_MAX_PER_USER || (int) $row["ip_failures"] >= LOGIN_MAX_PER_IP) {
            return max(1, (int) $row["wait_seconds"]);
        }
    } catch (Throwable $error) {
        /* no login_attempts table yet: no limit */
    }

    return 0;
}

function loginRecordFailure(mysqli $conn, string $loginKey, string $loginIp): void
{
    try {
        $statement = $conn->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
        $statement->bind_param("ss", $loginKey, $loginIp);
        $statement->execute();
        $statement->close();

        /* rows older than a day are not needed */
        $conn->query("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY");
    } catch (Throwable $error) {
        /* no login_attempts table yet */
    }
}

/* Same answer for an unknown user and a wrong password (nobody can find out which usernames exist) */
function loginFailed(mysqli $conn, string $loginKey, string $loginIp): void
{
    loginRecordFailure($conn, $loginKey, $loginIp);
    $conn->close();
    header("Location: sign_in.php?error=invalid_credentials");
    exit();
}

$waitSeconds = loginWaitSeconds($conn, $loginKey, $loginIp);

if ($waitSeconds > 0) {
    $conn->close();
    header("Location: sign_in.php?error=too_many_attempts&wait=" . (int) ceil($waitSeconds / 60));
    exit();
}


// Ψάχνουμε τον χρήστη στη βάση

$sql = "
    SELECT *
    FROM users
    WHERE username = ?
    OR email = ?
    LIMIT 1
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die("Database error: " . $conn->error);

}


$stmt->bind_param(
    "ss",
    $username,
    $username
);


$stmt->execute();


$result = $stmt->get_result();


if ($result->num_rows > 0) {


    $user = $result->fetch_assoc();


    // Έλεγχος κωδικού

    if (
        password_verify(
            $password,
            $user['password']
        )
    ) {


        // Προστασία από session fixation
        session_regenerate_id(true);

        // New CSRF token for the newly logged-in user
        csrfRegenerate();


        // Δημιουργία session

        $_SESSION['user_id'] =
            (int) $user['id'];

        $_SESSION['username'] =
            $user['username'];

        $_SESSION['name'] =
            $user['name'];

        $_SESSION['role'] =
            $user['role'] ?? 'user';


        // Επιτυχής σύνδεση: μηδενίζουμε τις αποτυχημένες προσπάθειες
        try {
            $clearAttempts = $conn->prepare("DELETE FROM login_attempts WHERE username = ?");
            $clearAttempts->bind_param("s", $loginKey);
            $clearAttempts->execute();
            $clearAttempts->close();
        } catch (Throwable $error) {
            /* no login_attempts table yet */
        }


        // "Να με θυμάσαι": cookie 30 ημερών (βλ. remember_me.php)

        if (!empty($_POST['remember'])) {

            require_once __DIR__ . "/remember_me.php";
            emRememberIssue($conn, (int) $user['id']);

        }


        // Redirect ανάλογα με τον ρόλο

        if ($_SESSION['role'] === 'admin') {

            header("Location: admin_dashboard.php");
            exit();

        }


        header("Location: dashboard.php");
        exit();


    } else {


        // Λάθος κωδικός

        loginFailed($conn, $loginKey, $loginIp);

    }


} else {


    // Δεν υπάρχει χρήστης

    // Ίδιος χρόνος απάντησης με τον λάθος κωδικό
    password_verify($password, '$2y$10$.FHddfyL9gc2KNiaSsZx4OZygsnpEZA4jeeHUUvBqgCv9FYXhEXyi');
    loginFailed($conn, $loginKey, $loginIp);

}


$stmt->close();

$conn->close();

?>