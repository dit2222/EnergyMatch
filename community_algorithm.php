<?php

/*

|--------------------------------------------------------------------------

| EnergyMatch - Community Algorithm

|--------------------------------------------------------------------------

| Core rules:

| 1. Every automatic community has exactly 5 members.

| 2. The logged-in user is always the creator/member 1.

| 3. Every user's contribution score is the SUM of estimated_value

|    from user_contributions.

| 4. Fair Share = User Contribution / Total Group Contribution * 100.

| 5. A community is valid only when Fair Share >= Minimum Share

|    for ALL 5 members.

| 6. No old offers/looking_for score and no arbitrary 70/20/10 weights.

| 7. Contribution diversity is informational only; it is NOT mandatory.

| 8. Valid communities are ranked with max-min fairness:

|    highest minimum (Fair Share - Minimum Share) margin first.

| 9. Automatic results are returned 5 communities at a time.

|--------------------------------------------------------------------------

*/

/* =========================================
   EQUIPMENT GROUPS FROM GROUP_CONCAT
   "pv_panels|3|450.00|W;battery_storage|1|10.00|kWh"
   -> list of [type, quantity, capacity, unit]
========================================= */

function emParseEquipmentItems($text)
{
    $items = [];

    foreach (explode(";", (string) $text) as $part) {
        $fields = explode("|", $part);

        if (count($fields) < 4 || trim($fields[0]) === "") {
            continue;
        }

        $items[] = [
            "equipment_type" => trim($fields[0]),
            "equipment_quantity" => (int) $fields[1],
            "equipment_capacity" => (float) $fields[2],
            "equipment_capacity_unit" => trim($fields[3]),
        ];
    }

    return $items;
}

/* =========================================

   LOAD ELIGIBLE MEMBERS

\========================================= */

function emLoadEligibleMembers($conn, $excludeActiveMembers = false)

