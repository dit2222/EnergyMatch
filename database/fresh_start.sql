-- =====================================================================
-- EnergyMatch - FRESH START: empty database + 2 admins + 25 users
-- =====================================================================
-- WARNING: DELETES ALL DATA (users, communities, messages, reports...).
--          The tables themselves stay. Export a backup first!
-- Run AFTER all migrations (auth_tokens, cleanup_columns, notifications,
-- login_attempts, moderation, account_deleted, farewell).
-- phpMyAdmin -> energymatch -> SQL -> paste -> Go.
--
-- Admins:  melina / Admin1234!     adamantia / Admin1234!
-- Users:   see the list below, password Test1234! for everyone
-- Change the passwords in Settings after the first sign-in.
-- =====================================================================

USE energymatch;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `community_notifications`;
TRUNCATE TABLE `community_messages`;
TRUNCATE TABLE `community_leave_requests`;
TRUNCATE TABLE `user_reports`;
TRUNCATE TABLE `community_members`;
TRUNCATE TABLE `communities`;
TRUNCATE TABLE `user_contributions`;
TRUNCATE TABLE `energy_profiles`;
TRUNCATE TABLE `contact_messages`;
TRUNCATE TABLE `remember_tokens`;
TRUNCATE TABLE `password_resets`;
TRUNCATE TABLE `login_attempts`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- ADMINS
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Μελίνα', 'Βανδώρου', 'Πολυτεχνειούπολη', 'Κρήτη', 'Χανιά', '73100', '6900000001', 'melina@energymatch.test', 'melina', '$2y$10$l8McdlIAiOQqPGFiWOvT2.4b0JXuQN2DVkhYJh/UjZNl7M8phYuvO', 'admin');
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Αδαμαντία', 'Κασκαντάμη', 'Πολυτεχνειούπολη', 'Κρήτη', 'Χανιά', '73100', '6900000002', 'adamantia@energymatch.test', 'adamantia', '$2y$10$l8McdlIAiOQqPGFiWOvT2.4b0JXuQN2DVkhYJh/UjZNl7M8phYuvO', 'admin');

-- gpapad: Γιώργος Παπαδόπουλος
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Γιώργος', 'Παπαδόπουλος', 'Ερμού 45', 'Αττική', 'Αθήνα', '10563', '6971234501', 'gpapad@energymatch.test', 'gpapad', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Αττική', 'Δημόσιος υπάλληλος, θέλω να βάλω ένα μέρος από τις οικονομίες μου.', 12.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 4000.00, 4000.00, 'Direct monetary contribution');

-- mkonst: Μαρία Κωνσταντίνου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Μαρία', 'Κωνσταντίνου', 'Τσιμισκή 112', 'Κεντρική Μακεδονία', 'Θεσσαλονίκη', '54622', '6971234502', 'mkonst@energymatch.test', 'mkonst', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Κεντρική Μακεδονία', 'Ηλεκτρολόγος μηχανικός, μπορώ να αναλάβω μελέτη και επίβλεψη.', 10.00);
INSERT INTO user_contributions (user_id, contribution_type, specialty, skilled_hours, estimated_value, valuation_method) VALUES (@u, 'skilled_labor', 'Engineer', 180.00, 2187.00, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC2 Professionals');

-- nalexiou: Νίκος Αλεξίου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Νίκος', 'Αλεξίου', 'Ηρώων Πολυτεχνείου 8', 'Θεσσαλία', 'Λάρισα', '41222', '6971234503', 'nalexiou@energymatch.test', 'nalexiou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Θεσσαλία', 'Αγρότης με χωράφι στον κάμπο, αρκετή ηλιοφάνεια.', 18.00);
INSERT INTO user_contributions (user_id, contribution_type, land_area, land_location, latitude, longitude, solar_irradiation, optimal_slope, optimal_azimuth, solar_score, location_factor, estimated_value, valuation_method) VALUES (@u, 'land', 10.00, 'Τερψιθέα, Λάρισα', 39.665, 22.355, 1925.00, 33.00, 0.00, 78.13, 0.9833, 13344.36, 'Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)');

-- egeorgiou: Ελένη Γεωργίου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Ελένη', 'Γεωργίου', 'Κορίνθου 230', 'Δυτική Ελλάδα', 'Πάτρα', '26223', '6971234504', 'egeorgiou@energymatch.test', 'egeorgiou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Δυτική Ελλάδα', 'Μπορώ να δουλέψω στην εγκατάσταση και να βάλω ένα μικρό ποσό.', 8.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 1500.00, 1500.00, 'Direct monetary contribution');
INSERT INTO user_contributions (user_id, contribution_type, manual_hours, estimated_value, valuation_method) VALUES (@u, 'manual_labor', 250.00, 1740.00, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC9 Elementary occupations');

-- dnikolaidis: Δημήτρης Νικολαΐδης
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Δημήτρης', 'Νικολαΐδης', 'Ικάρου 14', 'Κρήτη', 'Ηράκλειο', '71307', '6971234505', 'dnikolaidis@energymatch.test', 'dnikolaidis', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Κρήτη', 'Έχω πάνελ από παλιά εγκατάσταση στο σπίτι.', 15.00);
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'pv_panels', 16, 400.00, 'W', 3.00, 'good', NULL, 608.26, 'sun.store PV Index, September 2026 - TOPCon monofacial modules + straight-line age factor (service life 25 years, minimum 20%) + EnergyMatch condition factor');

