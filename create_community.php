<?php
session_start();
require_once __DIR__ . "/remember_me.php";

/* CSRF protection: reject forged POST requests (see csrf.php) */
require_once __DIR__ . "/csrf.php";
csrfVerify();
require_once "db.php";
require_once "lang.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: sign_in.php");
    exit;
}
$currentUserId = (int) $_SESSION["user_id"];
$isGreek = ($_SESSION["language"] ?? "en") === "gr";

/*
|--------------------------------------------------------------------------
| Reuse the exact same Fair Share rules as automatic matching
|--------------------------------------------------------------------------
*/
define("ENERGYMATCH_ALGORITHM_LIBRARY_ONLY", true);
require_once "community_algorithm.php";
require_once "contribution_tags.php";

function safe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function moneyValue($value)
{
    return "€" . number_format((float) $value, 2, ".", ",");
}

function percentValue($value)
{
    return number_format((float) $value, 2) . "%";
}
$pageError = "";
$manualEvaluation = null;
$selectedMemberIds = [];
$communityNameValue = "";
/*
 * Manual creation in two steps:
 *   step "preview" (the form)        -> the group is checked and shown,
 *                                       "Are you sure?", nothing is sent yet
 *   step "confirm" (preview button)  -> the proposal is saved and sent
 *   step "edit"    (preview button)  -> back to the form, same selection
 * A post without a step (button "Choose this community" in Matches,
 * where the whole group is already shown) is sent at once, as before.
 */
