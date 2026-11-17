<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - COMMUNITY EVENTS AND NOTIFICATIONS
|--------------------------------------------------------------------------
| One place that creates the notifications, so the same rule applies
| wherever something happens (invitations, leaving, chat, admin).
|
| Who gets what (see also notifications.php):
|   invitation_accepted           members who already accepted
|   invitation_rejected           everyone else in the proposal
|                                 (accepted AND still pending)
|   community_activated           all 5 members
|   community_cancelled_conflict  the others in a pending proposal that was
|                                 cancelled because one member joined
|                                 another community
|   member_left                   the 4 members who stayed
|   chat_message                  the other members; ONE unread notification
|                                 per community until the chat is opened
|   contact_answered              the user who sent the contact message
|   report_reviewed / dismissed   the user who made the report (admin_reports.php)
|
| A notification must never stop the action itself: if it cannot be
| saved (e.g. migration_notifications.sql not run yet) the error is
| written to the PHP log and the action continues.
|--------------------------------------------------------------------------
*/

/*
 * Sends $type to the members of $communityId whose invitation status is in
 * $statuses. The actor is skipped unless $includeActor is true.
 */
function emNotifyCommunity(
    mysqli $conn,
    int $communityId,
    int $actorUserId,
    string $type,
    array $statuses = ["accepted"],
    bool $includeActor = false
): void {
    $allowed = ["pending", "accepted", "rejected", "left"];
    $statuses = array_values(array_intersect($statuses, $allowed));

    if (!$statuses) {
        return;
    }

    $statusList = "'" . implode("','", $statuses) . "'";
    $include = $includeActor ? 1 : 0;

    try {
        $statement = $conn->prepare(
            "INSERT INTO community_notifications
                 (community_id, recipient_user_id, actor_user_id, notification_type, is_read)
             SELECT cm.community_id, cm.user_id, ?, ?, 0
             FROM community_members cm
             WHERE cm.community_id = ?
               AND cm.invitation_status IN ($statusList)
               AND (? = 1 OR cm.user_id <> ?)"
        );
        $statement->bind_param("isiii", $actorUserId, $type, $communityId, $include, $actorUserId);
        $statement->execute();
        $statement->close();
    } catch (Throwable $error) {
        error_log("EnergyMatch notification '" . $type . "' not saved: " . $error->getMessage());
    }
}

/*
 * Call right after a community was set to 'active', inside the same
 * transaction. $actorUserId = the member whose action activated it.
 *  1. all 5 members: "your community is active"
 *  2. every other pending proposal that contains one of these 5 members can
 *     never become active, so it is cancelled and its other members are told
 *     why (instead of waiting for ever)
 */