-- kmichailidou: Κατερίνα Μιχαηλίδου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Κατερίνα', 'Μιχαηλίδου', 'Παπάφη 40', 'Κεντρική Μακεδονία', 'Θεσσαλονίκη', '54638', '6971234506', 'kmichailidou@energymatch.test', 'kmichailidou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Κεντρική Μακεδονία', 'Τεχνικός φωτοβολταϊκών με 10 χρόνια εμπειρία.', 10.00);
INSERT INTO user_contributions (user_id, contribution_type, specialty, skilled_hours, estimated_value, valuation_method) VALUES (@u, 'skilled_labor', 'PV Technician', 300.00, 3105.00, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC3 Technicians and associate professionals');
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'electrical_equipment', 1, 6.00, 'kW', 2.00, 'excellent', NULL, 454.37, 'sun.store PV Index, September 2026 - hybrid inverters 1-20 kW (EnergyMatch assumption: used for all electrical equipment) + straight-line age factor (service life 10 years, minimum 20%) + EnergyMatch condition factor');

-- astamat: Αντώνης Σταματόπουλος
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Αντώνης', 'Σταματόπουλος', 'Αριστομένους 55', 'Πελοπόννησος', 'Καλαμάτα', '24100', '6971234507', 'astamat@energymatch.test', 'astamat', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Πελοπόννησος', 'Οικόπεδο κοντά στην πόλη και λίγα χρήματα.', 14.00);
INSERT INTO user_contributions (user_id, contribution_type, land_area, land_location, latitude, longitude, solar_irradiation, optimal_slope, optimal_azimuth, solar_score, location_factor, estimated_value, valuation_method) VALUES (@u, 'land', 4.00, 'Ασπρόχωμα, Μεσσηνία', 37.059, 22.056, 1985.00, 31.00, 0.00, 85.63, 1.0139, 5503.85, 'Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)');
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 1000.00, 1000.00, 'Direct monetary contribution');

-- sdimitrak: Σοφία Δημητρακοπούλου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Σοφία', 'Δημητρακοπούλου', 'Πανεπιστημίου 30', 'Ήπειρος', 'Ιωάννινα', '45221', '6971234508', 'sdimitrak@energymatch.test', 'sdimitrak', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Ήπειρος', 'Έχω δύο μπαταρίες αποθήκευσης που δεν χρησιμοποιώ.', 9.00);
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'battery_storage', 2, 10.00, 'kWh', 2.00, 'excellent', 'lfp', 3169.60, 'sun.store Battery Index, August 2026 - residential high-voltage performance + straight-line age factor (service life 10 years, minimum 20%) + EnergyMatch condition factor');

-- prigas: Παναγιώτης Ρήγας
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Παναγιώτης', 'Ρήγας', 'Δημητριάδος 120', 'Θεσσαλία', 'Βόλος', '38221', '6971234509', 'prigas@energymatch.test', 'prigas', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Θεσσαλία', 'Ηλεκτρολόγος, μπορώ να κάνω τις συνδέσεις.', 11.00);
INSERT INTO user_contributions (user_id, contribution_type, specialty, skilled_hours, estimated_value, valuation_method) VALUES (@u, 'skilled_labor', 'Electrician', 220.00, 2004.20, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC7 Craft and related trades workers');
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 800.00, 800.00, 'Direct monetary contribution');

