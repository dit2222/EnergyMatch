<?php
/*
|--------------------------------------------------------------------------
| ENERGYMATCH - WARNINGS FROM REPORTS
|--------------------------------------------------------------------------
| 1. A user reports a member (report_user.php). It does NOT count yet.
| 2. The admin approves it: "Mark as reviewed" (admin_reports.php).
| 3. Every approved report = one warning for the reported user, who gets
|    a notification "Warning x/5" with the reason.
| 4. The 5th approved report BLOCKS the account (users.banned_at):
|      the user can enter once more and sees only the farewell message
|      (farewell.php), then the account is deleted. If they never come
|      back it is deleted after EM_BANNED_PURGE_DAYS days.
|    What happens at once:
|      - every community of the user that is active or still pending is
|        cancelled (communities are fixed groups of 5),
|      - the other members are informed ("a member was removed"),
|      - the user and all their data are deleted.
| Reports from the same reporter about the same user count once, so one
| person alone cannot remove somebody.
|--------------------------------------------------------------------------
*/

const EM_WARNINGS_LIMIT = 5;
const EM_BANNED_PURGE_DAYS = 30;

/* Approved reports about a user, one per reporter */
function emApprovedReportCount(mysqli $conn, int $userId): int
{
    $statement = $conn->prepare(
        "SELECT COUNT(DISTINCT reporter_user_id)
         FROM user_reports
         WHERE reported_user_id = ? AND status = 'reviewed'"
    );
    $statement->bind_param("i", $userId);
    $statement->execute();
    $count = (int) $statement->get_result()->fetch_row()[0];
    $statement->close();

    return $count;
}

/* Approved reports for many users at once (admin lists) */
function emApprovedReportCounts(mysqli $conn): array
{
    $counts = [];
    $result = $conn->query(
        "SELECT reported_user_id, COUNT(DISTINCT reporter_user_id) AS total
         FROM user_reports
         WHERE status = 'reviewed'
         GROUP BY reported_user_id"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $counts[(int) $row["reported_user_id"]] = (int) $row["total"];
        }
        $result->free();
    }

    return $counts;
}

/*
 * Called by admin_reports.php right after a report became "reviewed".
 * Returns "warned", "deleted" or "none" (e.g. the same reporter again).
 */
function emOnReportApproved(mysqli $conn, int $reportId, int $adminUserId): string
{
    $statement = $conn->prepare(
        "SELECT reported_user_id, reporter_user_id, community_id
         FROM user_reports WHERE id = ?"
    );
    $statement->bind_param("i", $reportId);
    $statement->execute();
    $report = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$report) {
        return "none";
    }

    $reportedUserId = (int) $report["reported_user_id"];

    /* this reporter already had an approved report about this user: no new warning */
    $repeat = $conn->prepare(
        "SELECT COUNT(*) FROM user_reports
         WHERE reported_user_id = ? AND reporter_user_id = ? AND status = 'reviewed' AND id <> ?"
    );
    $reporterId = (int) $report["reporter_user_id"];
    $repeat->bind_param("iii", $reportedUserId, $reporterId, $reportId);
    $repeat->execute();
    $alreadyCounted = (int) $repeat->get_result()->fetch_row()[0] > 0;
    $repeat->close();

    if ($alreadyCounted) {
        return "none";
    }

    if (emApprovedReportCount($conn, $reportedUserId) >= EM_WARNINGS_LIMIT) {
        if (emBanReportedAccount($conn, $reportedUserId, $adminUserId)) {
            return "banned";
        }
        /* older database without users.banned_at: delete at once (as before) */
        emDeleteReportedAccount($conn, $reportedUserId, $adminUserId);
        return "deleted";
    }

    /* warning notification to the reported user (the reporter is not named) */
    try {
        $warning = $conn->prepare(
            "INSERT INTO community_notifications
                 (community_id, report_id, recipient_user_id, actor_user_id, notification_type, is_read)
             VALUES (?, ?, ?, ?, 'account_warning', 0)"
        );
        $communityId = (int) $report["community_id"];
        $warning->bind_param("iiii", $communityId, $reportId, $reportedUserId, $adminUserId);
        $warning->execute();
        $warning->close();
    } catch (Throwable $error) {
        error_log("EnergyMatch warning not saved: " . $error->getMessage());
    }

    return "warned";
}

