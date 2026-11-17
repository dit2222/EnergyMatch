<?php
session_start();
require_once __DIR__ . "/remember_me.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();
require_once "db.php";
require_once __DIR__ . "/community_events.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$communityId = (int) ($_POST["community_id"] ?? 0);
$action = $_POST["action"] ?? "";

if (
    $communityId <= 0 ||
    !in_array($action, ["accept", "reject"], true)
) {
    header("Location: notifications.php");
    exit;
}

/*
 * Δημιουργεί ειδοποίηση μόνο για τα άλλα μέλη που έχουν
 * ήδη αποδεχτεί (accepted) τη συγκεκριμένη κοινότητα.
 */
function createCommunityNotifications(
    mysqli $conn,
    int $communityId,
    int $actorUserId,
    string $notificationType
): void {

    $notificationStatement = $conn->prepare(
        "
        INSERT INTO community_notifications (
            community_id,
            recipient_user_id,
            actor_user_id,
            notification_type,
            is_read
        )
        SELECT
            cm.community_id,
            cm.user_id,
            ?,
            ?,
            0
        FROM community_members cm
        WHERE cm.community_id = ?
          AND cm.user_id <> ?
          AND cm.invitation_status = 'accepted'
        "
    );

    if (!$notificationStatement) {
        throw new Exception($conn->error);
    }

    $notificationStatement->bind_param(
        "isii",
        $actorUserId,
        $notificationType,
        $communityId,
        $actorUserId
    );

    if (!$notificationStatement->execute()) {
        $error = $notificationStatement->error;
        $notificationStatement->close();
        throw new Exception($error);
    }

    $notificationStatement->close();
}

$conn->begin_transaction();