-- akaragianni: Αθηνά Καραγιάννη
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Αθηνά', 'Καραγιάννη', 'Λεωφ. Κηφισίας 210', 'Αττική', 'Μαρούσι', '15124', '6971234510', 'akaragianni@energymatch.test', 'akaragianni', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Αττική', 'Θέλω να συμμετάσχω με κεφάλαιο.', 13.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 6000.00, 6000.00, 'Direct monetary contribution');

-- cvlachos: Χρήστος Βλάχος
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Χρήστος', 'Βλάχος', '25ης Μαρτίου 18', 'Στερεά Ελλάδα', 'Λαμία', '35100', '6971234511', 'cvlachos@energymatch.test', 'cvlachos', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Στερεά Ελλάδα', 'Εγκαταστάτης, διαθέτω και inverter.', 10.00);
INSERT INTO user_contributions (user_id, contribution_type, specialty, skilled_hours, estimated_value, valuation_method) VALUES (@u, 'skilled_labor', 'Installer', 350.00, 3188.50, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC7 Craft and related trades workers');
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'electrical_equipment', 1, 10.00, 'kW', 4.00, 'good', NULL, 511.16, 'sun.store PV Index, September 2026 - hybrid inverters 1-20 kW (EnergyMatch assumption: used for all electrical equipment) + straight-line age factor (service life 10 years, minimum 20%) + EnergyMatch condition factor');

-- ipetraki: Ιωάννα Πετράκη
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Ιωάννα', 'Πετράκη', 'Χάληδων 22', 'Κρήτη', 'Χανιά', '73131', '6971234512', 'ipetraki@energymatch.test', 'ipetraki', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Κρήτη', 'Μικρό αγροτεμάχιο με εξαιρετική ηλιοφάνεια και μερικά πάνελ.', 16.00);
INSERT INTO user_contributions (user_id, contribution_type, land_area, land_location, latitude, longitude, solar_irradiation, optimal_slope, optimal_azimuth, solar_score, location_factor, estimated_value, valuation_method) VALUES (@u, 'land', 3.00, 'Σούδα, Χανιά', 35.487, 24.073, 2045.00, 30.00, 0.00, 93.13, 1.0445, 4252.47, 'Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)');
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'pv_panels', 8, 410.00, 'W', 5.00, 'good', NULL, 283.39, 'sun.store PV Index, September 2026 - TOPCon monofacial modules + straight-line age factor (service life 25 years, minimum 20%) + EnergyMatch condition factor');

-- vtsakiris: Βασίλης Τσακίρης
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Βασίλης', 'Τσακίρης', 'Μεγάλου Αλεξάνδρου 9', 'Δυτική Μακεδονία', 'Κοζάνη', '50100', '6971234513', 'vtsakiris@energymatch.test', 'vtsakiris', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Δυτική Μακεδονία', 'Συνταξιούχος, μπορώ να βοηθήσω με χειρωνακτική εργασία.', 7.00);
INSERT INTO user_contributions (user_id, contribution_type, manual_hours, estimated_value, valuation_method) VALUES (@u, 'manual_labor', 400.00, 2784.00, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC9 Elementary occupations');

-- elazaridou: Ευαγγελία Λαζαρίδου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Ευαγγελία', 'Λαζαρίδου', 'Μεραρχίας 33', 'Κεντρική Μακεδονία', 'Σέρρες', '62123', '6971234514', 'elazaridou@energymatch.test', 'elazaridou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Κεντρική Μακεδονία', 'Λίγα χρήματα και λίγες ώρες εργασίας.', 12.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 2000.00, 2000.00, 'Direct monetary contribution');
INSERT INTO user_contributions (user_id, contribution_type, manual_hours, estimated_value, valuation_method) VALUES (@u, 'manual_labor', 100.00, 696.00, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC9 Elementary occupations');

-- smanolakis: Στέλιος Μανωλάκης
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Στέλιος', 'Μανωλάκης', 'Αυστραλίας 7', 'Νότιο Αιγαίο', 'Ρόδος', '85100', '6971234515', 'smanolakis@energymatch.test', 'smanolakis', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Νότιο Αιγαίο', 'Οικόπεδο στη Ρόδο, πολύς ήλιος όλο τον χρόνο.', 14.00);
INSERT INTO user_contributions (user_id, contribution_type, land_area, land_location, latitude, longitude, solar_irradiation, optimal_slope, optimal_azimuth, solar_score, location_factor, estimated_value, valuation_method) VALUES (@u, 'land', 5.00, 'Καλυθιές, Ρόδος', 36.354, 28.173, 2095.00, 30.00, 0.00, 99.38, 1.0701, 7261.16, 'Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)');

-- aoikonomou: Αλέξανδρος Οικονόμου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Αλέξανδρος', 'Οικονόμου', 'Βασ. Σοφίας 75', 'Αττική', 'Αθήνα', '11521', '6971234516', 'aoikonomou@energymatch.test', 'aoikonomou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Αττική', 'ΑΚΡΑΙΟ: πολύ μεγάλο κεφάλαιο, ζητάει μεγάλο ποσοστό.', 60.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 250000.00, 250000.00, 'Direct monetary contribution');

-- tkaramanlis: Θεόδωρος Καραμανλής
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Θεόδωρος', 'Καραμανλής', 'Αγίου Δημητρίου 3', 'Θεσσαλία', 'Φάρσαλα', '40300', '6971234517', 'tkaramanlis@energymatch.test', 'tkaramanlis', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Θεσσαλία', 'ΑΚΡΑΙΟ: τεράστια έκταση γης.', 50.00);
INSERT INTO user_contributions (user_id, contribution_type, land_area, land_location, latitude, longitude, solar_irradiation, optimal_slope, optimal_azimuth, solar_score, location_factor, estimated_value, valuation_method) VALUES (@u, 'land', 120.00, 'Φάρσαλα, Λάρισα', 39.295, 22.383, 1905.00, 33.00, 0.00, 75.63, 0.9730, 158455.00, 'Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)');

