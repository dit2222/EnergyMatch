-- =====================================================================
-- EnergyMatch - DEMO ACTIVITY on top of fresh_start.sql
-- =====================================================================
-- Run AFTER fresh_start.sql (phpMyAdmin -> energymatch -> Import -> Go).
-- Can be run again: it first clears communities, chat, notifications,
-- reports, contact messages and leave requests (users stay as they are).
--
-- What it creates:
--   2 ACTIVE communities (valid Fair Share groups) with group chat
--     #1 Ηλιακή Κοινότητα Αττικής      gpapad, mkonst, prigas, elazaridou, gandreatos
--     #2 Φωτοβολταϊκό Δυτικής Ελλάδας  egeorgiou, kmichailidou, sdimitrak, cvlachos, vtsakiris
--   2 PENDING proposals
--     #3 Ήλιος Πελοποννήσου  (3/5 accepted)  astamat, akaragianni, ipetraki | smanolakis, mzafeiriou
--     #4 Θεσσαλικός Ήλιος    (1/5 accepted)  nalexiou | akaragianni, smanolakis, mzafeiriou, echatzi
--   History: #5 cancelled (a member left), #6 rejected, #7 cancelled (conflict)
--   Notifications of every kind, 2 reports (1 approved = warning 1/5,
--   1 pending for the admin), 3 contact messages (new / read / answered).
-- =====================================================================
USE energymatch;
SET NAMES utf8mb4;

-- users by username (ids do not need to be known)
SET @gpapad       = (SELECT id FROM users WHERE username = 'gpapad');
SET @mkonst       = (SELECT id FROM users WHERE username = 'mkonst');
SET @nalexiou     = (SELECT id FROM users WHERE username = 'nalexiou');
SET @egeorgiou    = (SELECT id FROM users WHERE username = 'egeorgiou');
SET @dnikolaidis  = (SELECT id FROM users WHERE username = 'dnikolaidis');
SET @kmichailidou = (SELECT id FROM users WHERE username = 'kmichailidou');
SET @astamat      = (SELECT id FROM users WHERE username = 'astamat');
SET @sdimitrak    = (SELECT id FROM users WHERE username = 'sdimitrak');
SET @prigas       = (SELECT id FROM users WHERE username = 'prigas');
SET @akaragianni  = (SELECT id FROM users WHERE username = 'akaragianni');
SET @cvlachos     = (SELECT id FROM users WHERE username = 'cvlachos');
SET @ipetraki     = (SELECT id FROM users WHERE username = 'ipetraki');
SET @vtsakiris    = (SELECT id FROM users WHERE username = 'vtsakiris');
SET @elazaridou   = (SELECT id FROM users WHERE username = 'elazaridou');
SET @smanolakis   = (SELECT id FROM users WHERE username = 'smanolakis');
SET @aoikonomou   = (SELECT id FROM users WHERE username = 'aoikonomou');
SET @tkaramanlis  = (SELECT id FROM users WHERE username = 'tkaramanlis');
SET @mzafeiriou   = (SELECT id FROM users WHERE username = 'mzafeiriou');
SET @gandreatos   = (SELECT id FROM users WHERE username = 'gandreatos');
SET @echatzi      = (SELECT id FROM users WHERE username = 'echatzi');
SET @zpanag       = (SELECT id FROM users WHERE username = 'zpanag');
SET @melina       = (SELECT id FROM users WHERE username = 'melina');
SET @adamantia    = (SELECT id FROM users WHERE username = 'adamantia');

START TRANSACTION;
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM community_notifications;
DELETE FROM community_messages;
DELETE FROM community_leave_requests;
DELETE FROM user_reports;
DELETE FROM community_members;
DELETE FROM communities;
DELETE FROM contact_messages;
SET FOREIGN_KEY_CHECKS = 1;
ALTER TABLE communities AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------
-- communities
-- ---------------------------------------------------------------------
INSERT INTO communities (id, name, created_by, status, created_at) VALUES
 (1, 'Ηλιακή Κοινότητα Αττικής',     @gpapad,       'active',    NOW() - INTERVAL 9 DAY),
 (2, 'Φωτοβολταϊκό Δυτικής Ελλάδας', @egeorgiou,    'active',    NOW() - INTERVAL 6 DAY),
 (3, 'Ήλιος Πελοποννήσου',           @astamat,      'pending',   NOW() - INTERVAL 2 DAY),
 (4, 'Θεσσαλικός Ήλιος',             @nalexiou,     'pending',   NOW() - INTERVAL 5 HOUR),
 (5, 'Ηλιακό Πάρκο Ηπείρου',         @egeorgiou,    'cancelled', NOW() - INTERVAL 25 DAY),
 (6, 'Ενέργεια για Όλους',           @gpapad,       'rejected',  NOW() - INTERVAL 15 DAY),
 (7, 'Πράσινη Μακεδονία',            @kmichailidou, 'cancelled', NOW() - INTERVAL 8 DAY);

