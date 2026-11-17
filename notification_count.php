<?php

/*
|--------------------------------------------------------------------------
| NOTIFICATION COUNT (bell in the navbar)
|--------------------------------------------------------------------------
|
| Pending community invitations + unread community updates.
|
*/

function emNotificationCount($conn, $userId)
{
    $total = 0;

    $queries = [
        /* 1. Pending community invitations */
        "
        SELECT COUNT(*) AS total
        FROM community_members cm
        INNER JOIN communities c
            ON c.id = cm.community_id
        WHERE cm.user_id = ?
          AND cm.invitation_status = 'pending'
          AND c.status = 'pending'
        ",

        /* 2. Unread community updates (accept / reject notifications) */
        "
        SELECT COUNT(*) AS total
        FROM community_notifications
        WHERE recipient_user_id = ?
          AND is_read = 0
        ",
    ];

    foreach ($queries as $sql) {
        $statement = $conn->prepare($sql);

        if (!$statement) {
            continue;
        }

        $statement->bind_param("i", $userId);
        $statement->execute();

        $row = $statement->get_result()->fetch_assoc();
        $total += (int) ($row["total"] ?? 0);

        $statement->close();
    }

    return $total;
}
