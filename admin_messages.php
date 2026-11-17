<?php
session_start();
require_once __DIR__ . "/remember_me.php";
require_once __DIR__ . "/admin_i18n.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();
require_once "db.php";
require_once __DIR__ . "/community_events.php";

/* =========================================
   ADMIN ACCESS
========================================= */

/* =========================================
   ADMIN ACCESS
========================================= */

/* Δεν είναι συνδεδεμένος */
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit();
}

/* Είναι συνδεδεμένος αλλά δεν είναι Admin */
if (($_SESSION["role"] ?? "user") !== "admin") {
    header("Location: dashboard.php");
    exit();
}

/* =========================================
   SAFE OUTPUT
========================================= */

function adminSafe($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================
   HANDLE REPLY (the user sees it in their notifications)
========================================= */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["message_id"], $_POST["reply_text"])
) {
    $messageId = (int) $_POST["message_id"];
    $replyText = trim((string) $_POST["reply_text"]);
    $replyText = mb_substr($replyText, 0, 2000, "UTF-8");

    if ($messageId > 0 && $replyText !== "") {
        try {
            $replyStmt = $conn->prepare(
                "UPDATE contact_messages
                 SET admin_reply = ?, replied_at = NOW(), status = 'answered'
                 WHERE id = ?"
            );
            $replyStmt->bind_param("si", $replyText, $messageId);
            $replyStmt->execute();
            $replyStmt->close();

            emNotifyContactAnswered($conn, $messageId, (int) $_SESSION["user_id"]);
        } catch (Throwable $error) {
            error_log("Admin reply not saved: " . $error->getMessage());
        }
    }

    header("Location: admin_messages.php?updated=1");
    exit();
}

/* =========================================
   HANDLE STATUS CHANGE
========================================= */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["message_id"], $_POST["new_status"])
) {
    $messageId = (int)$_POST["message_id"];
    $newStatus = trim($_POST["new_status"]);
    $allowedStatuses = [
        "new",
        "read",
        "answered"
    ];
    if (
        $messageId > 0 &&
        in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {
        /* previous status: the user is notified only when it BECOMES answered */
        $previousStatus = "";
        $previousStmt = $conn->prepare("SELECT status FROM contact_messages WHERE id = ?");
        if ($previousStmt) {
            $previousStmt->bind_param("i", $messageId);
            $previousStmt->execute();
            $previousStatus = (string) ($previousStmt->get_result()->fetch_row()[0] ?? "");
            $previousStmt->close();
        }

        $updateSql = "
            UPDATE contact_messages
            SET status = ?
            WHERE id = ?
        ";
        $updateStmt =
            $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param(
                "si",
                $newStatus,
                $messageId
            );
            $updateStmt->execute();
            $updateStmt->close();

            if ($newStatus === "answered" && $previousStatus !== "answered") {
                emNotifyContactAnswered($conn, $messageId, (int) $_SESSION["user_id"]);
            }
        }
    }

    /*
       Redirect ώστε να μη γίνει ξανά
       POST αν ο Admin κάνει refresh.
    */
    $redirect =
        "admin_messages.php?updated=1";
    if (!empty($_POST["current_status"])) {
        $currentStatus =
            trim($_POST["current_status"]);
        if (
            in_array(
                $currentStatus,
                $allowedStatuses,
                true
            )
        ) {
            $redirect .=
                "&status=" .
                urlencode($currentStatus);
        }
    }
    header("Location: " . $redirect);
    exit();
}

/* =========================================
   FILTER
========================================= */
$status = trim(
    $_GET["status"] ?? ""
);
$allowedFilters = [
    "",
    "new",
    "read",
    "answered"
];
if (
    !in_array(
        $status,
        $allowedFilters,
        true
    )
) {
    $status = "";
}

/* =========================================
   COUNTS
========================================= */
$counts = [
    "all" => 0,
    "new" => 0,
    "read" => 0,
    "answered" => 0
];
$countSql = "
    SELECT
        COUNT(*) AS total,
        SUM(
            CASE
                WHEN status = 'new'
                THEN 1
                ELSE 0
            END
        ) AS new_count,
        SUM(
            CASE
                WHEN status = 'read'
                THEN 1
                ELSE 0
            END
        ) AS read_count,
        SUM(
            CASE
                WHEN status = 'answered'
                THEN 1
                ELSE 0
            END
        ) AS answered_count
    FROM contact_messages
