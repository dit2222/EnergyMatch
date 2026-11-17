<?php
/*
|--------------------------------------------------------------------------
| NAVBAR (top bar on every page + icon bar at the bottom on mobile)
|--------------------------------------------------------------------------
| Logged in : Home, Profile, Matches, My Community (menu) | language,
|             notifications bell, user menu (settings, logout)
| Guest     : Home, About, Contact, FAQ | language, Sign in
*/

$currentPage = basename($_SERVER["PHP_SELF"]);
$isLoggedIn =
    isset($_SESSION["user_id"])
    && !($forceGuestNavbar ?? false);

$currentLanguage = $_SESSION["language"] ?? "en";

$communityPages = [
    "community.php",
    "accepted_invitations.php"
];

$navIsActive = function ($pages) use ($currentPage) {
    return in_array($currentPage, (array) $pages, true) ? "is-active" : "";
};

if ($isLoggedIn) {
    require_once __DIR__ . "/notification_count.php";

    /* Some pages close $conn before the navbar is printed: open a new one then */
    $navConnectionOpen = false;

    try {
        $navConnectionOpen = isset($conn) && $conn instanceof mysqli && $conn->query("SELECT 1") !== false;
    } catch (Throwable $exception) {
        $navConnectionOpen = false;
    }

    if (!$navConnectionOpen) {
        include __DIR__ . "/db.php";
    }

    $navNotificationCount = emNotificationCount($conn, (int) $_SESSION["user_id"]);

    $navUserName = trim((string) ($_SESSION["name"] ?? $_SESSION["username"] ?? ""));
    $navUserInitial = $navUserName !== "" ? mb_substr($navUserName, 0, 1, "UTF-8") : "?";
}
?>