INSERT INTO community_members (community_id, user_id, invitation_status, responded_at) VALUES
 -- #1 active
 (1, @gpapad,     'accepted', NOW() - INTERVAL 9 DAY),
 (1, @mkonst,     'accepted', NOW() - INTERVAL 8 DAY),
 (1, @prigas,     'accepted', NOW() - INTERVAL 8 DAY),
 (1, @elazaridou, 'accepted', NOW() - INTERVAL 7 DAY),
 (1, @gandreatos, 'accepted', NOW() - INTERVAL 7 DAY),
 -- #2 active
 (2, @egeorgiou,    'accepted', NOW() - INTERVAL 6 DAY),
 (2, @kmichailidou, 'accepted', NOW() - INTERVAL 6 DAY),
 (2, @sdimitrak,    'accepted', NOW() - INTERVAL 5 DAY),
 (2, @cvlachos,     'accepted', NOW() - INTERVAL 5 DAY),
 (2, @vtsakiris,    'accepted', NOW() - INTERVAL 4 DAY),
 -- #3 pending, 3/5
 (3, @astamat,     'accepted', NOW() - INTERVAL 2 DAY),
 (3, @akaragianni, 'accepted', NOW() - INTERVAL 1 DAY),
 (3, @ipetraki,    'accepted', NOW() - INTERVAL 20 HOUR),
 (3, @smanolakis,  'pending',  NULL),
 (3, @mzafeiriou,  'pending',  NULL),
 -- #4 pending, 1/5
 (4, @nalexiou,    'accepted', NOW() - INTERVAL 5 HOUR),
 (4, @akaragianni, 'pending',  NULL),
 (4, @smanolakis,  'pending',  NULL),
 (4, @mzafeiriou,  'pending',  NULL),
 (4, @echatzi,     'pending',  NULL),
 -- #5 was active, ipetraki left
 (5, @egeorgiou, 'accepted', NOW() - INTERVAL 25 DAY),
 (5, @sdimitrak, 'accepted', NOW() - INTERVAL 24 DAY),
 (5, @ipetraki,  'left',     NOW() - INTERVAL 14 DAY),
 (5, @astamat,   'accepted', NOW() - INTERVAL 24 DAY),
 (5, @vtsakiris, 'accepted', NOW() - INTERVAL 23 DAY),
 -- #6 rejected by aoikonomou
 (6, @gpapad,      'accepted', NOW() - INTERVAL 15 DAY),
 (6, @prigas,      'accepted', NOW() - INTERVAL 14 DAY),
 (6, @mkonst,      'pending',  NULL),
 (6, @aoikonomou,  'rejected', NOW() - INTERVAL 13 DAY),
 (6, @tkaramanlis, 'pending',  NULL),
 -- #7 cancelled because kmichailidou joined #2
 (7, @kmichailidou, 'accepted', NOW() - INTERVAL 8 DAY),
 (7, @cvlachos,     'accepted', NOW() - INTERVAL 7 DAY),
 (7, @nalexiou,     'accepted', NOW() - INTERVAL 7 DAY),
 (7, @smanolakis,   'pending',  NULL),
 (7, @zpanag,       'pending',  NULL);

INSERT INTO community_leave_requests (community_id, user_id, reason, description, left_at) VALUES
 (5, @ipetraki, 'group_composition', 'Προτιμώ μια ομάδα πιο κοντά στην Κρήτη.', NOW() - INTERVAL 14 DAY);