function emOnCommunityActivated(mysqli $conn, int $communityId, int $actorUserId): void
{
    emNotifyCommunity($conn, $communityId, $actorUserId, "community_activated", ["accepted"], true);

    try {
        $overlap = $conn->prepare(
            "SELECT other.community_id, MIN(other.user_id) AS shared_user_id
             FROM community_members other
             INNER JOIN communities other_community
                 ON other_community.id = other.community_id
                AND other_community.status = 'pending'
             INNER JOIN community_members active_member
                 ON active_member.user_id = other.user_id
                AND active_member.community_id = ?
                AND active_member.invitation_status = 'accepted'
             WHERE other.community_id <> ?
               AND other.invitation_status IN ('pending', 'accepted')
             GROUP BY other.community_id
             ORDER BY other.community_id"
        );
        $overlap->bind_param("ii", $communityId, $communityId);
        $overlap->execute();
        $rows = $overlap->get_result()->fetch_all(MYSQLI_ASSOC);
        $overlap->close();

        foreach ($rows as $row) {
            $otherId = (int) $row["community_id"];
            $sharedUserId = (int) $row["shared_user_id"];

            $cancel = $conn->prepare("UPDATE communities SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $cancel->bind_param("i", $otherId);
            $cancel->execute();
            $changed = $cancel->affected_rows;
            $cancel->close();

            if ($changed > 0) {
                /*
                 * Everyone in that proposal who is NOT in the community that
                 * just became active (those got "community activated").
                 */
                $notify = $conn->prepare(
                    "INSERT INTO community_notifications
                         (community_id, recipient_user_id, actor_user_id, notification_type, is_read)
                     SELECT cm.community_id, cm.user_id, ?, 'community_cancelled_conflict', 0
                     FROM community_members cm
                     WHERE cm.community_id = ?
                       AND cm.invitation_status IN ('pending', 'accepted')
                       AND cm.user_id NOT IN (
                             SELECT active_member.user_id
                             FROM community_members active_member
                             WHERE active_member.community_id = ?
                               AND active_member.invitation_status = 'accepted'
                       )"
                );
                $notify->bind_param("iii", $sharedUserId, $otherId, $communityId);
                $notify->execute();
                $notify->close();
            }
        }
    } catch (Throwable $error) {
        error_log("EnergyMatch: overlapping proposals not cancelled: " . $error->getMessage());
    }
}

/* New chat message: one unread notification per member and community */
function emNotifyChatMessage(mysqli $conn, int $communityId, int $senderUserId): void
{
    try {
        $statement = $conn->prepare(
            "INSERT INTO community_notifications
                 (community_id, recipient_user_id, actor_user_id, notification_type, is_read)
             SELECT cm.community_id, cm.user_id, ?, 'chat_message', 0
             FROM community_members cm
             WHERE cm.community_id = ?
               AND cm.invitation_status = 'accepted'
               AND cm.user_id <> ?
               AND NOT EXISTS (
                     SELECT 1 FROM community_notifications existing
                     WHERE existing.recipient_user_id = cm.user_id
                       AND existing.community_id = cm.community_id
                       AND existing.notification_type = 'chat_message'
                       AND existing.is_read = 0
               )"
        );
        $statement->bind_param("iii", $senderUserId, $communityId, $senderUserId);
        $statement->execute();
        $statement->close();
    } catch (Throwable $error) {
        error_log("EnergyMatch chat notification not saved: " . $error->getMessage());
    }
}

/* The member opened the chat: its chat notifications are read */
function emMarkChatRead(mysqli $conn, int $communityId, int $userId): void
{
    try {
        $statement = $conn->prepare(
            "UPDATE community_notifications SET is_read = 1
             WHERE recipient_user_id = ? AND community_id = ?
               AND notification_type = 'chat_message' AND is_read = 0"
        );
        $statement->bind_param("ii", $userId, $communityId);
        $statement->execute();
        $statement->close();
    } catch (Throwable $error) {
        error_log("EnergyMatch chat notifications not marked read: " . $error->getMessage());
    }
}

/*
 * The admin answered a contact message. The sender is found by the saved
 * user id, or else by the e-mail of an account. Visitors without an
 * account get nothing (they have no notifications page).
 */
function emNotifyContactAnswered(mysqli $conn, int $contactMessageId, int $adminUserId): void
{
    try {
        $statement = $conn->prepare(
            "INSERT INTO community_notifications
                 (community_id, contact_message_id, recipient_user_id, actor_user_id, notification_type, is_read)
             SELECT NULL, m.id, u.id, ?, 'contact_answered', 0
             FROM contact_messages m
             INNER JOIN users u
                 ON u.id = m.user_id
                 OR (m.user_id IS NULL AND u.email = m.email)
             WHERE m.id = ?
             LIMIT 1"
        );
        $statement->bind_param("ii", $adminUserId, $contactMessageId);
        $statement->execute();
        $statement->close();
    } catch (Throwable $error) {
        error_log("EnergyMatch contact notification not saved: " . $error->getMessage());
    }
}

/* Used after leaving a community and after deleting an account (moved here from leave_community.php) */
/*
 * Re-checks fully accepted pending communities after an active community
 * is cancelled. A community becomes active only when all 5 members have
 * accepted and none of them belongs to another active community.
 *
 * Candidate communities are processed by ID and their users are locked in
 * ascending order. This keeps activation deterministic and prevents two
 * overlapping communities from being activated at the same time.
 */
function activateEligiblePendingCommunities(mysqli $conn): void
{
    $candidateResult = $conn->query(
        "
        SELECT c.id
        FROM communities c
        INNER JOIN community_members cm
            ON cm.community_id = c.id
        WHERE c.status = 'pending'
        GROUP BY c.id
        HAVING COUNT(*) = 5
           AND SUM(cm.invitation_status = 'accepted') = 5
        ORDER BY c.id ASC
        "
    );
    if (!$candidateResult) {
        throw new Exception($conn->error);
    }
    $candidateIds = [];
    while ($row = $candidateResult->fetch_assoc()) {
        $candidateIds[] = (int) $row["id"];
    }
    $candidateResult->free();
    foreach ($candidateIds as $candidateId) {
        $communityLockStmt = $conn->prepare(
            "SELECT status FROM communities WHERE id = ? FOR UPDATE"
        );
        if (!$communityLockStmt) {
            throw new Exception($conn->error);
        }
        $communityLockStmt->bind_param("i", $candidateId);
        $communityLockStmt->execute();
        $communityLockResult = $communityLockStmt->get_result();
        $candidateCommunity = $communityLockResult->fetch_assoc();
        $communityLockStmt->close();
        if (!$candidateCommunity || $candidateCommunity["status"] !== "pending") {
            continue;
        }

        // Lock the five users in a stable order. If another transaction is
        // trying to activate an overlapping community, it must wait here.
        $userLockStmt = $conn->prepare(
            "
            SELECT u.id
            FROM users u
            INNER JOIN community_members cm
                ON cm.user_id = u.id
            WHERE cm.community_id = ?
              AND cm.invitation_status = 'accepted'
            ORDER BY u.id ASC
            FOR UPDATE
            "
        );
        if (!$userLockStmt) {
            throw new Exception($conn->error);
        }
        $userLockStmt->bind_param("i", $candidateId);
        $userLockStmt->execute();
        $userLockResult = $userLockStmt->get_result();
        $lockedUserCount = $userLockResult->num_rows;
        $userLockStmt->close();
        if ($lockedUserCount !== 5) {
            continue;
        }

        // Re-check the exact 5/5 state after the locks were acquired.
        $stateStmt = $conn->prepare(
            "
            SELECT
                COUNT(*) AS total_members,
                SUM(invitation_status = 'accepted') AS accepted_members
            FROM community_members
            WHERE community_id = ?
            "
        );
        if (!$stateStmt) {
            throw new Exception($conn->error);
        }
        $stateStmt->bind_param("i", $candidateId);
        $stateStmt->execute();
        $stateResult = $stateStmt->get_result();
        $state = $stateResult->fetch_assoc();
        $stateStmt->close();
        if (
            (int) ($state["total_members"] ?? 0) !== 5 ||
            (int) ($state["accepted_members"] ?? 0) !== 5
        ) {
            continue;
        }
        $conflictStmt = $conn->prepare(
            "
            SELECT 1
            FROM community_members target
            INNER JOIN community_members other
                ON other.user_id = target.user_id
               AND other.community_id <> target.community_id
               AND other.invitation_status = 'accepted'
            INNER JOIN communities other_community
                ON other_community.id = other.community_id
               AND other_community.status = 'active'
            WHERE target.community_id = ?
              AND target.invitation_status = 'accepted'
            LIMIT 1
            "
        );
        if (!$conflictStmt) {
            throw new Exception($conn->error);
        }
        $conflictStmt->bind_param("i", $candidateId);
        $conflictStmt->execute();
        $conflictResult = $conflictStmt->get_result();
        $hasConflict = $conflictResult->num_rows > 0;
        $conflictStmt->close();
        if ($hasConflict) {
            continue;
        }
        $activateStmt = $conn->prepare(
            "
            UPDATE communities
            SET status = 'active'
            WHERE id = ?
              AND status = 'pending'
            "
        );
        if (!$activateStmt) {
            throw new Exception($conn->error);
        }
        $activateStmt->bind_param("i", $candidateId);
        if (!$activateStmt->execute()) {
            $error = $activateStmt->error;
            $activateStmt->close();
            throw new Exception($error);
        }
        $becameActive = $activateStmt->affected_rows > 0;
        $activateStmt->close();

        // This waiting proposal is now active: notify its 5 members and
        // cancel other pending proposals that share a member with it.
        if ($becameActive) {
            $creatorStmt = $conn->prepare("SELECT created_by FROM communities WHERE id = ?");
            $creatorStmt->bind_param("i", $candidateId);
            $creatorStmt->execute();
            $creatorRow = $creatorStmt->get_result()->fetch_assoc();
            $creatorStmt->close();
            emOnCommunityActivated($conn, $candidateId, (int) ($creatorRow["created_by"] ?? 0));
        }
    }
}