<header class="em-topbar">

    <a class="em-brand" href="<?php echo $isLoggedIn ? "dashboard.php" : "index.php"; ?>">
        <i data-lucide="sun"></i>
        EnergyMatch
    </a>

    <?php if ($isLoggedIn): ?>

        <!-- LOGGED-IN USER MENU -->

        <ul class="em-nav">

            <li>
                <a href="dashboard.php" class="em-nav-link <?php echo $navIsActive("dashboard.php"); ?>">
                    <i data-lucide="house"></i>
                    <?php echo t("dashboard"); ?>
                </a>
            </li>

            <li>
                <a href="profile.php" class="em-nav-link <?php echo $navIsActive("profile.php"); ?>">
                    <i data-lucide="user-round"></i>
                    <?php echo t("profile"); ?>
                </a>
            </li>

            <li>
                <a href="matches.php" class="em-nav-link <?php echo $navIsActive("matches.php"); ?>">
                    <i data-lucide="users-round"></i>
                    <?php echo t("matches"); ?>
                </a>
            </li>

            <li class="em-has-dropdown">

                <a href="community.php" class="em-nav-link <?php echo $navIsActive($communityPages); ?>">
                    <i data-lucide="sun-medium"></i>
                    <?php echo t("nav_my_community"); ?>
                    <i data-lucide="chevron-down" class="em-chevron"></i>
                </a>

                <div class="em-dropdown">

                    <a href="community.php" class="em-dropdown-link <?php echo $navIsActive("community.php"); ?>">
                        <span class="em-dropdown-icon"><i data-lucide="users"></i></span>
                        <span>
                            <strong><?php echo t("nav_active_community"); ?></strong>
                            <small><?php echo t("nav_active_community_text"); ?></small>
                        </span>
                    </a>

                    <a href="accepted_invitations.php" class="em-dropdown-link <?php echo $navIsActive("accepted_invitations.php"); ?>">
                        <span class="em-dropdown-icon"><i data-lucide="mail-check"></i></span>
                        <span>
                            <strong><?php echo t("nav_accepted_invitations"); ?></strong>
                            <small><?php echo t("nav_accepted_invitations_text"); ?></small>
                        </span>
                    </a>

                </div>

            </li>

        </ul>

        <div class="em-nav-right">

            <div class="em-lang">
                <a href="set_language.php?lang=en" class="<?php echo $currentLanguage === "en" ? "is-active" : ""; ?>">EN</a>
                <a href="set_language.php?lang=gr" class="<?php echo $currentLanguage === "gr" ? "is-active" : ""; ?>">GR</a>
            </div>

            <a
                href="notifications.php"
                class="em-icon-btn <?php echo $navIsActive("notifications.php"); ?>"
                title="<?php echo t("nav_notifications"); ?>"
                aria-label="<?php echo t("nav_notifications"); ?>"
            >
                <i data-lucide="bell"></i>

                <?php if ($navNotificationCount > 0): ?>
                    <span class="em-count"><?php echo $navNotificationCount > 99 ? "99+" : $navNotificationCount; ?></span>
                <?php endif; ?>
            </a>

            <div class="em-has-dropdown">

                <button type="button" class="em-user-btn" data-em-toggle aria-expanded="false" aria-label="<?php echo t("nav_user_menu"); ?>">
                    <span class="em-avatar"><?php echo htmlspecialchars($navUserInitial, ENT_QUOTES, "UTF-8"); ?></span>
                    <span class="em-user-name"><?php echo htmlspecialchars($navUserName, ENT_QUOTES, "UTF-8"); ?></span>
                    <i data-lucide="chevron-down" class="em-chevron"></i>
                </button>

                <div class="em-dropdown em-dropdown-right">

                    <a href="settings.php" class="em-dropdown-link <?php echo $navIsActive("settings.php"); ?>">
                        <span class="em-dropdown-icon"><i data-lucide="settings"></i></span>
                        <span><strong><?php echo t("settings"); ?></strong></span>
                    </a>

                    <a href="accepted_invitations.php" class="em-dropdown-link em-mobile-only">
                        <span class="em-dropdown-icon"><i data-lucide="mail-check"></i></span>
                        <span><strong><?php echo t("nav_accepted_invitations"); ?></strong></span>
                    </a>

                    <div class="em-dropdown-link em-mobile-only">
                        <span class="em-dropdown-icon"><i data-lucide="languages"></i></span>
                        <span class="em-lang">
                            <a href="set_language.php?lang=en" class="<?php echo $currentLanguage === "en" ? "is-active" : ""; ?>">EN</a>
                            <a href="set_language.php?lang=gr" class="<?php echo $currentLanguage === "gr" ? "is-active" : ""; ?>">GR</a>
                        </span>
                    </div>

                    <div class="em-dropdown-divider"></div>

                    <a href="log_out.php" class="em-dropdown-link is-danger">
                        <span class="em-dropdown-icon"><i data-lucide="log-out"></i></span>
                        <span><strong><?php echo t("logout"); ?></strong></span>
                    </a>

                </div>

            </div>

        </div>

    <?php else: ?>

        <!-- GUEST MENU -->

        <ul class="em-nav">

            <li>
                <a href="index.php" class="em-nav-link <?php echo $navIsActive("index.php"); ?>">
                    <i data-lucide="house"></i>
                    <?php echo t("home"); ?>
                </a>
            </li>

            <li>
                <a href="aboutus.php" class="em-nav-link <?php echo $navIsActive("aboutus.php"); ?>">
                    <i data-lucide="info"></i>
                    <?php echo t("about_us"); ?>
                </a>
            </li>

            <li>
                <a href="contacts.php" class="em-nav-link <?php echo $navIsActive("contacts.php"); ?>">
                    <i data-lucide="mail"></i>
                    <?php echo t("contacts"); ?>
                </a>
            </li>

            <li>
                <a href="FAQ.php" class="em-nav-link <?php echo $navIsActive("FAQ.php"); ?>">
                    <i data-lucide="circle-help"></i>
                    <?php echo t("faq"); ?>
                </a>
            </li>

            <li class="em-mobile-only">
                <a href="sign_in.php" class="em-nav-link <?php echo $navIsActive("sign_in.php"); ?>">
                    <i data-lucide="log-in"></i>
                    <?php echo t("sign_in"); ?>
                </a>
            </li>

        </ul>

        <div class="em-nav-right">

            <div class="em-lang">
                <a href="set_language.php?lang=en" class="<?php echo $currentLanguage === "en" ? "is-active" : ""; ?>">EN</a>
                <a href="set_language.php?lang=gr" class="<?php echo $currentLanguage === "gr" ? "is-active" : ""; ?>">GR</a>
            </div>

            <a href="sign_in.php" class="ui-btn ui-btn-dark em-guest-cta <?php echo $navIsActive("sign_in.php"); ?>">
                <i data-lucide="log-in"></i>
                <?php echo t("sign_in"); ?>
            </a>

            <button type="button" class="em-icon-btn em-menu-btn" aria-expanded="false" aria-label="Menu">
                <i data-lucide="menu"></i>
            </button>

        </div>

    <?php endif; ?>

</header>

<div class="em-nav-spacer"></div>

<?php if ($isLoggedIn): ?>

    <!-- MOBILE ICON BAR -->

    <nav class="em-bottom-nav">

        <a href="dashboard.php" class="<?php echo $navIsActive("dashboard.php"); ?>">
            <i data-lucide="house"></i>
            <?php echo t("dashboard"); ?>
        </a>

        <a href="profile.php" class="<?php echo $navIsActive("profile.php"); ?>">
            <i data-lucide="user-round"></i>
            <?php echo t("profile"); ?>
        </a>

        <a href="matches.php" class="<?php echo $navIsActive("matches.php"); ?>">
            <i data-lucide="users-round"></i>
            <?php echo t("nav_matches_short"); ?>
        </a>

        <a href="community.php" class="<?php echo $navIsActive($communityPages); ?>">
            <i data-lucide="sun-medium"></i>
            <?php echo t("nav_community_short"); ?>
        </a>

    </nav>

<?php endif; ?>