";
$countResult =
    $conn->query($countSql);
if ($countResult) {
    $countRow =
        $countResult->fetch_assoc();
    $counts["all"] =
        (int)($countRow["total"] ?? 0);
    $counts["new"] =
        (int)($countRow["new_count"] ?? 0);
    $counts["read"] =
        (int)($countRow["read_count"] ?? 0);
    $counts["answered"] =
        (int)($countRow["answered_count"] ?? 0);
}

/* =========================================
   LOAD MESSAGES
========================================= */
$messages = [];
$pageError = "";
try {
    if ($status !== "") {
        $sql = "
            SELECT
                *
            FROM contact_messages
            WHERE status = ?
            ORDER BY
                created_at DESC,
                id DESC
        ";
        $stmt =
            $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception(
                $conn->error
            );
        }
        $stmt->bind_param(
            "s",
            $status
        );
    } else {
        $sql = "
            SELECT
                *
            FROM contact_messages
            ORDER BY
                created_at DESC,
                id DESC
        ";
        $stmt =
            $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception(
                $conn->error
            );
        }
    }
    if (!$stmt->execute()) {
        throw new Exception(
            $stmt->error
        );
    }
    $result =
        $stmt->get_result();
    while (
        $row =
        $result->fetch_assoc()
    ) {
        $messages[] = $row;
    }
    $stmt->close();
} catch (Throwable $e) {
    error_log(
        "Admin messages error: " .
        $e->getMessage()
    );
    $pageError =
        adminT("Could not load the messages.", "Δεν ήταν δυνατή η φόρτωση των μηνυμάτων.");
}
?>
<!DOCTYPE html>
<html lang="<?php echo adminLangCode(); ?>">
<head>
    <?php include "head_assets.php"; ?>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        <?php echo adminT("EnergyMatch | Contact messages", "EnergyMatch | Μηνύματα επικοινωνίας"); ?>
    </title>
    <link
        rel="stylesheet"
        href="css/admin_messages.css?v=<?php echo ASSET_VERSION; ?>"
    >
</head>
<body>

<!-- =========================================
     NAVBAR
========================================= -->

<?php include "admin_navbar.php"; ?>

<!-- =========================================
     MAIN
========================================= -->

