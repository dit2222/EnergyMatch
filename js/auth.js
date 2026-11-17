/* =========================================================
   SIGN-IN / REGISTER PAGES
   1. Show / hide password (eye button)
   2. Register form in two steps (step 1 is checked first)
   3. Register: username / e-mail checked while typing
      (rules + "already used by another account")
========================================================= */

(function () {
    "use strict";

    /* ---------- 1. SHOW / HIDE PASSWORD ---------- */

    document.querySelectorAll("[data-show-password]").forEach(function (button) {
        button.addEventListener("click", function () {
            const input = document.getElementById(button.dataset.showPassword);

            if (!input) {
                return;
            }

            const show = input.type === "password";
            input.type = show ? "text" : "password";
            button.innerHTML = '<i data-lucide="' + (show ? "eye-off" : "eye") + '"></i>';

            if (window.emRefreshIcons) {
                window.emRefreshIcons();
            }
        });
    });

    /* ---------- 2. REGISTER IN TWO STEPS ---------- */

    const form = document.getElementById("registerForm");

    if (!form) {
        return;
    }

    const steps = form.querySelectorAll(".auth-step");
    const progress = form.querySelectorAll("[data-progress]");
    const password = form.querySelector("#password");
    const confirmPassword = form.querySelector("#confirm_password");

    form.classList.add("is-stepped");

    function showStep(number) {
        steps.forEach(function (step) {
            step.hidden = step.dataset.step !== String(number);
        });

        progress.forEach(function (item) {
            const value = Number(item.dataset.progress);
            item.classList.toggle("is-current", value <= number);
        });

        const firstField = form.querySelector('.auth-step[data-step="' + number + '"] input');

        if (firstField) {
            firstField.focus();
        }
    }

    function passwordsMatch() {
        const same = password.value === confirmPassword.value;
        confirmPassword.setCustomValidity(same ? "" : confirmPassword.dataset.mismatchMessage);
        return same;
    }

    confirmPassword.addEventListener("input", passwordsMatch);
    password.addEventListener("input", function () {
        if (confirmPassword.value !== "") {
            passwordsMatch();
        }
    });

    /* ---------- 3. USERNAME / E-MAIL AVAILABILITY ---------- */

    const pendingChecks = new Map();

    function setAvailability(input, text, state) {
        const box = form.querySelector('[data-availability-for="' + input.id + '"]');

        if (box) {
            box.textContent = text;
            box.dataset.state = state;
        }
    }

    function checkAvailability(input) {
        const value = input.value.trim();
        input.setCustomValidity("");

        if (value === "") {
            setAvailability(input, "", "");
            return Promise.resolve();
        }

        /* the browser's own rules first (pattern, length, e-mail format) */
        if (!input.checkValidity()) {
            if (input.dataset.invalidMessage) {
                input.setCustomValidity(input.dataset.invalidMessage);
                setAvailability(input, input.dataset.invalidMessage, "error");
            } else {
                setAvailability(input, "", "");
            }
            return Promise.resolve();
        }

        setAvailability(input, form.dataset.checkingMessage || "", "checking");

        const url = "check_available.php?field=" + encodeURIComponent(input.dataset.checkField)
            + "&value=" + encodeURIComponent(value);

        const check = fetch(url, { credentials: "same-origin" })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                if (input.value.trim() !== value) {
                    return; /* the user kept typing; a newer check follows */
                }
                if (answer.taken) {
                    input.setCustomValidity(input.dataset.takenMessage);
                    setAvailability(input, input.dataset.takenMessage, "error");
                } else if (answer.valid) {
                    setAvailability(input, input.dataset.freeMessage || "", "ok");
                }
            })
            .catch(function () {
                /* no answer: register.php checks again on submit */
                setAvailability(input, "", "");
            });

        pendingChecks.set(input.id, check);
        return check;
    }

    form.querySelectorAll("[data-check-field]").forEach(function (input) {
        let timer = null;

        input.addEventListener("input", function () {
            clearTimeout(timer);
            input.setCustomValidity("");
            setAvailability(input, "", "");
            timer = setTimeout(function () { checkAvailability(input); }, 400);
        });

        input.addEventListener("blur", function () {
            clearTimeout(timer);
            checkAvailability(input);
        });

        /* values kept after a server error are checked at once */
        if (input.value.trim() !== "") {
            checkAvailability(input);
        }
    });

    /* "Continue": all fields of step 1 must be valid */
    form.querySelector("[data-next-step]").addEventListener("click", async function () {
        passwordsMatch();

        /* wait for checks still running (e.g. the user clicked right after typing) */
        await Promise.all(Array.from(pendingChecks.values()));

        const fields = form.querySelectorAll('.auth-step[data-step="1"] input');

        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return;
            }
        }

        showStep(2);
    });

    form.querySelector("[data-previous-step]").addEventListener("click", function () {
        showStep(1);
    });

    /* Enter in step 1 goes to step 2 instead of submitting */
    form.querySelector('.auth-step[data-step="1"]').addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
            event.preventDefault();
            form.querySelector("[data-next-step]").click();
        }
    });

    /* A field of a hidden step is invalid on submit: open that step */
    form.addEventListener("invalid", function (event) {
        const step = event.target.closest(".auth-step");

        if (step && step.hidden) {
            showStep(Number(step.dataset.step));
        }
    }, true);

    steps.forEach(function (step) {
        step.hidden = step.dataset.step !== "1";
    });
})();
