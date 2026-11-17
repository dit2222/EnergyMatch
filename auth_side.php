<?php
/*
|--------------------------------------------------------------------------
| GREEN SIDE PANEL of the sign-in and register pages
|--------------------------------------------------------------------------
| Set $authSideTitle and $authSideText before including this file.
*/
?>
<aside class="auth-side">

    <div class="auth-side-intro">
        <span class="auth-side-eyebrow">EnergyMatch</span>
        <h2><?php echo $authSideTitle ?? ""; ?></h2>
        <p><?php echo $authSideText ?? ""; ?></p>
    </div>

    <ul class="auth-points">
        <li><span><i data-lucide="users-round"></i></span><?php echo t("home_point_match"); ?></li>
        <li><span><i data-lucide="scale"></i></span><?php echo t("home_point_fair"); ?></li>
        <li><span><i data-lucide="sun"></i></span><?php echo t("auth_point_kinds"); ?></li>
    </ul>

</aside>