try {

    // =================================================
    // ΕΛΕΓΧΟΣ ΟΤΙ Η ΠΡΟΣΚΛΗΣΗ ΑΝΗΚΕΙ
    // ΣΤΟΝ ΣΥΝΔΕΔΕΜΕΝΟ ΧΡΗΣΤΗ
    // =================================================

    $checkStatement = $conn->prepare(
        "
        SELECT cm.id
        FROM community_members cm
        INNER JOIN communities c
            ON c.id = cm.community_id
        WHERE cm.community_id = ?
          AND cm.user_id = ?
          AND cm.invitation_status = 'pending'
          AND c.status = 'pending'
        LIMIT 1
        "
    );

    if (!$checkStatement) {
        throw new Exception($conn->error);
    }

    $checkStatement->bind_param(
        "ii",
        $communityId,
        $userId
    );

    $checkStatement->execute();
    $checkResult = $checkStatement->get_result();

    if ($checkResult->num_rows === 0) {
        $checkStatement->close();
        throw new Exception("Invitation not found.");
    }

    $checkStatement->close();

    // =================================================
    // ACCEPT
    // =================================================

    if ($action === "accept") {

        // =================================================
        // Ο ΧΡΗΣΤΗΣ ΜΠΟΡΕΙ ΝΑ ΑΝΗΚΕΙ ΜΟΝΟ
        // ΣΕ ΜΙΑ ΕΝΕΡΓΗ ΚΟΙΝΟΤΗΤΑ
        // =================================================
        //
        // Η νέα πρόσκληση παραμένει pending όσο ο χρήστης
        // είναι ήδη accepted μέλος άλλης active κοινότητας.
        // Reject εξακολουθεί να επιτρέπεται κανονικά.

        $activeCommunityStatement = $conn->prepare(
            "
            SELECT c.id
            FROM community_members cm
            INNER JOIN communities c
                ON c.id = cm.community_id
            WHERE cm.user_id = ?
              AND cm.invitation_status = 'accepted'
              AND c.status = 'active'
              AND c.id <> ?
            LIMIT 1
            "
        );

        if (!$activeCommunityStatement) {
            throw new Exception($conn->error);
        }

        $activeCommunityStatement->bind_param(
            "ii",
            $userId,
            $communityId
        );

        $activeCommunityStatement->execute();
        $activeCommunityResult =
            $activeCommunityStatement->get_result();

        if ($activeCommunityResult->num_rows > 0) {

            $activeCommunityRow =
                $activeCommunityResult->fetch_assoc();

            $activeCommunityId =
                (int) $activeCommunityRow["id"];

            $activeCommunityStatement->close();

            // Δεν αλλάζουμε την πρόσκληση.
            // Παραμένει pending.
            $conn->rollback();

            header(
                "Location: notifications.php?warning=active_community"
                . "&active_community_id="
                . $activeCommunityId
            );
            exit;
        }

        $activeCommunityStatement->close();

        $status = "accepted";

        $statement = $conn->prepare(
            "
            UPDATE community_members
            SET
                invitation_status = ?,
                responded_at = NOW()
            WHERE community_id = ?
              AND user_id = ?
              AND invitation_status = 'pending'
            "
        );

        if (!$statement) {
            throw new Exception($conn->error);
        }

        $statement->bind_param(
            "sii",
            $status,
            $communityId,
            $userId
        );

        if (!$statement->execute()) {
            throw new Exception($statement->error);
        }

        $statement->close();

        // Ειδοποιούμε τα υπόλοιπα μέλη.
        createCommunityNotifications(
            $conn,
            $communityId,
            $userId,
            "invitation_accepted"
        );

        // =============================================
        // ΕΛΕΓΧΟΥΜΕ ΑΝ ΥΠΑΡΧΕΙ ΑΛΛΟ PENDING ΜΕΛΟΣ
        // =============================================

        $pendingStatement = $conn->prepare(
            "
            SELECT COUNT(*) AS pending_count
            FROM community_members
            WHERE community_id = ?
              AND invitation_status = 'pending'
            "
        );

        if (!$pendingStatement) {
            throw new Exception($conn->error);
        }

        $pendingStatement->bind_param(
            "i",
            $communityId
        );

        $pendingStatement->execute();
        $pendingResult = $pendingStatement->get_result();
        $pendingRow = $pendingResult->fetch_assoc();
        $pendingCount = (int) $pendingRow["pending_count"];
        $pendingStatement->close();

        // =============================================
        // ΑΝ ΟΛΟΙ ACCEPT → ACTIVE
        // =============================================

        if ($pendingCount === 0) {

            // Τελικός έλεγχος ασφαλείας για ΟΛΑ τα μέλη.
            // Η κοινότητα δεν γίνεται active αν έστω ένα από
            // τα accepted μέλη ανήκει ήδη σε άλλη active κοινότητα.
            $conflictStatement = $conn->prepare(
                "
                SELECT cm_current.user_id
                FROM community_members cm_current
                INNER JOIN community_members cm_other
                    ON cm_other.user_id = cm_current.user_id
                   AND cm_other.community_id <> cm_current.community_id
                   AND cm_other.invitation_status = 'accepted'
                INNER JOIN communities c_other
                    ON c_other.id = cm_other.community_id
                   AND c_other.status = 'active'
                WHERE cm_current.community_id = ?
                  AND cm_current.invitation_status = 'accepted'
                LIMIT 1
                "
            );

            if (!$conflictStatement) {
                throw new Exception($conn->error);
            }

            $conflictStatement->bind_param(
                "i",
                $communityId
            );

            $conflictStatement->execute();
            $conflictResult =
                $conflictStatement->get_result();

            $hasActiveCommunityConflict =
                $conflictResult->num_rows > 0;

            $conflictStatement->close();

            if (!$hasActiveCommunityConflict) {

                $activeStatus = "active";

                $communityStatement = $conn->prepare(
                    "
                    UPDATE communities
                    SET status = ?
                    WHERE id = ?
                      AND status = 'pending'
                    "
                );

                if (!$communityStatement) {
                    throw new Exception($conn->error);
                }

                $communityStatement->bind_param(
                    "si",
                    $activeStatus,
                    $communityId
                );

                if (!$communityStatement->execute()) {
                    throw new Exception($communityStatement->error);
                }

                $becameActive = $communityStatement->affected_rows > 0;
                $communityStatement->close();

                // Ενεργοποιήθηκε: ειδοποίηση στους 5 και ακύρωση των
                // άλλων προτάσεων σε αναμονή που έχουν κοινά μέλη.
                if ($becameActive) {
                    emOnCommunityActivated($conn, $communityId, $userId);
                }
            }
        }

        $conn->commit();

        header(
            "Location: notifications.php?success=accepted"
        );
        exit;
    }

    // =================================================
    // REJECT
    // =================================================

    if ($action === "reject") {

        $status = "rejected";

        $statement = $conn->prepare(
            "
            UPDATE community_members
            SET
                invitation_status = ?,
                responded_at = NOW()
            WHERE community_id = ?
              AND user_id = ?
              AND invitation_status = 'pending'
            "
        );

        if (!$statement) {
            throw new Exception($conn->error);
        }

        $statement->bind_param(
            "sii",
            $status,
            $communityId,
            $userId
        );

        if (!$statement->execute()) {
            throw new Exception($statement->error);
        }

        $statement->close();

        // Ειδοποιούμε ΟΛΑ τα υπόλοιπα μέλη, και όσα δεν είχαν απαντήσει
        // ακόμη, ώστε η πρόσκλησή τους να μην εξαφανιστεί χωρίς εξήγηση.
        emNotifyCommunity(
            $conn,
            $communityId,
            $userId,
            "invitation_rejected",
            ["accepted", "pending"]
        );

        // Αν ένα μέλος απορρίψει,
        // η προτεινόμενη κοινότητα απορρίπτεται.
        $communityStatus = "rejected";

        $communityStatement = $conn->prepare(
            "
            UPDATE communities
            SET status = ?
            WHERE id = ?
              AND status = 'pending'
            "
        );

        if (!$communityStatement) {
            throw new Exception($conn->error);
        }

        $communityStatement->bind_param(
            "si",
            $communityStatus,
            $communityId
        );

        if (!$communityStatement->execute()) {
            throw new Exception($communityStatement->error);
        }

        $communityStatement->close();

        $conn->commit();

        header(
            "Location: notifications.php?success=rejected"
        );
        exit;
    }

} catch (Throwable $error) {

    $conn->rollback();

    die(
        "EnergyMatch invitation error: " .
        htmlspecialchars($error->getMessage())
    );
}
?>