-- mzafeiriou: Μιχάλης Ζαφειρίου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Μιχάλης', 'Ζαφειρίου', 'Κανάρη 5', 'Θεσσαλία', 'Τρίκαλα', '42100', '6971234518', 'mzafeiriou@energymatch.test', 'mzafeiriou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Θεσσαλία', 'ΑΚΡΑΙΟ: πολύ μικρή συνεισφορά, δέχεται οποιοδήποτε ποσοστό.', 0.00);
INSERT INTO user_contributions (user_id, contribution_type, manual_hours, estimated_value, valuation_method) VALUES (@u, 'manual_labor', 15.00, 104.40, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC9 Elementary occupations');

-- dathanasiou: Δέσποινα Αθανασίου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Δέσποινα', 'Αθανασίου', 'Ελ. Βενιζέλου 60', 'Ανατολική Μακεδονία και Θράκη', 'Καβάλα', '65302', '6971234519', 'dathanasiou@energymatch.test', 'dathanasiou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Ανατολική Μακεδονία και Θράκη', 'ΑΚΡΑΙΟ: ζητάει 100%, δεν μπορεί να μπει σε καμία ομάδα.', 100.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 5000.00, 5000.00, 'Direct monetary contribution');

-- gandreatos: Γεράσιμος Ανδρεάτος
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Γεράσιμος', 'Ανδρεάτος', 'Λιθόστρωτο 12', 'Ιόνια Νησιά', 'Κέρκυρα', '49100', '6971234520', 'gandreatos@energymatch.test', 'gandreatos', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Ιόνια Νησιά', 'ΟΡΙΑΚΟ: ελάχιστο 21%, κοντά στο ταβάνι του.', 21.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 3500.00, 3500.00, 'Direct monetary contribution');
INSERT INTO user_contributions (user_id, contribution_type, specialty, skilled_hours, estimated_value, valuation_method) VALUES (@u, 'skilled_labor', 'Other', 60.00, 546.60, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC7 Craft and related trades workers');

-- echatzi: Ειρήνη Χατζηγεωργίου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Ειρήνη', 'Χατζηγεωργίου', 'Καραολή 4', 'Δυτική Μακεδονία', 'Φλώρινα', '53100', '6971234521', 'echatzi@energymatch.test', 'echatzi', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Δυτική Μακεδονία', 'ΑΚΡΑΙΟ: χωράφι με χαμηλή ηλιοφάνεια (βόρεια).', 10.00);
INSERT INTO user_contributions (user_id, contribution_type, land_area, land_location, latitude, longitude, solar_irradiation, optimal_slope, optimal_azimuth, solar_score, location_factor, estimated_value, valuation_method) VALUES (@u, 'land', 15.00, 'Φλώρινα', 40.782, 21.41, 1700.00, 35.00, 0.00, 50.00, 0.8683, 17675.55, 'Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)');

