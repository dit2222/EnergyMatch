<?php
/*
|--------------------------------------------------------------------------
| ADMIN NAVBAR - the same menu on every admin page
|--------------------------------------------------------------------------
| Uses the navigation styles of css/theme.css (section 5).
| On phones the links open with the menu button.
*/

$adminCurrentPage = basename($_SERVER["PHP_SELF"]);

$adminLinks = [
    ["admin_dashboard.php", "layout-dashboard", "admin_nav_dashboard", ["admin_dashboard.php"]],
    ["admin_users.php", "users", "admin_nav_users", ["admin_users.php"]],
    ["admin_communities.php", "sun-medium", "admin_nav_communities", ["admin_communities.php", "admin_community_view.php"]],
    ["admin_reports.php", "flag", "admin_nav_reports", ["admin_reports.php"]],
    ["admin_messages.php", "mail", "admin_nav_messages", ["admin_messages.php"]],
];

if (!function_exists("t")) {
    require_once __DIR__ . "/lang.php";
}

/*
 * Red counters on the menu: what still waits for the admin.
 *   Reports   -> reports with status "pending" (not approved / dismissed yet)
 *   Messages  -> contact messages with status "new" (not read / answered yet)
 * A counter disappears when the admin handles them on that page.
 */
$adminWaiting = ["admin_reports.php" => 0, "admin_messages.php" => 0];

try {
    if (!(isset($conn) && $conn instanceof mysqli)) {
        include __DIR__ . "/db.php";
    }
    $waitingRow = $conn->query(
        "SELECT
            (SELECT COUNT(*) FROM user_reports WHERE status = 'pending') AS reports,
            (SELECT COUNT(*) FROM contact_messages WHERE status = 'new') AS messages"
    )->fetch_assoc();
    $adminWaiting["admin_reports.php"] = (int) ($waitingRow["reports"] ?? 0);
    $adminWaiting["admin_messages.php"] = (int) ($waitingRow["messages"] ?? 0);
} catch (Throwable $error) {
    /* no counters if the database cannot be read; the menu still works */
}

$adminWaitingTotal = array_sum($adminWaiting);

$adminWaitingTitles = [
    "admin_reports.php" => ($_SESSION["language"] ?? "en") === "gr" ? "αναφορές σε αναμονή" : "pending reports",
    "admin_messages.php" => ($_SESSION["language"] ?? "en") === "gr" ? "νέα μηνύματα" : "new messages",
];
?>

<header class="em-topbar em-admin-topbar">

    <a class="em-brand" href="admin_dashboard.php">
        <i data-lucide="sun"></i>
        EnergyMatch
        <span class="em-admin-badge">ADMIN</span>
    </a>

    <ul class="em-nav">

        <?php foreach ($adminLinks as $adminLink): ?>
            <li>
                <?php $waiting = $adminWaiting[$adminLink[0]] ?? 0; ?>
                <a
                    href="<?php echo $adminLink[0]; ?>"
                    class="em-nav-link <?php echo in_array($adminCurrentPage, $adminLink[3], true) ? "is-active" : ""; ?> <?php echo $waiting > 0 ? "has-waiting" : ""; ?>"
                >
                    <i data-lucide="<?php echo $adminLink[1]; ?>"></i>
                    <?php echo t($adminLink[2]); ?>
                    <?php if ($waiting > 0): ?>
                        <span class="em-nav-count" title="<?php echo $waiting . " " . $adminWaitingTitles[$adminLink[0]]; ?>">
                            <?php echo $waiting > 99 ? "99+" : $waiting; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>

    </ul>

    <div class="em-nav-right">

        <?php $adminLanguage = $_SESSION["language"] ?? "en"; ?>
        <div class="em-lang">
            <a href="set_language.php?lang=en" class="<?php echo $adminLanguage === "en" ? "is-active" : ""; ?>">EN</a>
            <a href="set_language.php?lang=gr" class="<?php echo $adminLanguage === "gr" ? "is-active" : ""; ?>">GR</a>
        </div>

        <a href="log_out.php" class="ui-btn ui-btn-ghost em-admin-logout">
            <i data-lucide="log-out"></i>
            <?php echo t("logout"); ?>
        </a>

        <button type="button" class="em-icon-btn em-menu-btn" aria-expanded="false" aria-label="<?php echo ($_SESSION["language"] ?? "en") === "gr" ? "Μενού" : "Menu"; ?>">
            <i data-lucide="menu"></i>
            <?php if ($adminWaitingTotal > 0): ?>
                <span class="em-count"><?php echo $adminWaitingTotal > 99 ? "99+" : $adminWaitingTotal; ?></span>
            <?php endif; ?>
        </button>

    </div>

</header>

<div class="em-nav-spacer"></div>