/* After the 5th approved report: the same deletion as below, by the admin */
function emDeleteReportedAccount(mysqli $conn, int $userId, int $adminUserId): void
{
    emDeleteAccount($conn, $userId, "member_removed", $adminUserId);
}

/*
 * Deletes an account: by the admin (5 approved reports) or by the user
 * (Settings -> Delete account). It works like leaving a community:
 *   1. every community of the user that is active or still pending is
 *      cancelled (communities are fixed groups of 5),
 *   2. its other members are informed ($notificationType):
 *        member_removed          removed by the admin after reports
 *        member_account_deleted  the member deleted their own account
 *      The notification names the community, not the member.
 *   3. the community row stays, so the others still see it in their history
 *      (a community created by the user passes to another member),
 *   4. the user and everything that belongs to them are deleted,
 *   5. pending communities that were waiting only for these members to be
 *      free are activated (same as after leaving).
 * $actorUserId: the admin; null when the user deletes their own account
 * (then each notification names its own recipient as actor, because the
 * deleted user's notifications are removed with the account).
 */
function emDeleteAccount(mysqli $conn, int $userId, string $notificationType, ?int $actorUserId): void
{
    require_once __DIR__ . "/community_events.php";

    $conn->begin_transaction();

    try {
        emCancelCommunitiesOfUser($conn, $userId, $notificationType, $actorUserId);

        /*
         * Communities CREATED by the user keep their row, so the other
         * members still see them in their history. The creator passes to
         * another member of that community (the admin if there is none);
         * otherwise the foreign key would delete the community.
         */
        $handOver = $conn->prepare(
            "UPDATE communities c
             SET c.created_by = COALESCE(
                 (SELECT MIN(other.user_id) FROM community_members other
                  WHERE other.community_id = c.id AND other.user_id <> ?),
                 (SELECT MIN(admin.id) FROM users admin WHERE admin.role = 'admin' AND admin.id <> ?),
                 c.created_by
             )
             WHERE c.created_by = ?"
        );
        $handOver->bind_param("iii", $userId, $userId, $userId);
        $handOver->execute();
        $handOver->close();

        /* the account and everything that belongs to it (foreign keys: ON DELETE CASCADE) */
        $deleteProfile = $conn->prepare("DELETE FROM energy_profiles WHERE user_id = ?");
        $deleteProfile->bind_param("i", $userId);
        $deleteProfile->execute();
        $deleteProfile->close();

        /* the admin never deletes another administrator; anyone may delete their own account */
        $deleteUser = $conn->prepare(
            $actorUserId !== null
                ? "DELETE FROM users WHERE id = ? AND role <> 'admin'"
                : "DELETE FROM users WHERE id = ?"
        );
        $deleteUser->bind_param("i", $userId);
        $deleteUser->execute();
        $deleteUser->close();

        /* the other members are free again: waiting communities may start */
        activateEligiblePendingCommunities($conn);

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        error_log("EnergyMatch: account not deleted: " . $error->getMessage());
        throw $error;
    }
}

/*
 * Cancels every active or pending community of a user and informs its
 * other members ($notificationType). Runs inside the caller's transaction.
 * $actorUserId null = each notification names its own recipient as actor.
 */
