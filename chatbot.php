<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH ASSISTANT (floating chatbot, signed-in users only)
|--------------------------------------------------------------------------
| The FAQ explains the platform in general; the assistant answers about
| the signed-in user's OWN situation (chatbot_state.php) and gives buttons
| that go straight to the right page. Answers and texts: js/chatbot.js.
*/

if (!isset($_SESSION["user_id"])) {
    return;
}

require_once __DIR__ . "/chatbot_state.php";

$chatbotState = null;
try {
    if (!(isset($conn) && $conn instanceof mysqli)) {
        include __DIR__ . "/db.php";
    }
    $chatbotState = emChatbotState($conn, (int) $_SESSION["user_id"]);
} catch (Throwable $error) {
    /* without the user's data the assistant still answers the general questions */
    $chatbotState = null;
}

$chatbotGreek = ($_SESSION["language"] ?? "en") === "gr";
?>
<div class="chatbot-wrapper">

    <button
        type="button"
        class="chatbot-toggle"
        id="chatbotToggle"
        aria-label="<?php echo $chatbotGreek ? "Άνοιγμα βοηθού EnergyMatch" : "Open the EnergyMatch assistant"; ?>"
    >
        <span class="chatbot-toggle-icon"><i data-lucide="bot-message-square"></i></span>
    </button>

    <section class="chatbot-box" id="chatbotBox" aria-hidden="true">

        <div class="chatbot-header">
            <div class="chatbot-header-info">
                <div class="chatbot-avatar"><i data-lucide="sun"></i></div>

                <div>
                    <h3><?php echo $chatbotGreek ? "Βοηθός EnergyMatch" : "EnergyMatch Assistant"; ?></h3>
                    <p>
                        <span class="chatbot-status-dot"></span>
                        <?php echo $chatbotGreek ? "Ενεργός τώρα" : "Active now"; ?>
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="chatbot-close"
                id="chatbotClose"
                aria-label="<?php echo $chatbotGreek ? "Κλείσιμο" : "Close"; ?>"
            >
                <i data-lucide="x"></i>
            </button>
        </div>

        <!-- filled by js/chatbot.js: greeting + suggestions for this user -->
        <div class="chatbot-messages" id="chatbotMessages"></div>

        <form class="chatbot-form" id="chatbotForm">

            <input
                type="text"
                id="chatbotInput"
                placeholder="<?php echo $chatbotGreek ? "Γράψε την ερώτησή σου..." : "Type your question..."; ?>"
                autocomplete="off"
                maxlength="300"
            >

            <button
                type="submit"
                class="chatbot-send"
                aria-label="<?php echo $chatbotGreek ? "Αποστολή" : "Send"; ?>"
            >
                <i data-lucide="send-horizontal"></i>
            </button>

        </form>

    </section>

</div>

<script>
window.EM_CHATBOT = <?php echo json_encode([
    "lang" => $chatbotGreek ? "gr" : "en",
    "state" => $chatbotState,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