{

    /*
     * A user can belong to only ONE active community
     * (same rule as respond_invitation.php).
     *
     * $excludeActiveMembers = true  -> used when PROPOSING new communities
     *                                  (matches / manual creation): users who
     *                                  are already accepted members of an
     *                                  active community are left out.
     * $excludeActiveMembers = false -> used when EVALUATING an existing
     *                                  community (community.php), where its
     *                                  own active members must stay in.
     *
     * The filter is a fixed SQL fragment (no user input).
     */
    $activeMemberFilter = $excludeActiveMembers
        ? "
        WHERE NOT EXISTS (
            SELECT 1
            FROM community_members active_cm
            INNER JOIN communities active_c
                ON active_c.id = active_cm.community_id
            WHERE active_cm.user_id = u.id
              AND active_cm.invitation_status = 'accepted'
              AND active_c.status = 'active'
        )
        "
        : "";

    $sql = "

        SELECT

            u.id AS user_id,

            u.name,

            u.surname,

            u.username,

            ep.location,

            ep.minimum_share,

            COALESCE(SUM(uc.estimated_value), 0) AS total_contribution_value,

            COALESCE(SUM(

                CASE

                    WHEN uc.contribution_type = 'capital'

                    THEN uc.estimated_value

                    ELSE 0

                END

            ), 0) AS capital_value,

            COALESCE(SUM(

                CASE

                    WHEN uc.contribution_type = 'land'

                    THEN uc.estimated_value

                    ELSE 0

                END

            ), 0) AS land_value,

            COALESCE(SUM(

                CASE

                    WHEN uc.contribution_type = 'manual_labor'

                    THEN uc.estimated_value

                    ELSE 0

                END

            ), 0) AS manual_labor_value,

            COALESCE(SUM(

                CASE

                    WHEN uc.contribution_type = 'skilled_labor'

                    THEN uc.estimated_value

                    ELSE 0

                END

            ), 0) AS skilled_labor_value,

            COALESCE(SUM(

                CASE

                    WHEN uc.contribution_type = 'equipment'

                    THEN uc.estimated_value

                    ELSE 0

                END

            ), 0) AS equipment_value,

            GROUP_CONCAT(

                DISTINCT uc.contribution_type

                ORDER BY uc.contribution_type

                SEPARATOR ','

            ) AS contribution_types,

            MAX(CASE WHEN uc.contribution_type = 'capital' THEN uc.capital_amount END)

                AS capital_amount,

            MAX(CASE WHEN uc.contribution_type = 'land' THEN uc.land_area END)

                AS land_area,

            MAX(CASE WHEN uc.contribution_type = 'land' THEN uc.land_location END)

                AS land_location,

            MAX(CASE WHEN uc.contribution_type = 'manual_labor' THEN uc.manual_hours END)

                AS manual_hours,

            MAX(CASE WHEN uc.contribution_type = 'skilled_labor' THEN uc.specialty END)

                AS specialty,

            MAX(CASE WHEN uc.contribution_type = 'skilled_labor' THEN uc.skilled_hours END)

                AS skilled_hours,

            /* All equipment groups of the user, one per line:
               type|quantity|capacity|unit */
            GROUP_CONCAT(
                CASE WHEN uc.contribution_type = 'equipment'
                THEN CONCAT_WS('|', uc.equipment_type, uc.equipment_quantity,
                               uc.equipment_capacity, uc.equipment_capacity_unit)
                END
                ORDER BY uc.id
                SEPARATOR ';'
            ) AS equipment_items

        FROM users u

        INNER JOIN energy_profiles ep

            ON ep.user_id = u.id

        INNER JOIN user_contributions uc

            ON uc.user_id = u.id

        {$activeMemberFilter}

        GROUP BY

            u.id,

            u.name,

            u.surname,

            u.username,

            ep.location,

            ep.minimum_share

        HAVING total_contribution_value > 0

        ORDER BY u.id ASC

    ";

    $result = $conn->query($sql);

    if (!$result) {

        throw new Exception($conn->error);

    }

    $members = [];

    while ($row = $result->fetch_assoc()) {

        $types = [];

        if (!empty($row["contribution_types"])) {

            $types = array_values(

                array_filter(

                    explode(",", $row["contribution_types"])

                )

            );

        }

        $members[] = [

            "user_id" => (int) $row["user_id"],

            "name" => (string) $row["name"],

            "surname" => (string) $row["surname"],

            "username" => (string) ($row["username"] ?? ""),

            "location" => trim((string) $row["location"]),

            "minimum_share" => (float) $row["minimum_share"],

            "contribution_value" => (float) $row["total_contribution_value"],

            "contribution_types" => $types,

            "contribution_breakdown" => [

                "capital" => (float) $row["capital_value"],

                "land" => (float) $row["land_value"],

                "manual_labor" => (float) $row["manual_labor_value"],

                "skilled_labor" => (float) $row["skilled_labor_value"],

                "equipment" => (float) $row["equipment_value"]

            ],

            "contribution_details" => [

                "capital_amount" => isset($row["capital_amount"]) ? (float) $row["capital_amount"] : 0.0,

                "land_area" => isset($row["land_area"]) ? (float) $row["land_area"] : 0.0,

                "land_location" => trim((string) ($row["land_location"] ?? "")),

                "manual_hours" => isset($row["manual_hours"]) ? (float) $row["manual_hours"] : 0.0,

                "specialty" => trim((string) ($row["specialty"] ?? "")),

                "skilled_hours" => isset($row["skilled_hours"]) ? (float) $row["skilled_hours"] : 0.0,

                "equipment_items" => emParseEquipmentItems($row["equipment_items"] ?? "")

            ]

        ];

    }

    $result->free();

    return $members;

}

/* =========================================

   INDEX MEMBERS BY USER ID

\========================================= */

/* =========================================
   IS THE USER ALREADY IN AN ACTIVE COMMUNITY?
   Returns the active community id, or null.
\========================================= */