function emCancelCommunitiesOfUser(mysqli $conn, int $userId, string $notificationType, ?int $actorUserId): void
{
    $communities = $conn->prepare(
        "SELECT DISTINCT c.id
         FROM communities c
         INNER JOIN community_members cm ON cm.community_id = c.id
         WHERE cm.user_id = ?
           AND cm.invitation_status IN ('pending', 'accepted')
           AND c.status IN ('pending', 'active')"
    );
    $communities->bind_param("i", $userId);
    $communities->execute();
    $communityIds = array_map(
        function ($row) { return (int) $row["id"]; },
        $communities->get_result()->fetch_all(MYSQLI_ASSOC)
    );
    $communities->close();

    foreach ($communityIds as $communityId) {
        if ($actorUserId !== null) {
            $notify = $conn->prepare(
                "INSERT INTO community_notifications
                     (community_id, recipient_user_id, actor_user_id, notification_type, is_read)
                 SELECT cm.community_id, cm.user_id, ?, ?, 0
                 FROM community_members cm
                 WHERE cm.community_id = ?
                   AND cm.user_id <> ?
                   AND cm.invitation_status IN ('pending', 'accepted')"
            );
            $notify->bind_param("isii", $actorUserId, $notificationType, $communityId, $userId);
        } else {
            $notify = $conn->prepare(
                "INSERT INTO community_notifications
                     (community_id, recipient_user_id, actor_user_id, notification_type, is_read)
                 SELECT cm.community_id, cm.user_id, cm.user_id, ?, 0
                 FROM community_members cm
                 WHERE cm.community_id = ?
                   AND cm.user_id <> ?
                   AND cm.invitation_status IN ('pending', 'accepted')"
            );
            $notify->bind_param("sii", $notificationType, $communityId, $userId);
        }
        $notify->execute();
        $notify->close();

        $cancel = $conn->prepare(
            "UPDATE communities SET status = 'cancelled'
             WHERE id = ? AND status IN ('pending', 'active')"
        );
        $cancel->bind_param("i", $communityId);
        $cancel->execute();
        $cancel->close();
    }

}

/*
 * 5th approved report: the account is blocked, not deleted yet.
 * Its communities are cancelled now (members informed), the profile and
 * contributions are removed (no more matching), and banned_at is set.
 * The user sees the farewell page once (farewell.php), then it is deleted.
 * Returns false when the database has no banned_at column yet.
 */
function emBanReportedAccount(mysqli $conn, int $userId, int $adminUserId): bool
{
    $column = $conn->query("SHOW COLUMNS FROM users LIKE 'banned_at'");
    if (!$column || $column->num_rows === 0) {
        return false;
    }

    require_once __DIR__ . "/community_events.php";

    $conn->begin_transaction();

    try {
        emCancelCommunitiesOfUser($conn, $userId, "member_removed", $adminUserId);

        foreach (["user_contributions", "energy_profiles", "remember_tokens"] as $table) {
            $delete = $conn->prepare("DELETE FROM `$table` WHERE user_id = ?");
            $delete->bind_param("i", $userId);
            $delete->execute();
            $delete->close();
        }

        $ban = $conn->prepare("UPDATE users SET banned_at = NOW() WHERE id = ? AND role <> 'admin'");
        $ban->bind_param("i", $userId);
        $ban->execute();
        $ban->close();

        activateEligiblePendingCommunities($conn);

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        error_log("EnergyMatch: account not blocked: " . $error->getMessage());
        throw $error;
    }

    return true;
}

/* Blocked accounts that never came back for the farewell are deleted after EM_BANNED_PURGE_DAYS */
function emPurgeBannedAccounts(mysqli $conn): void
{
    try {
        $days = EM_BANNED_PURGE_DAYS;
        $statement = $conn->prepare("SELECT id FROM users WHERE banned_at IS NOT NULL AND banned_at < NOW() - INTERVAL ? DAY");
        $statement->bind_param("i", $days);
        $statement->execute();
        $ids = array_map(function ($row) { return (int) $row[0]; }, $statement->get_result()->fetch_all(MYSQLI_NUM));
        $statement->close();

        foreach ($ids as $id) {
            emDeleteAccount($conn, $id, "member_removed", null);
        }
    } catch (Throwable $error) {
        /* no banned_at column yet: nothing to purge */
    }
}