-- ---------------------------------------------------------------------
-- group chat
-- ---------------------------------------------------------------------
INSERT INTO community_messages (community_id, user_id, message, created_at) VALUES
 (1, @gpapad,     'Καλησπέρα σε όλους! Χαίρομαι πολύ που η κοινότητα ενεργοποιήθηκε.', NOW() - INTERVAL 7 DAY + INTERVAL 2 HOUR),
 (1, @mkonst,     'Καλησπέρα! Μπορώ να ξεκινήσω τη μελέτη για το φωτοβολταϊκό από την επόμενη εβδομάδα.', NOW() - INTERVAL 7 DAY + INTERVAL 3 HOUR),
 (1, @prigas,     'Τέλεια. Εγώ αναλαμβάνω τις ηλεκτρολογικές συνδέσεις μόλις έχουμε τη μελέτη.', NOW() - INTERVAL 6 DAY),
 (1, @elazaridou, 'Να κανονίσουμε μια πρώτη συνάντηση; Εμένα με βολεύει Σάββατο πρωί.', NOW() - INTERVAL 4 DAY),
 (1, @gandreatos, 'Σάββατο είναι καλά και για μένα.', NOW() - INTERVAL 4 DAY + INTERVAL 40 MINUTE),
 (1, @gpapad,     'Ωραία, Σάββατο στις 11:00. Θα στείλω τη διεύθυνση εδώ.', NOW() - INTERVAL 3 HOUR),
 (2, @egeorgiou,    'Γεια σας! Καλώς ήρθατε στην κοινότητα.', NOW() - INTERVAL 4 DAY + INTERVAL 1 HOUR),
 (2, @cvlachos,     'Γεια! Έχω ήδη το inverter, οπότε μπορούμε να ξεκινήσουμε γρήγορα.', NOW() - INTERVAL 4 DAY + INTERVAL 2 HOUR),
 (2, @kmichailidou, 'Μπορώ να κάνω την εγκατάσταση των πάνελ μαζί με τον Χρήστο.', NOW() - INTERVAL 3 DAY),
 (2, @sdimitrak,    'Οι μπαταρίες είναι σε άριστη κατάσταση, μπορώ να τις φέρω όποτε χρειαστεί.', NOW() - INTERVAL 2 DAY),
 (2, @vtsakiris,    'Εγώ είμαι διαθέσιμος για τις χειρωνακτικές εργασίες από Δευτέρα.', NOW() - INTERVAL 1 HOUR);

-- ---------------------------------------------------------------------
-- reports: 1 approved (warning 1/5), 1 pending for the admin
-- ---------------------------------------------------------------------
INSERT INTO user_reports (id, community_id, reporter_user_id, reported_user_id, reason, description, status, created_at) VALUES
 (1, 1, @prigas,     @gandreatos, 'collaboration_problem',  'Δεν απαντά στα μηνύματα της ομάδας για αρκετές μέρες.', 'reviewed', NOW() - INTERVAL 3 DAY),
 (2, 1, @elazaridou, @gandreatos, 'inappropriate_behavior', 'Απότομες απαντήσεις στη συνομιλία.',                    'pending',  NOW() - INTERVAL 2 HOUR);

-- ---------------------------------------------------------------------
-- contact messages
-- ---------------------------------------------------------------------
INSERT INTO contact_messages (id, user_id, full_name, email, subject, message, status, admin_reply, replied_at, created_at) VALUES
 (1, NULL, 'Γιάννης Παπαδάκης', 'giannis@example.com', 'Κοινόχρηστο χωράφι', 'Μπορώ να δηλώσω χωράφι που έχω από κοινού με τον αδερφό μου;', 'new', NULL, NULL, NOW() - INTERVAL 1 HOUR),
 (2, NULL, 'Κατερίνα Λάμπρου', 'k.lamprou@example.com', 'Συνεργασία', 'Είμαστε σύλλογος και θα θέλαμε να μάθουμε περισσότερα για την πλατφόρμα.', 'read', NULL, NULL, NOW() - INTERVAL 2 DAY),
 (3, @nalexiou, 'Νίκος Αλεξίου', 'nalexiou@energymatch.test', 'Αλλαγή περιοχής', 'Πώς αλλάζω την περιοχή μου;', 'answered', 'Από το Προφίλ → Επεξεργασία Προφίλ, στο πεδίο «Τοποθεσία / Περιοχή».', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 4 DAY);