function emUserActiveCommunityId($conn, $userId)
{
    $statement = $conn->prepare("
        SELECT c.id
        FROM community_members cm
        INNER JOIN communities c
            ON c.id = cm.community_id
        WHERE cm.user_id = ?
          AND cm.invitation_status = 'accepted'
          AND c.status = 'active'
        LIMIT 1
    ");

    if (!$statement) {
        throw new Exception($conn->error);
    }

    $userId = (int) $userId;
    $statement->bind_param("i", $userId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row ? (int) $row["id"] : null;
}

function emIndexMembersById($members)

{

    $indexed = [];

    foreach ($members as $member) {

        $indexed[(int) $member["user_id"]] = $member;

    }

    return $indexed;

}

/* =========================================

   GENERATE COMBINATIONS LAZILY

\========================================= */

function emGenerateCombinations(

    $items,

    $size,

    $start = 0,

    $current = []

) {

    if (count($current) === $size) {

        yield $current;

        return;

    }

    $remaining = $size - count($current);

    $total = count($items);

    for (

        $i = $start;

        $i <= $total - $remaining;

        $i++

    ) {

        $next = $current;

        $next[] = $items[$i];

        foreach (

            emGenerateCombinations(

                $items,

                $size,

                $i + 1,

                $next

            ) as $combination

        ) {

            yield $combination;

        }

    }

}

/* =========================================

   FAST MINIMUM-SHARE CHECK

\========================================= */

function emMinimumSharesArePossible($members)

{

    $totalMinimumShare = 0.0;

    foreach ($members as $member) {

        $totalMinimumShare += (float) $member["minimum_share"];

    }

    return $totalMinimumShare <= 100.000001;

}

/* =========================================

   EVALUATE EXACTLY ONE 5-MEMBER COMMUNITY

\========================================= */

function emEvaluateCommunity($members)

{

    $result = [

        "valid" => false,

        "reason" => "",

        "member_count" => count($members),

        "total_group_value" => 0.0,

        "total_minimum_share" => 0.0,

        "minimum_share_slack" => 0.0,

        "minimum_fairness_margin" => null,

        "members" => []

    ];

    if (count($members) !== 5) {

        $result["reason"] = "A community must contain exactly 5 members.";
        $result["reason_key"] = "eval_reason_not_five";

        return $result;

    }

    $seenIds = [];

    foreach ($members as $member) {

        $memberId = (int) $member["user_id"];

        if (isset($seenIds[$memberId])) {

            $result["reason"] = "Duplicate member detected.";
            $result["reason_key"] = "eval_reason_duplicate";

            return $result;

        }

        $seenIds[$memberId] = true;

    }

    $totalGroupValue = 0.0;

    $totalMinimumShare = 0.0;

    foreach ($members as $member) {

        $contributionValue = (float) $member["contribution_value"];

        $minimumShare = (float) $member["minimum_share"];

        if ($contributionValue <= 0) {

            $result["reason"] = "Every member must have a positive contribution score.";
            $result["reason_key"] = "eval_reason_no_value";

            return $result;

        }

        if ($minimumShare < 0 || $minimumShare > 100) {

            $result["reason"] = "Invalid minimum share value.";
            $result["reason_key"] = "eval_reason_bad_minimum";

            return $result;

        }

        $totalGroupValue += $contributionValue;

        $totalMinimumShare += $minimumShare;

    }

    $result["total_group_value"] = round($totalGroupValue, 2);

    $result["total_minimum_share"] = round($totalMinimumShare, 2);

    $result["minimum_share_slack"] = round(100 - $totalMinimumShare, 2);

    if ($totalMinimumShare > 100.000001) {

        $result["reason"] = "The sum of minimum shares is greater than 100%.";
        $result["reason_key"] = "eval_reason_minimum_sum";

        return $result;

    }

    if ($totalGroupValue <= 0) {

        $result["reason"] = "The community has no positive contribution score.";
        $result["reason_key"] = "eval_reason_zero_total";

        return $result;

    }

    $allMembersPass = true;

    $minimumFairnessMargin = null;

    $evaluatedMembers = [];

    foreach ($members as $member) {

        $contributionValue = (float) $member["contribution_value"];

        $minimumShare = (float) $member["minimum_share"];

        $fairShareRaw =

            ($contributionValue / $totalGroupValue) * 100;

        $fairnessMarginRaw =

            $fairShareRaw - $minimumShare;

        $meetsMinimum =

            ($fairShareRaw + 0.000001) >= $minimumShare;

        if (!$meetsMinimum) {

            $allMembersPass = false;

        }

        if (

            $minimumFairnessMargin === null

            ||

            $fairnessMarginRaw < $minimumFairnessMargin

        ) {

            $minimumFairnessMargin = $fairnessMarginRaw;

        }

        $member["fair_share"] = round($fairShareRaw, 2);

        $member["fairness_margin"] = round($fairnessMarginRaw, 2);

        $member["meets_minimum_share"] = $meetsMinimum;

        $evaluatedMembers[] = $member;

    }

    $result["members"] = $evaluatedMembers;

    $result["minimum_fairness_margin"] =

        round((float) $minimumFairnessMargin, 2);

    if (!$allMembersPass) {

        $result["reason"] =

            "At least one member receives a Fair Share below their Minimum Share.";

        $result["reason_key"] = "eval_reason_below_minimum";

        return $result;

    }

    $result["valid"] = true;

    $result["reason"] = "Valid community.";
    $result["reason_key"] = "eval_reason_valid";

    return $result;

}

/* =========================================

   MANUAL MODE HELPER

   SAME FAIRNESS RULES AS AUTOMATIC MODE

\========================================= */

function emEvaluateCommunityByIds(

    $memberIds,

    $membersById

) {

    $memberIds = array_values(

        array_unique(

            array_map("intval", $memberIds)

        )

    );

    if (count($memberIds) !== 5) {

        return [

            "valid" => false,

            "reason" => "Manual communities must contain exactly 5 distinct members.",
            "reason_key" => "eval_reason_not_five",

            "member_count" => count($memberIds),

            "total_group_value" => 0.0,

            "total_minimum_share" => 0.0,

            "minimum_share_slack" => 0.0,

            "minimum_fairness_margin" => null,

            "members" => []

        ];

    }

    $members = [];

    foreach ($memberIds as $memberId) {

        if (!isset($membersById[$memberId])) {

            return [

                "valid" => false,

                "reason" => "One or more selected users do not have a valid contribution profile.",
                "reason_key" => "eval_reason_not_eligible",

                "member_count" => count($memberIds),

                "total_group_value" => 0.0,

                "total_minimum_share" => 0.0,

                "minimum_share_slack" => 0.0,

                "minimum_fairness_margin" => null,

                "members" => []

            ];

        }

        $members[] = $membersById[$memberId];

    }

    return emEvaluateCommunity($members);

}

/* =========================================

   AUTOMATIC COMMUNITY GENERATION

\========================================= */

/* =========================================

   CHECK IF EXACT COMMUNITY ALREADY EXISTS

\========================================= */

function emCommunityCombinationAlreadyExists($conn, $memberIds)

{

    // Καθαρίζουμε τα IDs ώστε να έχουμε μόνο μοναδικούς ακέραιους.

    $memberIds = array_values(

        array_unique(

            array_map("intval", $memberIds)

        )

    );

    // Μια EnergyMatch κοινότητα πρέπει να έχει ακριβώς 5 διαφορετικά μέλη.

    if (count($memberIds) !== 5) {

        return false;

    }

    // Η σειρά επιλογής των χρηστών δεν πρέπει να παίζει ρόλο.

    sort($memberIds, SORT_NUMERIC);

    $placeholders = implode(",", array_fill(0, 5, "?"));

    $sql = "

        SELECT cm.community_id

        FROM community_members cm

        /*
         * Only communities that are still alive (pending or active) block a new proposal.
         * If the community was cancelled (a member left), rejected or
         * completed, the same 5 people may propose it again.
         */
        INNER JOIN communities c

            ON c.id = cm.community_id

           AND c.status IN ('pending', 'active')

        GROUP BY cm.community_id

        HAVING

            COUNT(DISTINCT cm.user_id) = 5

            AND COUNT(

                DISTINCT CASE

                    WHEN cm.user_id IN ($placeholders)

                    THEN cm.user_id

                END

            ) = 5

        LIMIT 1

    ";

    $statement = $conn->prepare($sql);

    if (!$statement) {

        throw new Exception($conn->error);

    }

    $statement->bind_param(

        "iiiii",

        $memberIds[0],

        $memberIds[1],

        $memberIds[2],

        $memberIds[3],

        $memberIds[4]

    );

    if (!$statement->execute()) {

        $error = $statement->error;

        $statement->close();

        throw new Exception($error);

    }

    $statement->store_result();

    $alreadyExists = $statement->num_rows > 0;

    $statement->close();

    return $alreadyExists;

}

/* =========================================
   DIFFERENT PROPOSALS FIRST
   (see the explanation where it is called)
========================================= */
const EM_PROPOSAL_MAX_SHARED = 2;

function emDiversifyProposals(array $rankedCommunities, int $currentUserId): array
{
    /*
     * Two groups share more than EM_PROPOSAL_MAX_SHARED of their other
     * members exactly when they have a common subset of
     * EM_PROPOSAL_MAX_SHARED + 1 members. So every chosen group stores its
     * subsets of that size in a lookup table, and a new group only checks
     * its own subsets there. Same result as comparing it with every chosen
     * group, but the time grows linearly with the number of groups.
     */
    $subsetSize = EM_PROPOSAL_MAX_SHARED + 1;
    $takenSubsets = [];
    $chosen = [];
    $later = [];
    foreach ($rankedCommunities as $community) {
        $others = array_values(array_diff($community["community_ids"], [$currentUserId]));
        sort($others, SORT_NUMERIC);
        $keys = [];
        foreach (emGenerateCombinations($others, $subsetSize) as $subset) {
            $keys[] = implode("-", $subset);
        }
        $isDifferent = true;
        foreach ($keys as $key) {
            if (isset($takenSubsets[$key])) {
                $isDifferent = false;
                break;
            }
        }
        if ($isDifferent) {
            $chosen[] = $community;
            foreach ($keys as $key) {
                $takenSubsets[$key] = true;
            }
        } else {
            $later[] = $community;
        }
    }
    return array_merge($chosen, $later);
}

if (!defined("ENERGYMATCH_ALGORITHM_LIBRARY_ONLY")) {

$community_algorithm_error = null;

/* Set when the logged-in user already belongs to an active community */
$creator_active_community_id = null;

$all_valid_communities = [];

$community_proposals = [];

$community_limit = 5;

$community_offset = isset($_GET["community_offset"])

    ? max(0, (int) $_GET["community_offset"])

    : 0;

$has_more_communities = false;

$next_community_offset = null;

$previous_community_offset = null;

$best_community_members = [];

$best_community_ids = [];

$best_community_metrics = [

    "valid" => false,

    "reason" => "No valid community found.",

    "total_group_value" => 0.0,

    "total_minimum_share" => 0.0,

    "minimum_share_slack" => 0.0,

    "minimum_fairness_margin" => null,

    "members" => []

];

try {

    /* Candidates: users who are NOT already in an active community */
    $eligibleMembers = emLoadEligibleMembers($conn, true);

    $membersById = emIndexMembersById($eligibleMembers);

    /*
     * One active community per user: if the logged-in user is already
     * an accepted member of an active community, no new proposals are made.
     * (The member list above still loads, so they can browse members.)
     */
    $creator_active_community_id =
        emUserActiveCommunityId($conn, (int) $user_id);

    if ($creator_active_community_id !== null) {

        throw new Exception(t("already_in_active_community_text"));
    }

    if (!isset($membersById[(int) $user_id])) {

        throw new Exception(

            "The current user does not yet have a valid contribution profile."

        );

    }

    $creator_member = $membersById[(int) $user_id];

    $community_candidates = [];

    foreach ($eligibleMembers as $member) {

        if ((int) $member["user_id"] === (int) $user_id) {

            continue;

        }

        $community_candidates[] = $member;

    }

    if (count($community_candidates) >= 4) {

        foreach (

            emGenerateCombinations(

                $community_candidates,

                4

            ) as $combination

        ) {

            $possibleCommunity = array_merge(

                [$creator_member],

                $combination

            );

            /*

             * Cheap early rejection.

             * This is necessary but NOT sufficient.

             */

            if (!emMinimumSharesArePossible($possibleCommunity)) {

                continue;

            }

            $evaluation =

                emEvaluateCommunity($possibleCommunity);

            if (!$evaluation["valid"]) {

                continue;

            }

            $communityIds = [];

            foreach ($evaluation["members"] as $member) {

                $communityIds[] = (int) $member["user_id"];

            }

            sort($communityIds, SORT_NUMERIC);

            $evaluation["community_ids"] = $communityIds;

            $evaluation["community_key"] =

                implode("-", $communityIds);

            $all_valid_communities[] = $evaluation;

        }

    }

    /*

     * RANK ONLY THE ALREADY VALID COMMUNITIES.

     *

     * Hard feasibility is never relaxed:

     * every member has already passed Fair Share >= Minimum Share.

     *

     * Ranking rules:

     * 1. Higher minimum fairness margin first (max-min fairness).

     * 2. If tied, higher minimum-share slack first.

     * 3. If still tied, deterministic numeric member-ID order.

     *

     * No arbitrary weighted score is used.

     */

    usort(

        $all_valid_communities,

        function ($first, $second) {

            $marginDifference =

                (float) $second["minimum_fairness_margin"]

                -

                (float) $first["minimum_fairness_margin"];

            if (abs($marginDifference) > 0.000001) {

                return $marginDifference > 0 ? 1 : -1;

            }

            $slackDifference =

                (float) $second["minimum_share_slack"]

                -

                (float) $first["minimum_share_slack"];

            if (abs($slackDifference) > 0.000001) {

                return $slackDifference > 0 ? 1 : -1;

            }

            $firstIds = $first["community_ids"] ?? [];

            $secondIds = $second["community_ids"] ?? [];

            $maxLength = max(count($firstIds), count($secondIds));

            for ($i = 0; $i < $maxLength; $i++) {

                $firstId = $firstIds[$i] ?? 0;

                $secondId = $secondIds[$i] ?? 0;

                if ($firstId === $secondId) {

                    continue;

                }

                return $firstId <=> $secondId;

            }

            return 0;

        }

    );

    /*
     * DIFFERENT PROPOSALS FIRST.
     *
     * The best groups are often small variations of each other (the same
     * strong members, one person swapped). The list is re-ordered so that
     * the first proposals are really different options:
     * walking down the ranking, a group is taken now if it shares at most
     * EM_PROPOSAL_MAX_SHARED of its 4 other members with every group
     * taken before it. All other valid groups follow afterwards in their
     * original order, so no valid group is lost.
     */
    $all_valid_communities = emDiversifyProposals(
        $all_valid_communities,
        (int) $user_id
    );

    $community_proposals = array_slice(

        $all_valid_communities,

        $community_offset,

        $community_limit

    );

    $has_more_communities =

        ($community_offset + $community_limit)

        <

        count($all_valid_communities);

    if ($has_more_communities) {

        $next_community_offset =

            $community_offset + $community_limit;

    }

    if ($community_offset > 0) {

        $previous_community_offset =

            max(0, $community_offset - $community_limit);

    }

    /*

     * Backward-friendly variables for the first proposal.

     * These now represent the new Fair Share model, NOT the old score model.

     */

    if (!empty($community_proposals)) {

        $best_community_metrics =

            $community_proposals[0];

        foreach (

            $community_proposals[0]["members"] as $member

        ) {

            if ((int) $member["user_id"] === (int) $user_id) {

                continue;

            }

            $best_community_members[] = $member;

            $best_community_ids[] = (int) $member["user_id"];

        }

    }

} catch (Throwable $e) {

    $community_algorithm_error = $e->getMessage();

}

}

?>
