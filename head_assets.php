<?php
/*
|--------------------------------------------------------------------------
| SHARED <head> ASSETS - included in the <head> of every page
|--------------------------------------------------------------------------
| theme.css : fonts, colours and navigation (loaded before the page CSS)
| lucide    : icon set, <i data-lucide="name"></i> becomes an SVG icon
| theme.js  : draws the icons and opens/closes the menus
|
| ASSET_VERSION is added to every CSS / JS link (file.css?v=...).
| It is the time of the most recently changed file in css/ and js/, so
| the browser downloads the files again automatically after any change
| and keeps them in its cache otherwise. Nothing to update by hand.
*/

if (!defined("ASSET_VERSION")) {
    $emAssetTime = 0;
    foreach (array_merge(glob(__DIR__ . "/css/*.css") ?: [], glob(__DIR__ . "/js/*.js") ?: []) as $emAssetFile) {
        $emAssetTime = max($emAssetTime, (int) @filemtime($emAssetFile));
    }
    define("ASSET_VERSION", (string) $emAssetTime);
}
?>
<link rel="stylesheet" href="css/theme.css?v=<?php echo ASSET_VERSION; ?>">
<script src="js/lucide.min.js?v=<?php echo ASSET_VERSION; ?>" defer></script>
<script src="js/theme.js?v=<?php echo ASSET_VERSION; ?>" defer></script>