$creationStep = "send";
$showPreview = false;
try {
    /*
     * One active community per user: a user who is already in an
     * active community cannot create a new one. matches.php shows why.
     */
    if (emUserActiveCommunityId($conn, $currentUserId) !== null) {
        header("Location: matches.php");
        exit;
    }

    /* Candidates exclude users who are already in an active community */
    $eligibleMembers = emLoadEligibleMembers($conn, true);
    $membersById = emIndexMembersById($eligibleMembers);
    if (!isset($membersById[$currentUserId])) {
        header("Location: profile.php?error=contribution_required");
        exit;
    }
    $creator = $membersById[$currentUserId];
    $candidates = [];
    foreach ($eligibleMembers as $member) {
        if ((int) $member["user_id"] === $currentUserId) {
            continue;
        }
        $candidates[] = $member;
    }

    /*
     * "Propose this group again" (History in accepted_invitations.php):
     * create_community.php?reuse=<old community id>
     *   - only a cancelled or declined proposal I was a member of
     *   - the other 4 must still be available now (not in an active
     *     community, account not deleted/blocked, with contributions)
     *   - all 4 available -> straight to the preview (Fair Share is checked
     *     again with today's contributions); nothing is sent before
     *     "Yes, send the proposal", and everyone has to accept again
     *   - someone unavailable -> the form, with the others already selected
     *     and a message naming who has to be replaced
     */
    $reuseCommunityId = $_SERVER["REQUEST_METHOD"] === "GET" ? (int) ($_GET["reuse"] ?? 0) : 0;
    $reuseRequest = false;
    if ($reuseCommunityId > 0) {
        $reuseStatement = $conn->prepare(
            "SELECT c.name, c.status
             FROM communities c
             INNER JOIN community_members me
                 ON me.community_id = c.id
                AND me.user_id = ?
             WHERE c.id = ?"
        );
        $reuseStatement->bind_param("ii", $currentUserId, $reuseCommunityId);
        $reuseStatement->execute();
        $reuseCommunity = $reuseStatement->get_result()->fetch_assoc();
        $reuseStatement->close();

        if (!$reuseCommunity || !in_array($reuseCommunity["status"], ["cancelled", "rejected"], true)) {
            $pageError = t("reuse_not_possible");
            $reuseCommunityId = 0;
        } else {
            $communityNameValue = (string) $reuseCommunity["name"];

            /* LEFT JOIN: a member whose account was deleted has no users row */
            $reuseMembersStatement = $conn->prepare(
                "SELECT cm.user_id, u.name, u.surname
                 FROM community_members cm
                 LEFT JOIN users u ON u.id = cm.user_id
                 WHERE cm.community_id = ?
                   AND cm.user_id <> ?
                 ORDER BY cm.id ASC"
            );
            $reuseMembersStatement->bind_param("ii", $reuseCommunityId, $currentUserId);
            $reuseMembersStatement->execute();
            $reuseMembers = $reuseMembersStatement->get_result()->fetch_all(MYSQLI_ASSOC);
            $reuseMembersStatement->close();

            $unavailableNames = [];
            foreach ($reuseMembers as $reuseMember) {
                $reuseUserId = (int) $reuseMember["user_id"];
                if (isset($membersById[$reuseUserId])) {
                    $selectedMemberIds[] = $reuseUserId;
                } elseif ($reuseMember["name"] !== null) {
                    $unavailableNames[] = trim($reuseMember["name"] . " " . $reuseMember["surname"]);
                }
            }
            /* members whose account no longer exists at all */
            $missingCount = 4 - count($selectedMemberIds) - count($unavailableNames);

            if (count($selectedMemberIds) === 4) {
                $reuseRequest = true;
                $creationStep = "preview";
            } else {
                $who = $unavailableNames;
                if ($missingCount > 0) {
                    $who[] = str_replace("{count}", (string) $missingCount, t("reuse_deleted_members"));
                }
                $pageError = str_replace("{names}", implode(", ", $who), t("reuse_members_unavailable"));
            }
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" || $reuseRequest) {
        if (!$reuseRequest) {
            $communityNameValue = trim(
                (string) ($_POST["community_name"] ?? "")
            );
            $postedIds = $_POST["selected_members"] ?? [];
            if (!is_array($postedIds)) {
                $postedIds = [];
            }
            $selectedMemberIds = array_values(
                array_unique(
                    array_filter(
                        array_map("intval", $postedIds),

                        function ($id) use ($currentUserId) {
                            return $id > 0 && $id !== $currentUserId;
                        }
                    )
                )
            );
            $creationStep = (string) ($_POST["step"] ?? "send");
        }

        if ($creationStep === "edit") {
            /* back from the preview: the form is shown again with the same choices */
        } elseif (count($selectedMemberIds) !== 4) {
            $pageError = t("create_you_must_select_exactly_4_other_members_together_with_y");
        } else {
            $memberIds = array_merge(
                [$currentUserId],
                $selectedMemberIds
            );
            $manualEvaluation = emEvaluateCommunityByIds(
                $memberIds,
                $membersById
            );
            if (!$manualEvaluation["valid"]) {
                $pageError = $reuseRequest
                    ? t("reuse_no_longer_valid")
                    : t("create_this_5_member_group_does_not_pass_the_fair_share_rules");
            } elseif (emCommunityCombinationAlreadyExists($conn, $memberIds)) {
                $pageError = t("create_this_exact_5_member_community_has_already_been_proposed");
            } elseif ($creationStep === "preview") {
                /* valid group: show it and ask before sending anything */
                $showPreview = true;
            } else {
                if ($communityNameValue === "") {
                    /* default name in the creator's language, with their region (or the date) */
                    $creatorRegion = trim((string) ($creator["location"] ?? ""));
                    $communityNameValue =
                        ($isGreek ? "Ενεργειακή Κοινότητα" : "Energy Community")
                        . " · "
                        . ($creatorRegion !== "" ? $creatorRegion : date("d/m/Y"));
                }

                // A newly sent proposal waits for the other 4 members.
                $communityStatus = "pending";
                $conn->begin_transaction();
                try {
                    // Re-check immediately before INSERT. This also protects
                    // against a normal double-submit of the same group.
                    if (emCommunityCombinationAlreadyExists($conn, $memberIds)) {
                        throw new RuntimeException("DUPLICATE_COMMUNITY_COMBINATION");
                    }
                    $communityStatement = $conn->prepare(
                        "
                        INSERT INTO communities (
                            name,
                            created_by,
                            status
                        )
                        VALUES (?, ?, ?)
                        "
                    );
                    if (!$communityStatement) {
                        throw new Exception($conn->error);
                    }
                    $communityStatement->bind_param(
                        "sis",
                        $communityNameValue,
                        $currentUserId,
                        $communityStatus
                    );
                    if (!$communityStatement->execute()) {
                        throw new Exception($communityStatement->error);
                    }
                    $communityId = (int) $conn->insert_id;
                    $communityStatement->close();
                    $memberStatement = $conn->prepare(
                        "
                        INSERT INTO community_members (
                            community_id,
                            user_id,
                            invitation_status,
                            responded_at
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            IF(? = 1, NOW(), NULL)
                        )
                        "
                    );
                    if (!$memberStatement) {
                        throw new Exception($conn->error);
                    }
                    foreach ($manualEvaluation["members"] as $member) {
                        $memberUserId = (int) $member["user_id"];
                        if ($memberUserId === $currentUserId) {
                            // The creator has already accepted their own proposal.
                            $invitationStatus = "accepted";
                            $hasResponded = 1;
                        } else {
                            // The other four members receive a pending invitation.
                            $invitationStatus = "pending";
                            $hasResponded = 0;
                        }
                        $memberStatement->bind_param(
                            "iisi",
                            $communityId,
                            $memberUserId,
                            $invitationStatus,
                            $hasResponded
                        );
                        if (!$memberStatement->execute()) {
                            throw new Exception($memberStatement->error);
                        }
                    }
                    $memberStatement->close();
                    $conn->commit();
                    header(
                        "Location: community.php?id=" .
                        $communityId .
                        "&created=1"
                    );
                    exit;
                } catch (Throwable $error) {
                    $conn->rollback();
                    if ($error->getMessage() === "DUPLICATE_COMMUNITY_COMBINATION") {
                        $pageError = t("create_this_exact_5_member_community_has_already_been_proposed");
                    } else {
                        throw $error;
                    }
                }
            }
        }
    }
} catch (Throwable $error) {
    $pageError = $isGreek
        ? "Παρουσιάστηκε σφάλμα: " . $error->getMessage()
        : "An error occurred: " . $error->getMessage();
    $creator = $creator ?? null;
    $candidates = $candidates ?? [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo t("create_en"); ?>">
<head>
    <?php include "head_assets.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EnergyMatch - <?php echo t("create_manual_community"); ?></title>
    <link rel="stylesheet" href="css/create_community.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="css/chatbot.css?v=<?php echo ASSET_VERSION; ?>">
</head>
<body>

<section class="hero-section">
<?php include "navbar.php"; ?>
<div class="manual-community-wrapper">
    <div class="manual-panel">
        <h1><?php echo t("create_community_submit"); ?></h1>
        <p class="manual-muted">
            <?php echo t("create_select_exactly_4_other_members_together_with_you_the_gr"); ?>
        </p>
        <div class="info-note">
            <?php echo t("create_economic_contribution_value_does_not_necessarily_mean_c"); ?>
        </div>

        <?php if ($creator): ?>
            <div class="creator-summary">
                <div class="summary-box">
                    <small><?php echo t("create_creator"); ?></small>
                    <strong><?php echo safe($creator["name"] . " " . $creator["surname"]); ?></strong>
                </div>
                <div class="summary-box">
                    <small><?php echo t("create_economic_contribution_value"); ?></small>
                    <strong><?php echo moneyValue($creator["contribution_value"]); ?></strong>
                </div>
                <div class="summary-box">
                    <small><?php echo t("summary_minimum_share"); ?></small>
                    <strong><?php echo percentValue($creator["minimum_share"]); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($showPreview): ?>
        <?php
        $previewTotal = 0.0;
        $previewMargin = null;
        foreach ($manualEvaluation["members"] as $member) {
            $previewTotal += (float) $member["contribution_value"];
            $margin = (float) $member["fair_share"] - (float) $member["minimum_share"];
            $previewMargin = $previewMargin === null ? $margin : min($previewMargin, $margin);
        }
        ?>
        <!-- PREVIEW: nothing has been sent yet -->
        <section class="manual-panel preview-panel" id="communityPreview">
            <span class="preview-label"><i data-lucide="eye"></i> <?php echo t("create_preview_label"); ?></span>
            <h2><?php echo safe($communityNameValue !== "" ? $communityNameValue : t("create_preview_auto_name")); ?></h2>
            <p class="manual-muted"><?php echo t("create_preview_intro"); ?></p>
            <?php if ($reuseRequest ?? false): ?>
                <div class="info-note"><i data-lucide="history"></i> <?php echo t("reuse_preview_note"); ?></div>
            <?php endif; ?>

            <div class="preview-stats">
                <div><small><?php echo t("create_preview_members"); ?></small><strong>5</strong></div>
                <div><small><?php echo t("create_economic_contribution_value"); ?></small><strong><?php echo moneyValue($previewTotal); ?></strong></div>
                <div><small><?php echo t("create_preview_min_margin"); ?></small><strong class="pass">+<?php echo percentValue($previewMargin); ?></strong></div>
            </div>

            <div class="preview-table-wrap">
            <table class="evaluation-table preview-table">
                <thead>
                    <tr>
                        <th><?php echo t("create_member"); ?></th>
                        <th><?php echo t("create_contribution"); ?></th>
                        <th>Minimum</th>
                        <th>Fair Share</th>
                        <th><?php echo t("create_preview_status"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($manualEvaluation["members"] as $member): ?>
                        <?php $isMe = (int) $member["user_id"] === $currentUserId; ?>
                        <tr>
                            <td>
                                <strong><?php echo safe($member["name"] . " " . $member["surname"]); ?></strong>
                                <?php if ($isMe): ?><span class="preview-you"><?php echo t("you_label"); ?></span><?php endif; ?>
                                <?php if (($member["username"] ?? "") !== ""): ?><br><small>@<?php echo safe($member["username"]); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo moneyValue($member["contribution_value"]); ?></td>
                            <td><?php echo percentValue($member["minimum_share"]); ?></td>
                            <td><?php echo percentValue($member["fair_share"]); ?></td>
                            <td>
                                <?php echo $isMe
                                    ? '<span class="preview-chip is-accepted">' . t("create_preview_auto_accepted") . '</span>'
                                    : '<span class="preview-chip">' . t("create_preview_will_be_invited") . '</span>'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div class="preview-confirm">
                <i data-lucide="circle-help"></i>
                <div>
                    <strong><?php echo t("create_preview_sure"); ?></strong>
                    <p><?php echo t("create_preview_sure_text"); ?></p>
                </div>
            </div>

            <div class="manual-actions">
                <form method="POST" action="create_community.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="step" value="edit">
                    <input type="hidden" name="community_name" value="<?php echo safe($communityNameValue); ?>">
                    <?php foreach ($selectedMemberIds as $selectedId): ?>
                        <input type="hidden" name="selected_members[]" value="<?php echo (int) $selectedId; ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="manual-btn secondary">
                        <i data-lucide="arrow-left"></i> <?php echo t("create_preview_back"); ?>
                    </button>
                </form>
                <form method="POST" action="create_community.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="step" value="confirm">
                    <input type="hidden" name="community_name" value="<?php echo safe($communityNameValue); ?>">
                    <?php foreach ($selectedMemberIds as $selectedId): ?>
                        <input type="hidden" name="selected_members[]" value="<?php echo (int) $selectedId; ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="manual-btn primary" id="sendInvitationsButton">
                        <i data-lucide="send"></i> <?php echo t("create_preview_send"); ?>
                    </button>
                </form>
            </div>
        </section>
    <?php else: ?>
    <form method="POST" class="manual-panel" id="manualCommunityForm">
        <?php echo csrfField(); ?>
        <input type="hidden" name="step" value="preview">

        <?php if ($pageError !== ""): ?>
            <div class="error-box">
                <strong><?php echo safe($pageError); ?></strong>

                <?php if ($manualEvaluation && empty($manualEvaluation["valid"])): ?>
                    <div style="margin-top:7px;">
                        <?php echo safe(isset($manualEvaluation["reason_key"]) ? t($manualEvaluation["reason_key"]) : ($manualEvaluation["reason"] ?? "")); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($manualEvaluation && !empty($manualEvaluation["members"])): ?>
            <div class="evaluation-box">
                <strong>
                    <?php echo t("create_selected_group_validation_result"); ?>
                </strong>
                <table class="evaluation-table">
                    <thead>
                        <tr>
                            <th><?php echo t("create_member"); ?></th>
                            <th><?php echo t("create_contribution"); ?></th>
                            <th>Minimum</th>
                            <th>Fair Share</th>
                            <th><?php echo t("create_check"); ?></th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($manualEvaluation["members"] as $member): ?>
                            <tr>
                                <td><?php echo safe($member["name"] . " " . $member["surname"]); ?></td>
                                <td><?php echo moneyValue($member["contribution_value"]); ?></td>
                                <td><?php echo percentValue($member["minimum_share"]); ?></td>
                                <td><?php echo percentValue($member["fair_share"]); ?></td>
                                <td class="<?php echo $member["meets_minimum_share"] ? "pass" : "fail"; ?>">
                                    <?php echo $member["meets_minimum_share"] ? "PASS" : "FAIL"; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <label class="community-form-label" for="community_name">
            <?php echo t("create_community_name_optional"); ?>
        </label>
        <input
            type="text"
            id="community_name"
            name="community_name"
            class="community-name-input"
            maxlength="150"
            value="<?php echo safe($communityNameValue); ?>"
            placeholder="<?php echo t("create_e_g_solar_community_athens"); ?>"
        >
        <div class="selection-counter">
            <?php echo t("create_selected_members"); ?>
            <span id="selectedCount">0</span>/4
        </div>
        <div class="selected-chips" id="selectedChips" hidden>
            <span class="selected-chips-label"><?php echo t("create_selected_label"); ?></span>
            <div class="selected-chips-list" id="selectedChipsList"></div>
        </div>

        <?php if (count($candidates) >= 4): ?>
            <?php
            /* Filters (only change what is shown; nothing is sent to the server) */
            $contributionFilters = [
                "capital"       => ["icon" => "coins",     "label" => t("create_filter_capital")],
                "land"          => ["icon" => "land-plot", "label" => t("create_filter_land")],
                "manual_labor"  => ["icon" => "hard-hat",  "label" => t("create_filter_manual")],
                "skilled_labor" => ["icon" => "wrench",    "label" => t("create_filter_skilled")],
                "equipment"     => ["icon" => "plug-zap",  "label" => t("create_filter_equipment")],
            ];
            $candidateRegions = [];
            foreach ($candidates as $candidate) {
                $region = trim((string) $candidate["location"]);
                if ($region !== "") {
                    $candidateRegions[mb_strtolower($region, "UTF-8")] = $region;
                }
            }
            natcasesort($candidateRegions);

            /* solar score and factor f for the land details on hover */
            $landInfoByUser = emLoadLandInfo($conn);
            ?>

            <div class="candidate-filters" id="candidateFilters">
                <div class="candidate-filter-row">
                    <div class="candidate-search">
                        <i data-lucide="search"></i>
                        <input
                            type="search"
                            id="candidateSearch"
                            placeholder="<?php echo t("create_filter_search"); ?>"
                            autocomplete="off"
                        >
                    </div>
                    <div class="candidate-region">
                        <i data-lucide="map-pin"></i>
                        <select id="candidateRegion" aria-label="<?php echo t("create_filter_region"); ?>">
                            <option value=""><?php echo t("create_filter_all_regions"); ?></option>
                            <?php foreach ($candidateRegions as $regionKey => $regionLabel): ?>
                                <option value="<?php echo safe($regionKey); ?>"><?php echo safe($regionLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="candidate-chips" role="group" aria-label="<?php echo t("create_filter_types"); ?>">
                    <button type="button" class="candidate-chip is-active" data-type-filter="">
                        <?php echo t("create_filter_all"); ?>
                    </button>
                    <?php foreach ($contributionFilters as $typeKey => $typeFilter): ?>
                        <button type="button" class="candidate-chip" data-type-filter="<?php echo $typeKey; ?>" aria-pressed="false">
                            <i data-lucide="<?php echo $typeFilter["icon"]; ?>"></i>
                            <?php echo $typeFilter["label"]; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="candidate-filter-info">
                    <span id="candidateShown"><?php echo count($candidates); ?></span>
                    <?php echo t("create_filter_of"); ?>
                    <?php echo count($candidates); ?>
                    <?php echo t("create_filter_members"); ?>
                    <button type="button" class="candidate-filter-reset" id="candidateFilterReset" hidden>
                        <i data-lucide="x"></i>
                        <?php echo t("create_filter_reset"); ?>
                    </button>
                </div>
            </div>

            <div class="candidate-grid">

                <?php foreach ($candidates as $candidate): ?>
                    <?php
                    $candidateId = (int) $candidate["user_id"];
                    $checked = in_array(
                        $candidateId,
                        $selectedMemberIds,
                        true
                    );
                    ?>
                    <?php
                    $candidateTypes = array_values(array_filter(
                        (array) ($candidate["contribution_types"] ?? []),
                        function ($type) use ($contributionFilters) {
                            return isset($contributionFilters[$type]);
                        }
                    ));
                    ?>
                    <label
                        class="candidate-card <?php echo $checked ? "selected" : ""; ?>"
                        data-name="<?php echo safe($candidate["name"] . " " . $candidate["surname"]); ?>"
                        data-username="<?php echo safe($candidate["username"] ?? ""); ?>"
                        data-region="<?php echo safe(mb_strtolower(trim((string) $candidate["location"]), "UTF-8")); ?>"
                        data-types="<?php echo safe(implode(",", $candidateTypes)); ?>"
                    >
                        <input
                            type="checkbox"
                            name="selected_members[]"
                            value="<?php echo $candidateId; ?>"
                            <?php echo $checked ? "checked" : ""; ?>
                        >
                        <div class="candidate-name">
                            <?php echo safe($candidate["name"] . " " . $candidate["surname"]); ?>
                        </div>
                        <?php if (($candidate["username"] ?? "") !== ""): ?>
                            <div class="candidate-username">@<?php echo safe($candidate["username"]); ?></div>
                        <?php endif; ?>
                        <div class="candidate-location">
                            <i data-lucide="map-pin"></i> <?php echo safe(
                                $candidate["location"] !== ""
                                    ? $candidate["location"]
                                    : (t("create_location_not_provided"))
                            ); ?>
                        </div>
                        <?php if ($candidateTypes): ?>
                            <div class="candidate-types">
                                <?php echo emContributionTags($candidate, $landInfoByUser[$candidateId] ?? null); ?>
                            </div>
                        <?php endif; ?>
                        <div class="candidate-stats">
                            <div class="candidate-stat">
                                <span><?php echo t("create_economic_value"); ?></span>
                                <strong><?php echo moneyValue($candidate["contribution_value"]); ?></strong>
                            </div>
                            <div class="candidate-stat">
                                <span><?php echo t("summary_minimum_share"); ?></span>
                                <strong><?php echo percentValue($candidate["minimum_share"]); ?></strong>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="candidate-empty" id="candidateEmpty" hidden>
                <i data-lucide="search-x"></i>
                <?php echo t("create_filter_no_results"); ?>
            </div>
            <div class="pagination" id="candidatePagination">
                <button type="button" id="candidatePrevious" class="pagination-arrow">
                    <i data-lucide="arrow-left"></i> <?php echo t("previous"); ?>
                </button>
                <div class="pagination-pages" id="candidatePages"></div>
                <button type="button" id="candidateNext" class="pagination-arrow">
                    <?php echo t("next"); ?> <i data-lucide="arrow-right"></i>
                </button>
            </div>
            <div class="manual-actions">
                <a href="matches.php" class="manual-btn secondary">
                    <i data-lucide="arrow-left"></i> <?php echo t("create_back"); ?>
                </a>
                <button type="submit" class="manual-btn primary" id="createCommunityButton" disabled>
                    <?php echo t("create_validate_create_community"); ?>
                </button>
            </div>
        <?php else: ?>
            <div class="error-box">
                <?php echo t("create_there_are_not_yet_4_available_users_with_a_valid_econom"); ?>
            </div>
            <div class="manual-actions">
                <a href="matches.php" class="manual-btn secondary">
                    <i data-lucide="arrow-left"></i> <?php echo t("back_to_matches"); ?>
                </a>
            </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const checkboxes = Array.from(
        document.querySelectorAll('input[name="selected_members[]"]')
    );
    const selectedCount = document.getElementById("selectedCount");
    const submitButton = document.getElementById("createCommunityButton");

    function updateSelection() {
        const checked = checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        });
        if (selectedCount) {
            selectedCount.textContent = checked.length;
        }
        checkboxes.forEach(function (checkbox) {
            const card = checkbox.closest(".candidate-card");
            if (card) {
                card.classList.toggle("selected", checkbox.checked);
            }
            checkbox.disabled =
                !checkbox.checked && checked.length >= 4;
        });
        if (submitButton) {
            submitButton.disabled = checked.length !== 4;
        }
        renderSelectedChips(checked);
    }

    /* "Selected: ..." line, visible on every page; ✕ removes a member */
    function renderSelectedChips(checked) {
        const chipsBox = document.getElementById("selectedChips");
        const chipsList = document.getElementById("selectedChipsList");
        if (!chipsBox || !chipsList) {
            return;
        }
        chipsList.innerHTML = "";
        checked.forEach(function (checkbox) {
            const card = checkbox.closest(".candidate-card");
            const chip = document.createElement("span");
            chip.className = "selected-chip";
            chip.textContent = card ? card.dataset.name : checkbox.value;
            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "selected-chip-remove";
            remove.setAttribute("aria-label", <?php echo json_encode(t("create_remove_member")); ?>);
            remove.textContent = "✕";
            remove.addEventListener("click", function () {
                checkbox.checked = false;
                checkbox.dispatchEvent(new Event("change"));
            });
            chip.appendChild(remove);
            chipsList.appendChild(chip);
        });
        chipsBox.hidden = checked.length === 0;
    }
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener("change", updateSelection);
    });
    updateSelection();

    /*
     * FILTERS: name, region and contribution type.
     * Several types = members with at least one of them.
     * Selected members are listed in the "Selected:" line, so they
     * stay visible even when the filter or the page hides their card.
     */
    const searchInput = document.getElementById("candidateSearch");
    const regionSelect = document.getElementById("candidateRegion");
    const chips = Array.from(document.querySelectorAll("[data-type-filter]"));
    const allChip = chips.find(function (chip) { return chip.dataset.typeFilter === ""; });
    const shownCount = document.getElementById("candidateShown");
    const emptyBox = document.getElementById("candidateEmpty");
    const resetButton = document.getElementById("candidateFilterReset");
    const cards = Array.from(document.querySelectorAll(".candidate-card"));

    if (!searchInput || !regionSelect || !allChip) {
        return;
    }

    function normalize(text) {
        return (text || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .trim();
    }

    function activeTypes() {
        return chips
            .filter(function (chip) { return chip.dataset.typeFilter !== "" && chip.classList.contains("is-active"); })
            .map(function (chip) { return chip.dataset.typeFilter; });
    }

    /* PAGES: 6 members per page, like the member list in Matches */
    const perPage = 6;
    let currentPage = 1;
    const paginationBox = document.getElementById("candidatePagination");
    const pagesBox = document.getElementById("candidatePages");
    const previousButton = document.getElementById("candidatePrevious");
    const nextButton = document.getElementById("candidateNext");

    function matchingCards() {
        const search = normalize(searchInput.value);
        const region = regionSelect.value;
        const types = activeTypes();
        return cards.filter(function (card) {
            const cardTypes = (card.dataset.types || "").split(",");
            return (search === "" || normalize(card.dataset.name).includes(search) || (search.replace(/^@/, "") !== "" && normalize(card.dataset.username).includes(search.replace(/^@/, ""))))
                && (region === "" || card.dataset.region === region)
                && (types.length === 0 || types.some(function (type) { return cardTypes.includes(type); }));
        });
    }

    function renderPage() {
        const matching = matchingCards();
        const totalPages = Math.max(1, Math.ceil(matching.length / perPage));
        currentPage = Math.min(Math.max(1, currentPage), totalPages);
        const first = (currentPage - 1) * perPage;
        const onPage = matching.slice(first, first + perPage);
        cards.forEach(function (card) {
            card.hidden = !onPage.includes(card);
        });
        shownCount.textContent = matching.length;
        emptyBox.hidden = matching.length !== 0;

        if (!paginationBox || !pagesBox) {
            return;
        }
        pagesBox.innerHTML = "";
        paginationBox.style.display = totalPages > 1 ? "flex" : "none";
        for (let page = 1; page <= totalPages; page++) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "pagination-number" + (page === currentPage ? " active-page" : "");
            button.textContent = page;
            button.addEventListener("click", function () {
                currentPage = page;
                renderPage();
            });
            pagesBox.appendChild(button);
        }
        previousButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages;
    }

    if (previousButton && nextButton) {
        previousButton.addEventListener("click", function () { currentPage--; renderPage(); });
        nextButton.addEventListener("click", function () { currentPage++; renderPage(); });
    }

    /* a filter change starts again from page 1 */
    function applyFilters() {
        const search = normalize(searchInput.value);
        const region = regionSelect.value;
        const types = activeTypes();
        currentPage = 1;
        renderPage();
        resetButton.hidden = search === "" && region === "" && types.length === 0;
    }

    chips.forEach(function (chip) {
        chip.addEventListener("click", function () {
            if (chip === allChip) {
                chips.forEach(function (other) { other.classList.remove("is-active"); });
            } else {
                chip.classList.toggle("is-active");
            }

            allChip.classList.toggle("is-active", activeTypes().length === 0);
            chips.forEach(function (other) {
                if (other !== allChip) {
                    other.setAttribute("aria-pressed", other.classList.contains("is-active") ? "true" : "false");
                }
            });
            applyFilters();
        });
    });

    searchInput.addEventListener("input", applyFilters);
    regionSelect.addEventListener("change", applyFilters);

    /* Enter in the search box must not submit the form */
    searchInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
            event.preventDefault();
        }
    });

    resetButton.addEventListener("click", function () {
        searchInput.value = "";
        regionSelect.value = "";
        allChip.click();
    });

    applyFilters();
});
</script>
<?php include "chatbot.php"; ?>

<script src="js/chatbot.js?v=<?php echo ASSET_VERSION; ?>"></script>
</body>
</html>
