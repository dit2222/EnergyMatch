<?php
/*
|--------------------------------------------------------------------------
| ADMIN PAGES - GREEK / ENGLISH TEXTS
|--------------------------------------------------------------------------
| The admin pages write each text in both languages next to each other:
|     adminT("Registered users", "Εγγεγραμμένοι χρήστες")
| The language is the same as the rest of the site (EN / GR switch).
*/

require_once __DIR__ . "/lang.php";

function adminIsGreek(): bool
{
    return ($_SESSION["language"] ?? "en") === "gr";
}

function adminT(string $english, string $greek): string
{
    return adminIsGreek() ? $greek : $english;
}

/* Value for <html lang="..."> */
function adminLangCode(): string
{
    return adminIsGreek() ? "el" : "en";
}

/* Capital letters without Greek accents (ΕΝΕΡΓΗ, not ΕΝΕΡΓΉ) */
function adminUpper(string $text): string
{
    $text = mb_strtoupper($text, "UTF-8");
    return strtr($text, ["Ά" => "Α", "Έ" => "Ε", "Ή" => "Η", "Ί" => "Ι", "Ό" => "Ο", "Ύ" => "Υ", "Ώ" => "Ω", "Ϊ́" => "Ϊ", "Ϋ́" => "Ϋ"]);
}

/* Community, invitation, message and report statuses */
function adminStatusLabel($status): string
{
    $labels = [
        "active"    => ["Active", "Ενεργή"],
        "pending"   => ["Pending", "Σε αναμονή"],
        "cancelled" => ["Cancelled", "Ακυρωμένη"],
        "rejected"  => ["Rejected", "Απορριφθείσα"],
        "accepted"  => ["Accepted", "Αποδεκτή"],
        "declined"  => ["Declined", "Απορρίφθηκε"],
        "joined"    => ["Joined", "Μέλος"],
        "new"       => ["New", "Νέο"],
        "read"      => ["Read", "Διαβασμένο"],
        "answered"  => ["Answered", "Απαντημένο"],
        "reviewed"  => ["Reviewed", "Εξετάστηκε"],
        "dismissed" => ["Dismissed", "Απορρίφθηκε"],
    ];
    $key = strtolower(trim((string) $status));

    return isset($labels[$key])
        ? adminT($labels[$key][0], $labels[$key][1])
        : htmlspecialchars(ucfirst($key), ENT_QUOTES, "UTF-8");
}

/* Report reasons (same texts as report_user.php) */
function adminReasonLabel($reason): string
{
    $key = "reason_" . strtolower(trim((string) $reason));
    $text = t($key);

    return $text !== $key
        ? $text
        : htmlspecialchars(ucfirst(str_replace("_", " ", (string) $reason)), ENT_QUOTES, "UTF-8");
}
