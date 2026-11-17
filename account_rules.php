<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - ACCOUNT RULES (register, settings, availability check)
|--------------------------------------------------------------------------
| Username: Latin letters, digits or "_" only, no spaces, 3 to 15
|           characters. It is chosen once at registration and cannot be
|           changed later (settings show it read-only).
| Unique:   username and e-mail belong to one account only (the database
|           also has UNIQUE keys on both). Upper/lower case does not
|           matter: "Maria" and "maria" are the same username.
| Phone:    exactly 10 digits, nothing else (no spaces, +, -).
| E-mail:   name@domain.tld form.
| Name and surname: any letters, but no digit.
| Postal code: exactly 5 digits (Greek format, e.g. 22100).
| City, region, profile location: letters only (Greek or Latin, accents
|           allowed) with spaces, "-", "." or "'" between words - no digits
|           or other symbols (e.g. "Νέα Σμύρνη", "Αγ. Νικόλαος").
|--------------------------------------------------------------------------
*/

const EM_USERNAME_MIN = 3;
const EM_USERNAME_MAX = 15;

/* The same rule is used by the HTML pattern of the register form */
const EM_USERNAME_PATTERN = "[A-Za-z0-9_]{3,15}";

function emUsernameIsValid(string $username): bool
{
    return preg_match('/^' . EM_USERNAME_PATTERN . '$/', $username) === 1;
}

/*
 * Is this value already used by another account?
 * $field is "username" or "email"; $exceptUserId skips the user's own row.
 */
function emAccountValueTaken(mysqli $conn, string $field, string $value, int $exceptUserId = 0): bool
{
    if (!in_array($field, ["username", "email"], true) || $value === "") {
        return false;
    }

    $statement = $conn->prepare(
        "SELECT 1 FROM users WHERE LOWER(`$field`) = LOWER(?) AND id <> ? LIMIT 1"
    );
    $statement->bind_param("si", $value, $exceptUserId);
    $statement->execute();
    $taken = $statement->get_result()->num_rows > 0;
    $statement->close();

    return $taken;
}

/* The same rules are used by the HTML pattern of the forms */
const EM_PHONE_PATTERN = "[0-9]{10}";
const EM_PERSON_NAME_PATTERN = "[^0-9]+";
const EM_EMAIL_PATTERN = "[^@\\s]+@[^@\\s]+\\.[^@\\s]+";

function emPhoneIsValid(string $phone): bool
{
    return preg_match('/^[0-9]{10}$/', $phone) === 1;
}

/* name or surname: not empty and without any digit */
function emPersonNameIsValid(string $name): bool
{
    return $name !== "" && preg_match('/[0-9]/', $name) === 0;
}

function emEmailIsValid(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) === 1;
}

/* postal code: exactly 5 digits */
const EM_POSTAL_CODE_PATTERN = "[0-9]{5}";

/* place names (city, region, profile location): starts with a letter, then
   letters, spaces, "-", "." or "'" only. \p{L} = any letter of any alphabet */
const EM_PLACE_PATTERN = "\\p{L}[\\p{L}\\s.'\\-]*";

function emPostalCodeIsValid(string $postalCode): bool
{
    return preg_match('/^[0-9]{5}$/', $postalCode) === 1;
}

function emPlaceIsValid(string $place): bool
{
    return preg_match("/^\\p{L}[\\p{L}\\s.'\\-]*$/u", $place) === 1;
}