-- ---------------------------------------------------------------------
-- notifications
-- ---------------------------------------------------------------------
INSERT INTO community_notifications (community_id, contact_message_id, report_id, recipient_user_id, actor_user_id, notification_type, is_read, created_at) VALUES
 -- #1 acceptances and activation
 (1, NULL, NULL, @gpapad,     @mkonst,     'invitation_accepted', 1, NOW() - INTERVAL 8 DAY),
 (1, NULL, NULL, @gpapad,     @prigas,     'invitation_accepted', 1, NOW() - INTERVAL 8 DAY),
 (1, NULL, NULL, @gpapad,     @gandreatos, 'community_activated', 1, NOW() - INTERVAL 7 DAY),
 (1, NULL, NULL, @mkonst,     @gandreatos, 'community_activated', 1, NOW() - INTERVAL 7 DAY),
 (1, NULL, NULL, @prigas,     @gandreatos, 'community_activated', 1, NOW() - INTERVAL 7 DAY),
 (1, NULL, NULL, @elazaridou, @gandreatos, 'community_activated', 0, NOW() - INTERVAL 7 DAY),
 (1, NULL, NULL, @gandreatos, @gandreatos, 'community_activated', 1, NOW() - INTERVAL 7 DAY),
 -- #1 unread chat
 (1, NULL, NULL, @mkonst,     @gpapad, 'chat_message', 0, NOW() - INTERVAL 3 HOUR),
 (1, NULL, NULL, @elazaridou, @gpapad, 'chat_message', 0, NOW() - INTERVAL 3 HOUR),
 -- #1 report: warning to gandreatos, result to prigas
 (1, NULL, 1, @gandreatos, @melina, 'account_warning', 0, NOW() - INTERVAL 2 DAY),
 (1, NULL, 1, @prigas,     @melina, 'report_reviewed', 0, NOW() - INTERVAL 2 DAY),
 -- #2 acceptances and activation
 (2, NULL, NULL, @egeorgiou,    @kmichailidou, 'invitation_accepted', 1, NOW() - INTERVAL 6 DAY),
 (2, NULL, NULL, @egeorgiou,    @vtsakiris,    'community_activated', 1, NOW() - INTERVAL 4 DAY),
 (2, NULL, NULL, @kmichailidou, @vtsakiris,    'community_activated', 1, NOW() - INTERVAL 4 DAY),
 (2, NULL, NULL, @sdimitrak,    @vtsakiris,    'community_activated', 0, NOW() - INTERVAL 4 DAY),
 (2, NULL, NULL, @cvlachos,     @vtsakiris,    'community_activated', 1, NOW() - INTERVAL 4 DAY),
 (2, NULL, NULL, @vtsakiris,    @vtsakiris,    'community_activated', 1, NOW() - INTERVAL 4 DAY),
 -- #2 unread chat
 (2, NULL, NULL, @egeorgiou,    @vtsakiris, 'chat_message', 0, NOW() - INTERVAL 1 HOUR),
 (2, NULL, NULL, @cvlachos,     @vtsakiris, 'chat_message', 0, NOW() - INTERVAL 1 HOUR),
 -- #3 acceptances
 (3, NULL, NULL, @astamat,     @akaragianni, 'invitation_accepted', 1, NOW() - INTERVAL 1 DAY),
 (3, NULL, NULL, @astamat,     @ipetraki,    'invitation_accepted', 0, NOW() - INTERVAL 20 HOUR),
 (3, NULL, NULL, @akaragianni, @ipetraki,    'invitation_accepted', 0, NOW() - INTERVAL 20 HOUR),
 -- #5 a member left
 (5, NULL, NULL, @egeorgiou, @ipetraki, 'member_left', 1, NOW() - INTERVAL 14 DAY),
 (5, NULL, NULL, @sdimitrak, @ipetraki, 'member_left', 1, NOW() - INTERVAL 14 DAY),
 (5, NULL, NULL, @astamat,   @ipetraki, 'member_left', 1, NOW() - INTERVAL 14 DAY),
 (5, NULL, NULL, @vtsakiris, @ipetraki, 'member_left', 1, NOW() - INTERVAL 14 DAY),
 -- #6 rejected
 (6, NULL, NULL, @gpapad,      @aoikonomou, 'invitation_rejected', 1, NOW() - INTERVAL 13 DAY),
 (6, NULL, NULL, @prigas,      @aoikonomou, 'invitation_rejected', 1, NOW() - INTERVAL 13 DAY),
 (6, NULL, NULL, @mkonst,      @aoikonomou, 'invitation_rejected', 1, NOW() - INTERVAL 13 DAY),
 (6, NULL, NULL, @tkaramanlis, @aoikonomou, 'invitation_rejected', 0, NOW() - INTERVAL 13 DAY),
 -- #7 cancelled: kmichailidou and cvlachos joined #2
 (7, NULL, NULL, @nalexiou,   @kmichailidou, 'community_cancelled_conflict', 1, NOW() - INTERVAL 6 DAY),
 (7, NULL, NULL, @smanolakis, @kmichailidou, 'community_cancelled_conflict', 0, NOW() - INTERVAL 6 DAY),
 (7, NULL, NULL, @zpanag,     @kmichailidou, 'community_cancelled_conflict', 0, NOW() - INTERVAL 6 DAY),
 -- contact message answered
 (NULL, 3, NULL, @nalexiou, @adamantia, 'contact_answered', 0, NOW() - INTERVAL 3 DAY);

COMMIT;