-- skalogerakis: Σπύρος Καλογεράκης
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Σπύρος', 'Καλογεράκης', 'Ευαγγελιστρίας 19', 'Κρήτη', 'Ρέθυμνο', '74100', '6971234522', 'skalogerakis@energymatch.test', 'skalogerakis', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Κρήτη', 'ΑΚΡΑΙΟ: παλιός εξοπλισμός σε κακή κατάσταση (πάνω από τη διάρκεια ζωής).', 12.00);
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'pv_panels', 30, 250.00, 'W', 28.00, 'poor', NULL, 90.00, 'sun.store PV Index, September 2026 - TOPCon monofacial modules + straight-line age factor (service life 25 years, minimum 20%) + EnergyMatch condition factor');
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'battery_storage', 1, 5.00, 'kWh', 12.00, 'poor', 'lead_acid', 99.05, 'sun.store Battery Index, August 2026 - residential high-voltage performance + straight-line age factor (service life 10 years, minimum 20%) + EnergyMatch condition factor');

-- zpanag: Ζωή Παναγιωτοπούλου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Ζωή', 'Παναγιωτοπούλου', 'Αγίου Νικολάου 50', 'Δυτική Ελλάδα', 'Πάτρα', '26221', '6971234523', 'zpanag@energymatch.test', 'zpanag', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Δυτική Ελλάδα', 'ΑΚΡΑΙΟ: όλοι οι τύποι συνεισφοράς μαζί, μέγιστες ώρες εργασίας.', 15.00);
INSERT INTO user_contributions (user_id, contribution_type, capital_amount, estimated_value, valuation_method) VALUES (@u, 'capital', 3000.00, 3000.00, 'Direct monetary contribution');
INSERT INTO user_contributions (user_id, contribution_type, land_area, land_location, latitude, longitude, solar_irradiation, optimal_slope, optimal_azimuth, solar_score, location_factor, estimated_value, valuation_method) VALUES (@u, 'land', 2.00, 'Ρίο, Αχαΐα', 38.295, 21.79, 1950.00, 32.00, 0.00, 81.25, 0.9960, 2703.34, 'Eurostat national average land price / 10 x stremmata x location factor f (PVGIS)');
INSERT INTO user_contributions (user_id, contribution_type, manual_hours, estimated_value, valuation_method) VALUES (@u, 'manual_labor', 1000.00, 6960.00, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC9 Elementary occupations');
INSERT INTO user_contributions (user_id, contribution_type, specialty, skilled_hours, estimated_value, valuation_method) VALUES (@u, 'skilled_labor', 'Engineer', 1000.00, 12150.00, 'Eurostat SES 2022 Greece - mean hourly earnings, ISCO OC2 Professionals');
INSERT INTO user_contributions (user_id, contribution_type, equipment_type, equipment_quantity, equipment_capacity, equipment_capacity_unit, equipment_age, equipment_condition, battery_chemistry, estimated_value, valuation_method) VALUES (@u, 'equipment', 'pv_panels', 10, 450.00, 'W', 1.00, 'excellent', NULL, 518.40, 'sun.store PV Index, September 2026 - TOPCon monofacial modules + straight-line age factor (service life 25 years, minimum 20%) + EnergyMatch condition factor');

-- lmavromatis: Λευτέρης Μαυρομάτης
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Λευτέρης', 'Μαυρομάτης', 'Ομονοίας 2', 'Ανατολική Μακεδονία και Θράκη', 'Αλεξανδρούπολη', '68100', '6971234524', 'lmavromatis@energymatch.test', 'lmavromatis', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();
INSERT INTO energy_profiles (user_id, location, description, minimum_share) VALUES (@u, 'Ανατολική Μακεδονία και Θράκη', 'Έφτιαξε προφίλ αλλά δεν δήλωσε συνεισφορά.', 10.00);

-- asidiropoulou: Άννα Σιδηροπούλου
INSERT INTO users (name, surname, address, region, city, postal_code, phone, email, username, password, role) VALUES ('Άννα', 'Σιδηροπούλου', 'Μητροπόλεως 15', 'Κεντρική Μακεδονία', 'Θεσσαλονίκη', '54624', '6971234525', 'asidiropoulou@energymatch.test', 'asidiropoulou', '$2y$10$4K73RM63nC94eRZqTXYCN.Hsb3CH6l5abpPht8NV3GJAqPLfXQbya', 'user');
SET @u = LAST_INSERT_ID();

COMMIT;
