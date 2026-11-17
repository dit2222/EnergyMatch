<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - FAREWELL (after the 5th approved report)
|--------------------------------------------------------------------------
| moderation.php blocked the account (users.banned_at). Every page sends
| the user here (remember_me.php). The page shows the farewell message ONCE
| and deletes the account at the same moment; the user is signed out and
| can never sign in with it again.
*/

session_start();
require_once __DIR__ . "/remember_me.php";
require_once "db.php";
require_once __DIR__ . "/moderation.php";

$userId = (int) ($_SESSION["user_id"] ?? 0);
$farewellName = "";
$farewellGreek = ($_SESSION["language"] ?? "en") === "gr";

/* only a signed-in, blocked account sees this page */
$blocked = false;
if ($userId > 0) {
    try {
        $statement = $conn->prepare("SELECT name, banned_at FROM users WHERE id = ?");
        $statement->bind_param("i", $userId);
        $statement->execute();
        $account = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($account && $account["banned_at"] !== null) {
            $blocked = true;
            $farewellName = (string) $account["name"];
        }
    } catch (Throwable $error) {
        $blocked = false;
    }
}

if (!$blocked) {
    header("Location: index.php");
    exit();
}

/* the account is deleted now (its communities were already cancelled when it was blocked) */
try {
    emDeleteAccount($conn, $userId, "member_removed", null);
} catch (Throwable $error) {
    error_log("EnergyMatch farewell: account not deleted: " . $error->getMessage());
}

/* signed out for good; the language is kept for this page only */
$_SESSION = [];
emRememberClearCookie();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

function farewellText(string $gr, string $en): string
{
    global $farewellGreek;
    return htmlspecialchars($farewellGreek ? $gr : $en, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="<?php echo $farewellGreek ? "el" : "en"; ?>">
<head>
    <?php include "head_assets.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?php echo farewellText("Αντίο", "Goodbye"); ?> - EnergyMatch</title>
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 24px 16px;
            background: var(--em-bg);
            font-family: var(--em-font);
        }

        .farewell-card {
            width: 100%;
            max-width: 560px;
            padding: 40px 34px;
            border: 1px solid var(--em-border);
            border-radius: 24px;
            background: var(--em-surface);
            box-shadow: var(--em-shadow-lg);
            text-align: center;
        }

        .farewell-brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--em-green);
            font-size: 18px;
            font-weight: 800;
        }

        .farewell-icon {
            display: grid;
            place-items: center;
            width: 64px;
            height: 64px;
            margin: 26px auto 18px;
            border-radius: 20px;
            background: var(--em-danger-50);
            color: var(--em-danger);
        }

        .farewell-icon svg {
            width: 30px;
            height: 30px;
        }

        .farewell-card h1 {
            margin: 0 0 14px;
            color: var(--em-text);
            font-size: 28px;
        }

        .farewell-card p {
            margin: 0 0 12px;
            color: var(--em-muted);
            font-size: 15px;
            line-height: 1.6;
        }

        .farewell-card .farewell-final {
            margin-top: 18px;
            padding: 14px;
            border-radius: 14px;
            background: var(--em-bg);
            color: var(--em-text);
            font-weight: 600;
        }

        .farewell-card .ui-btn {
            display: inline-flex;
            margin-top: 22px;
        }
    </style>
</head>
<body>

<main class="farewell-card">
    <div class="farewell-brand"><i data-lucide="sun"></i> EnergyMatch</div>

    <div class="farewell-icon"><i data-lucide="user-x"></i></div>

    <h1><?php echo farewellText("Αντίο" . ($farewellName !== "" ? ", " . $farewellName : ""), "Goodbye" . ($farewellName !== "" ? ", " . $farewellName : "")); ?></h1>

    <p><?php echo farewellText(
        "Μετά από επανειλημμένες αναφορές άλλων μελών, που εγκρίθηκαν από τον διαχειριστή, σε αποχαιρετάμε από το EnergyMatch, σύμφωνα με τον Κώδικα συμπεριφοράς.",
        "After repeated reports by other members, approved by the administrator, we say goodbye to you from EnergyMatch, in line with the Code of conduct."
    ); ?></p>

    <p><?php echo farewellText(
        "Ο λογαριασμός σου και όλα τα στοιχεία του διαγράφηκαν οριστικά.",
        "Your account and all its data have been permanently deleted."
    ); ?></p>

    <p class="farewell-final"><?php echo farewellText(
        "Δεν μπορείς να συνδεθείς ξανά με αυτόν τον λογαριασμό.",
        "You can no longer sign in with this account."
    ); ?></p>

    <a href="index.php" class="ui-btn ui-btn-ghost">
        <i data-lucide="house"></i>
        <?php echo farewellText("Αρχική σελίδα", "Home page"); ?>
    </a>
</main>

</body>
</html>