<main class="container">

    <!-- HEADER -->
    <section class="page-header">
        <div>
            <p class="page-label">
                <?php echo adminT("ADMINISTRATION", "ΔΙΑΧΕΙΡΙΣΗ"); ?>
            </p>
            <h1>
                <?php echo adminT("Contact messages", "Μηνύματα επικοινωνίας"); ?>
            </h1>
            <p>
                <?php echo adminT("View and manage the messages sent through the contact form.", "Προβολή και διαχείριση των μηνυμάτων που αποστέλλονται μέσω της φόρμας επικοινωνίας."); ?>
            </p>
        </div>
        <div class="new-counter">
            <span>
                <?php echo adminT("New messages", "Νέα μηνύματα"); ?>
            </span>
            <strong>
                <?php echo $counts["new"]; ?>
            </strong>
        </div>
    </section>

    <?php if (
        isset($_GET["updated"]) &&
        $_GET["updated"] === "1"
    ): ?>
        <div class="success-box">
            <i data-lucide="check"></i> <?php echo adminT("The message status was updated.", "Η κατάσταση του μηνύματος ενημερώθηκε επιτυχώς."); ?>
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ""): ?>
        <div class="error-box">
            <?php
            echo adminSafe(
                $pageError
            );
            ?>
        </div>
    <?php endif; ?>

    <!-- =====================================
         FILTERS
    ====================================== -->

    <section class="status-tabs">
        <a
            href="admin_messages.php"
            class="<?php
                echo $status === ""
                    ? "active"
                    : "";
            ?>"
        >
            <?php echo adminT("All", "Όλα"); ?>
            <span>
                <?php
                echo $counts["all"];
                ?>
            </span>
        </a>
        <a
            href="admin_messages.php?status=new"
            class="<?php
                echo $status === "new"
                    ? "active"
                    : "";
            ?>"
        >
            <?php echo adminT("New", "Νέα"); ?>
            <span>
                <?php
                echo $counts["new"];
                ?>
            </span>
        </a>
        <a
            href="admin_messages.php?status=read"
            class="<?php
                echo $status === "read"
                    ? "active"
                    : "";
            ?>"
        >
            <?php echo adminT("Read", "Διαβασμένα"); ?>
            <span>
                <?php
                echo $counts["read"];
                ?>
            </span>
        </a>
        <a
            href="admin_messages.php?status=answered"
            class="<?php
                echo $status === "answered"
                    ? "active"
                    : "";
            ?>"
        >
            <?php echo adminT("Answered", "Απαντημένα"); ?>
            <span>
                <?php
                echo $counts["answered"];
                ?>
            </span>
        </a>
    </section>

    <!-- =====================================
         MESSAGES
    ====================================== -->

    <section class="messages-panel">
        <div class="panel-heading">
            <div>
                <h2>
                    <?php echo adminT("Contact messages", "Μηνύματα επικοινωνίας"); ?>
                </h2>
                <p>
                    <?php
                    if ($status === "new") {
                        echo adminT("New messages that have not been reviewed.", "Νέα μηνύματα που δεν έχουν εξεταστεί.");
                    } elseif ($status === "read") {
                        echo adminT("Messages that have been read.", "Μηνύματα που έχουν διαβαστεί.");
                    } elseif ($status === "answered") {
                        echo adminT("Messages that have been answered.", "Μηνύματα που έχουν αντιμετωπιστεί.");
                    } else {
                        echo adminT("All contact messages.", "Όλα τα μηνύματα επικοινωνίας.");
                    }
                    ?>
                </p>
            </div>
        </div>

        <?php if (
            empty($messages)
        ): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-lucide="mail"></i>
                </div>
                <h3>
                    <?php echo adminT("No messages", "Δεν υπάρχουν μηνύματα"); ?>
                </h3>
                <p>
                    <?php echo adminT("There are no messages in this category.", "Δεν υπάρχουν μηνύματα σε αυτή την κατηγορία."); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="messages-list">

                <?php foreach (
                    $messages as $message
                ): ?>
                    <?php
                    $messageStatus =
                        strtolower(
                            trim(
                                $message["status"]
                                ?? "new"
                            )
                        );
                    $createdAt = "—";
                    if (
                        !empty(
                            $message["created_at"]
                        )
                    ) {
                        $timestamp =
                            strtotime(
                                $message["created_at"]
                            );
                        if (
                            $timestamp !== false
                        ) {
                            $createdAt =
                                date(
                                    "d/m/Y H:i",
                                    $timestamp
                                );
                        }
                    }
                    ?>
                    <article
                        class="
                            message-card
                            <?php
                            echo $messageStatus === "new"
                                ? "unread-message"
                                : "";
                            ?>
                        "
                    >

                        <!-- TOP -->
                        <div class="message-top">
                            <div>
                                <span class="message-id">
                                    <?php echo adminT("Message", "Μήνυμα"); ?>
                                    #<?php
                                    echo (int)$message["id"];
                                    ?>
                                </span>
                                <h3>
                                    <?php
                                    echo adminSafe(
                                        $message["subject"]
                                    );
                                    ?>
                                </h3>
                            </div>

                            <?php if (
                                $messageStatus === "new"
                            ): ?>
                                <span
                                    class="
                                        status-badge
                                        status-new
                                    "
                                >
                                    <?php echo adminT("NEW", "ΝΕΟ"); ?>
                                </span>
                            <?php elseif (
                                $messageStatus === "read"
                            ): ?>
                                <span
                                    class="
                                        status-badge
                                        status-read
                                    "
                                >
                                    <?php echo adminT("READ", "ΔΙΑΒΑΣΜΕΝΟ"); ?>
                                </span>
                            <?php else: ?>
                                <span
                                    class="
                                        status-badge
                                        status-answered
                                    "
                                >
                                    <?php echo adminT("ANSWERED", "ΑΠΑΝΤΗΜΕΝΟ"); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- SENDER -->
                        <div class="sender-info">
                            <div>
                                <span>
                                    <?php echo adminT("SENDER", "ΑΠΟΣΤΟΛΕΑΣ"); ?>
                                </span>
                                <strong>
                                    <?php
                                    echo adminSafe(
                                        $message["full_name"]
                                    );
                                    ?>
                                </strong>
                            </div>
                            <div>
                                <span>
                                    EMAIL
                                </span>
                                <strong>
                                    <?php
                                    echo adminSafe(
                                        $message["email"]
                                    );
                                    ?>
                                </strong>
                            </div>
                            <div>
                                <span>
                                    <?php echo adminT("DATE", "ΗΜΕΡΟΜΗΝΙΑ"); ?>
                                </span>
                                <strong>
                                    <?php
                                    echo adminSafe(
                                        $createdAt
                                    );
                                    ?>
                                </strong>
                            </div>
                        </div>

                        <!-- MESSAGE -->
                        <div class="message-content">
                            <span>
                                <?php echo adminT("MESSAGE", "ΜΗΝΥΜΑ"); ?>
                            </span>
                            <p>
                                <?php
                                echo nl2br(
                                    adminSafe(
                                        $message["message"]
                                    )
                                );
                                ?>
                            </p>
                        </div>

                        <!-- REPLY -->
                        <?php if (!empty($message["admin_reply"])): ?>
                            <div class="message-reply">
                                <span>
                                    <?php echo adminT("YOUR REPLY", "Η ΑΠΑΝΤΗΣΗ ΣΟΥ"); ?>
                                    <?php if (!empty($message["replied_at"])): ?>
                                        · <?php echo date("d/m/Y H:i", strtotime($message["replied_at"])); ?>
                                    <?php endif; ?>
                                </span>
                                <p><?php echo nl2br(adminSafe($message["admin_reply"])); ?></p>
                            </div>
                        <?php elseif (array_key_exists("admin_reply", $message)): ?>
                            <form method="POST" action="admin_messages.php" class="message-reply-form">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="message_id" value="<?php echo (int) $message["id"]; ?>">
                                <label for="reply-<?php echo (int) $message["id"]; ?>">
                                    <?php echo adminT("Reply", "Απάντηση"); ?>
                                </label>
                                <textarea
                                    id="reply-<?php echo (int) $message["id"]; ?>"
                                    name="reply_text"
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="<?php echo adminT("Write a reply. A signed-in sender sees it in their notifications.", "Γράψε απάντηση. Αν ο αποστολέας έχει λογαριασμό, τη βλέπει στις ειδοποιήσεις του."); ?>"
                                    required
                                ></textarea>
                                <button type="submit" class="action-button answered-button">
                                    <i data-lucide="send"></i> <?php echo adminT("Send reply", "Αποστολή απάντησης"); ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- ACTIONS -->
                        <div class="message-actions">

                            <?php if (
                                $messageStatus === "new"
                            ): ?>

                                <form
                                    method="POST"
                                    action="admin_messages.php"
                                >
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="message_id"
                                        value="<?php
                                            echo (int)$message["id"];
                                        ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="read"
                                    >
                                    <input
                                        type="hidden"
                                        name="current_status"
                                        value="<?php
                                            echo adminSafe($status);
                                        ?>"
                                    >
                                    <button
                                        type="submit"
                                        class="action-button read-button"
                                    >
                                        <i data-lucide="check"></i> <?php echo adminT("Mark as read", "Σήμανση ως διαβασμένο"); ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (
                                $messageStatus !== "answered"
                            ): ?>

                                <form
                                    method="POST"
                                    action="admin_messages.php"
                                >
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="message_id"
                                        value="<?php
                                            echo (int)$message["id"];
                                        ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="answered"
                                    >
                                    <input
                                        type="hidden"
                                        name="current_status"
                                        value="<?php
                                            echo adminSafe($status);
                                        ?>"
                                    >
                                    <button
                                        type="submit"
                                        class="action-button answered-button"
                                    >
                                        <i data-lucide="check"></i> <?php echo adminT("Mark as answered", "Σήμανση ως απαντημένο"); ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (
                                $messageStatus !== "new"
                            ): ?>

                                <form
                                    method="POST"
                                    action="admin_messages.php"
                                >
                                    <?php echo csrfField(); ?>
                                    <input
                                        type="hidden"
                                        name="message_id"
                                        value="<?php
                                            echo (int)$message["id"];
                                        ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="new_status"
                                        value="new"
                                    >
                                    <input
                                        type="hidden"
                                        name="current_status"
                                        value="<?php
                                            echo adminSafe($status);
                                        ?>"
                                    >
                                    <button
                                        type="submit"
                                        class="action-button reset-button"
                                    >
                                        <?php echo adminT("Mark as new", "Επαναφορά σε νέο"); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="footer">
    <?php echo adminT("© EnergyMatch — Administration panel", "© EnergyMatch — Πίνακας διαχείρισης"); ?>
</footer>
</body>
</html>
