/* =========================================================
   ENERGYMATCH - THEME SCRIPT
   1. Draws Lucide icons (<i data-lucide="...">)
   2. Opens / closes the user menu and the guest mobile menu
========================================================= */

(function () {
    "use strict";

    /* Can be called again after new HTML is added to the page */
    window.emRefreshIcons = function () {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    };

    document.addEventListener("DOMContentLoaded", function () {
        window.emRefreshIcons();

        /* Space at the bottom of the page for the mobile icon bar */
        if (document.querySelector(".em-bottom-nav")) {
            document.body.classList.add("em-has-bottom-nav");
        }

        /* Menus that open on click (user menu, community menu on touch screens) */
        document.querySelectorAll("[data-em-toggle]").forEach(function (button) {
            const holder = button.closest(".em-has-dropdown");

            button.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();

                const open = !holder.classList.contains("is-open");

                document.querySelectorAll(".em-has-dropdown.is-open").forEach(function (other) {
                    other.classList.remove("is-open");
                });

                holder.classList.toggle("is-open", open);
                button.setAttribute("aria-expanded", open ? "true" : "false");
            });
        });

        document.addEventListener("click", function (event) {
            document.querySelectorAll(".em-has-dropdown.is-open").forEach(function (holder) {
                if (!holder.contains(event.target)) {
                    holder.classList.remove("is-open");
                }
            });
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                document.querySelectorAll(".em-has-dropdown.is-open").forEach(function (holder) {
                    holder.classList.remove("is-open");
                });

                const topbar = document.querySelector(".em-topbar.is-menu-open");

                if (topbar) {
                    topbar.classList.remove("is-menu-open");
                }
            }
        });

        /* Guest mobile menu button */
        const menuButton = document.querySelector(".em-menu-btn");

        if (menuButton) {
            menuButton.addEventListener("click", function () {
                const topbar = menuButton.closest(".em-topbar");
                const open = topbar.classList.toggle("is-menu-open");

                menuButton.setAttribute("aria-expanded", open ? "true" : "false");
            });
        }

        /* Contribution tag details: open downwards when there is no room above */
        function placeTagTip(event) {
            const tag = event.target.closest && event.target.closest(".em-ctag");

            if (!tag) {
                return;
            }

            const tip = tag.querySelector(".em-ctag-tip");
            const topbar = document.querySelector(".em-topbar");
            const topLimit = topbar ? topbar.getBoundingClientRect().bottom : 0;

            if (tip) {
                tag.classList.toggle(
                    "em-ctag--below",
                    tag.getBoundingClientRect().top - tip.offsetHeight - 16 < topLimit
                );
            }
        }

        document.addEventListener("mouseover", placeTagTip);
        document.addEventListener("focusin", placeTagTip);
    });
})();

/* =========================================================
   FORM FIELDS WITH A RULE (pattern) - clear message in the
   user's language instead of the browser's generic one.
   <input pattern="..." data-pattern-message="...">
   The server checks the same rules again (account_rules.php).
========================================================= */
(function () {
    "use strict";

    document.addEventListener("invalid", function (event) {
        const field = event.target;
        if (field.dataset && field.dataset.patternMessage && field.validity.patternMismatch) {
            field.setCustomValidity(field.dataset.patternMessage);
        }
    }, true);

    document.addEventListener("input", function (event) {
        const field = event.target;
        if (field.dataset && field.dataset.patternMessage) {
            field.setCustomValidity("");
        }
    }, true);
})();
