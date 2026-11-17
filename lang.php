<?php

/*

|--------------------------------------------------------------------------

| ENERGY MATCH - LANGUAGE SYSTEM

|--------------------------------------------------------------------------

*/

/*
 * The chosen language is also kept in a cookie (set_language.php),
 * so it is remembered after logout or when the browser is closed.
 */
if (!isset($_SESSION["language"])) {

    $rememberedLanguage = $_COOKIE["em_language"] ?? "";

    $_SESSION["language"] = in_array($rememberedLanguage, ["en", "gr"], true)
        ? $rememberedLanguage
        : "en";

}

$allowed_languages = ["en", "gr"];

if (!in_array($_SESSION["language"], $allowed_languages, true)) {

    $_SESSION["language"] = "en";

}

$translations = [

    /* NAVIGATION */

    "dashboard" => ["en" => "Dashboard", "gr" => "Αρχική"],

    "profile" => ["en" => "Profile", "gr" => "Προφίλ"],

    "matches" => ["en" => "Matches", "gr" => "Αντιστοιχίσεις"],

    "settings" => ["en" => "Settings", "gr" => "Ρυθμίσεις"],

    "logout" => ["en" => "Logout", "gr" => "Αποσύνδεση"],

    /* DASHBOARD */

    "welcome" => ["en" => "Welcome", "gr" => "Καλώς ήρθες"],

    "welcome_text" => [

        "en" => "Find your ideal energy community partners and build sustainable projects together.",

        "gr" => "Βρες τους ιδανικούς συνεργάτες για την ενεργειακή σου κοινότητα και δημιούργησε βιώσιμα έργα μαζί τους."

    ],

    "view_profile" => ["en" => "View Profile", "gr" => "Προβολή Προφίλ"],

    "find_matches" => ["en" => "Find Matches", "gr" => "Εύρεση Αντιστοιχίσεων"],

    "community" => ["en" => "Community", "gr" => "Κοινότητα"],

        /* PROFILE PAGE */

    "profile_page_title" => [

        "en" => "Build Your Energy Profile",

        "gr" => "Δημιούργησε το Ενεργειακό σου Προφίλ"

    ],

    "profile_page_subtitle" => [

        "en" => "Tell EnergyMatch what you can contribute to an energy community. Your contributions will be economically evaluated to create fair and compatible communities.",

        "gr" => "Δήλωσε τι μπορείς να συνεισφέρεις σε μια ενεργειακή κοινότητα. Οι συνεισφορές σου θα αποτιμηθούν οικονομικά ώστε να δημιουργούνται δίκαιες και συμβατές κοινότητες."

    ],

    "profile_updated" => [

        "en" => "Profile updated!",

        "gr" => "Το προφίλ ενημερώθηκε!"

    ],

    "profile_saved_info" => [

        "en" => "Your contribution information has been saved successfully.",

        "gr" => "Οι πληροφορίες συνεισφοράς αποθηκεύτηκαν επιτυχώς."

    ],

    "profile_cleared" => [

        "en" => "Profile cleared.",

        "gr" => "Το προφίλ καθαρίστηκε."

    ],

    "profile_cleared_info" => [

        "en" => "You can now create your contribution profile again.",

        "gr" => "Μπορείς τώρα να δημιουργήσεις ξανά το προφίλ συνεισφοράς σου."

    ],

    "what_can_contribute" => [

        "en" => "What Can You Contribute?",

        "gr" => "Τι Μπορείς να Συνεισφέρεις;"

    ],

    "contribution_description" => [

        "en" => "Select one or more types of contribution. You can combine money, land, work, expertise and equipment.",

        "gr" => "Επίλεξε μία ή περισσότερες μορφές συνεισφοράς. Μπορείς να συνδυάσεις χρήματα, γη, εργασία, τεχνογνωσία και εξοπλισμό."

    ],

    "capital_money" => [

        "en" => "Capital / Money",

        "gr" => "Κεφάλαιο / Χρήματα"

    ],

    "land_space" => [

        "en" => "Land / Space",

        "gr" => "Γη / Χώρος"

    ],

    "manual_labor" => [

        "en" => "Manual Labor",

        "gr" => "Χειρωνακτική Εργασία"

    ],

    "skilled_labor" => [

        "en" => "Skilled Labor / Expertise",

        "gr" => "Εξειδικευμένη Εργασία / Τεχνογνωσία"

    ],

    "equipment_infrastructure" => [

        "en" => "Equipment / Infrastructure",

        "gr" => "Εξοπλισμός / Υποδομή"

    ],

    "capital_contribution" => [

        "en" => "Capital Contribution",

        "gr" => "Χρηματική Συνεισφορά"

    ],

    "capital_contribution_description" => [

        "en" => "Enter the amount of money you are willing to contribute.",

        "gr" => "Δήλωσε το ποσό χρημάτων που είσαι διατεθειμένος να συνεισφέρεις."

    ],

    "available_capital" => [

        "en" => "Available Capital (€)",

        "gr" => "Διαθέσιμο Κεφάλαιο (€)"

    ],

    "land_contribution" => [

        "en" => "Land / Space Contribution",

        "gr" => "Συνεισφορά Γης / Χώρου"

    ],

    "land_contribution_description" => [

        "en" => "Provide information about the land or space you can make available.",

        "gr" => "Δώσε πληροφορίες για τη γη ή τον χώρο που μπορείς να διαθέσεις."

    ],

    "land_area" => [

        "en" => "Area (stremmata)",

        "gr" => "Έκταση (στρέμματα)"

    ],

    "land_location" => [

        "en" => "Land Location",

        "gr" => "Τοποθεσία Γης"

    ],

    "land_location_placeholder" => [

        "en" => "e.g. Nemea, Corinthia",

        "gr" => "π.χ. Νεμέα, Κορινθία"

    ],

    "latitude" => [

        "en" => "Latitude",

        "gr" => "Γεωγραφικό Πλάτος"

    ],

    "longitude" => [

        "en" => "Longitude",

        "gr" => "Γεωγραφικό Μήκος"

    ],

    "manual_labor_description" => [

        "en" => "Enter the total number of work hours you are willing to contribute to the project.",

        "gr" => "Δήλωσε τις συνολικές ώρες εργασίας που είσαι διατεθειμένος να προσφέρεις στο έργο."

    ],

    "manual_hours" => [

        "en" => "Committed Work Hours",

        "gr" => "Ώρες Εργασίας που Προσφέρεις"

    ],

    "skilled_labor_description" => [

        "en" => "Describe the professional expertise you can contribute.",

        "gr" => "Δήλωσε την επαγγελματική τεχνογνωσία που μπορείς να προσφέρεις."

    ],

    "specialty" => [

        "en" => "Specialty",

        "gr" => "Ειδικότητα"

    ],

    "select_specialty" => [

        "en" => "Select specialty",

        "gr" => "Επίλεξε ειδικότητα"

    ],

    "electrician" => [

        "en" => "Electrician",

        "gr" => "Ηλεκτρολόγος"

    ],

    "pv_technician" => [

        "en" => "Photovoltaic Technician",

        "gr" => "Τεχνικός Φωτοβολταϊκών"

    ],

    "engineer" => [

        "en" => "Engineer",

        "gr" => "Μηχανικός"

    ],

    "installer" => [

        "en" => "Installer / Technical Specialist",

        "gr" => "Εγκαταστάτης / Τεχνικός"

    ],

    "other_specialty" => [

        "en" => "Other",

        "gr" => "Άλλη ειδικότητα"

    ],

    "skilled_hours" => [

        "en" => "Committed Skilled Work Hours",

        "gr" => "Ώρες Εξειδικευμένης Εργασίας"

    ],

    "equipment_contribution_description" => [

        "en" => "Enter the technical characteristics of the equipment you can contribute.",

        "gr" => "Δήλωσε τα τεχνικά χαρακτηριστικά του εξοπλισμού που μπορείς να συνεισφέρεις."

    ],

    "equipment_type" => [

        "en" => "Equipment Type",

        "gr" => "Τύπος Εξοπλισμού"

    ],

    "select_equipment" => [

        "en" => "Select equipment",

        "gr" => "Επίλεξε εξοπλισμό"

    ],

    "pv_panels" => [

        "en" => "PV Panels",

        "gr" => "Φωτοβολταϊκά Πάνελ"

    ],

    "inverter" => [

        "en" => "Inverter",

        "gr" => "Inverter"

    ],

    "battery_storage" => [

        "en" => "Storage Batteries",

        "gr" => "Μπαταρίες Αποθήκευσης"

    ],

    "mounting_system" => [

        "en" => "Mounting System",

        "gr" => "Σύστημα Στήριξης"

    ],

    "electrical_equipment" => [

        "en" => "Electrical Equipment",

        "gr" => "Ηλεκτρολογικός Εξοπλισμός"

    ],

    "quantity" => [

        "en" => "Quantity",

        "gr" => "Ποσότητα"

    ],

    "equipment_capacity" => [

        "en" => "Capacity / Power",

        "gr" => "Ισχύς / Χωρητικότητα"

    ],

    "unit" => [

        "en" => "Unit",

        "gr" => "Μονάδα"

    ],

    "equipment_age" => [

        "en" => "Equipment Age (years)",

        "gr" => "Ηλικία Εξοπλισμού (έτη)"

    ],

    "condition" => [

        "en" => "Condition",

        "gr" => "Κατάσταση"

    ],

    "select_condition" => [

        "en" => "Select condition",

        "gr" => "Επίλεξε κατάσταση"

    ],

    "condition_excellent" => [

        "en" => "Excellent",

        "gr" => "Άριστη"

    ],

    "condition_good" => [

        "en" => "Good",

        "gr" => "Καλή"

    ],

    "condition_fair" => [

        "en" => "Fair",

        "gr" => "Μέτρια"

    ],

    "condition_poor" => [

        "en" => "Poor",

        "gr" => "Χαμηλή"

    ],

    "participation_preferences" => [

        "en" => "Participation Preferences",

        "gr" => "Προτιμήσεις Συμμετοχής"

    ],

    "participation_description" => [

        "en" => "Set the minimum participation share you are willing to accept. This is not your final share. EnergyMatch will calculate your fair share from the economic value of your contribution.",

        "gr" => "Όρισε το ελάχιστο ποσοστό συμμετοχής που είσαι διατεθειμένος να αποδεχτείς. Αυτό δεν είναι το τελικό σου ποσοστό. Το EnergyMatch θα υπολογίζει το δίκαιο ποσοστό σου από την οικονομική αξία της συνεισφοράς σου."

    ],

    "minimum_participation_share" => [

        "en" => "Minimum Participation Share (%)",

        "gr" => "Ελάχιστο Ποσοστό Συμμετοχής (%)"

    ],

    "fair_share_help" => [

        "en" => "Your final fair share will be calculated automatically.",

        "gr" => "Το τελικό δίκαιο ποσοστό σου θα υπολογίζεται αυτόματα."

    ],

    "location_region" => [

        "en" => "Location / Region",

        "gr" => "Τοποθεσία / Περιοχή"

    ],

    "location_placeholder" => [

        "en" => "e.g. Corinthia",

        "gr" => "π.χ. Κορινθία"

    ],

    "short_description" => [

        "en" => "Short Description",

        "gr" => "Σύντομη Περιγραφή"

    ],

    "description_placeholder" => [

        "en" => "Tell other members a little about yourself and your interest in energy communities...",

        "gr" => "Πες λίγα λόγια για εσένα και το ενδιαφέρον σου για τις ενεργειακές κοινότητες..."

    ],

    "save_profile" => [

        "en" => "Save Profile",

        "gr" => "Αποθήκευση Προφίλ"

    ],

    "clear_profile" => [

        "en" => "Clear Profile",

        "gr" => "Καθαρισμός Προφίλ"

    ],

    "clear_profile_confirm" => [

        "en" => "Are you sure you want to clear your contribution profile?",

        "gr" => "Είσαι σίγουρος ότι θέλεις να διαγράψεις τα στοιχεία συνεισφοράς του προφίλ σου;"

    ],

    /* COMMUNITY FAIR SHARE LABELS */

    "minimum_fairness_margin" => [

        "en" => "Minimum Fairness Margin",

        "gr" => "Ελάχιστο Περιθώριο Δικαιοσύνης"

    ],

    "minimum_share" => [

        "en" => "Minimum Share",

        "gr" => "Ελάχιστο Ποσοστό Συμμετοχής"

    ],

    "fair_share" => [

        "en" => "Fair Share",

        "gr" => "Δίκαιο Ποσοστό"

    ],

    "margin" => [

        "en" => "Margin",

        "gr" => "Περιθώριο"

    ],

    "result" => [

        "en" => "Result",

        "gr" => "Αποτέλεσμα"

    ],

    /* CURRENT MATCHES / COMMUNITY MATCHING */

    "valid" => ["en" => "VALID", "gr" => "ΕΓΚΥΡΗ"],



    /* MATCHES PAGE */


    "member" => ["en" => "member", "gr" => "μέλος"],

    "location_not_provided" => ["en" => "Location not provided", "gr" => "Δεν έχει δηλωθεί τοποθεσία"],

    "previous" => ["en" => "Previous", "gr" => "Προηγούμενη"],

    "next" => ["en" => "Next", "gr" => "Επόμενη"],

        /*

    |--------------------------------------------------------------------------

    | PUBLIC PAGES / HOME

    |--------------------------------------------------------------------------

    */

    "home" => [

        "en" => "Home",

        "gr" => "Αρχική"

    ],

    "sign_in" => [

        "en" => "Sign In",

        "gr" => "Σύνδεση"

    ],

    "about_us" => [

        "en" => "About Us",

        "gr" => "Σχετικά με εμάς"

    ],

    "contacts" => [

        "en" => "Contacts",

        "gr" => "Επικοινωνία"

    ],

    "faq" => [

        "en" => "FAQ",

        "gr" => "Συχνές Ερωτήσεις"

    ],

    "green" => [

        "en" => "GREEN",

        "gr" => "ΠΡΑΣΙΝΗ"

    ],

    "energy" => [

        "en" => "ENERGY",

        "gr" => "ΕΝΕΡΓΕΙΑ"

    ],

    "home_description" => [

        "en" => "Connect with the right people, combine resources and build your own sustainable energy community.",

        "gr" => "Συνδέσου με τους κατάλληλους ανθρώπους, συνδύασε πόρους και δημιούργησε τη δική σου βιώσιμη ενεργειακή κοινότητα."

    ],

    "read_more" => [

        "en" => "READ MORE",

        "gr" => "ΜΑΘΕ ΠΕΡΙΣΣΟΤΕΡΑ"

    ],

        /*

    |--------------------------------------------------------------------------

    | SIGN IN PAGE

    |--------------------------------------------------------------------------

    */

    "username_email" => [

        "en" => "Username or Email",

        "gr" => "Όνομα χρήστη ή Email"

    ],

    "password" => [

        "en" => "Password",

        "gr" => "Κωδικός"

    ],

    "remember_me" => [

        "en" => "Remember me",

        "gr" => "Να με θυμάσαι"

    ],

    "forgot_password" => [

        "en" => "Forgot password?",

        "gr" => "Ξέχασες τον κωδικό;"

    ],

    "no_account" => [

        "en" => "No account?",

        "gr" => "Δεν έχεις λογαριασμό;"

    ],


        "create_one" => [

        "en" => "Create one",

        "gr" => "Δημιούργησε έναν"

    ],

    /*

    |--------------------------------------------------------------------------

    | ABOUT US PAGE

    |--------------------------------------------------------------------------

    */

    /*

    |--------------------------------------------------------------------------

    | CONTACT PAGE

    |--------------------------------------------------------------------------

    */

    "contact_us" => [

        "en" => "Contact Us",

        "gr" => "Επικοινωνήστε μαζί μας"

    ],

    "contact_intro" => [

        "en" => "Have a question about EnergyMatch? We would be happy to hear from you.",

        "gr" => "Έχεις κάποια ερώτηση σχετικά με το EnergyMatch; Θα χαρούμε να επικοινωνήσεις μαζί μας."

    ],

    "get_in_touch" => [

        "en" => "Get in Touch",

        "gr" => "Επικοινώνησε μαζί μας"

    ],

    "contact_description" => [

        "en" => "If you have questions about the platform, energy communities or your EnergyMatch profile, feel free to contact us.",

        "gr" => "Αν έχεις ερωτήσεις σχετικά με την πλατφόρμα, τις ενεργειακές κοινότητες ή το προφίλ σου στο EnergyMatch, μπορείς να επικοινωνήσεις μαζί μας."

    ],

    "contact_email" => [

        "en" => "Email",

        "gr" => "Email"

    ],

    "phone" => [

        "en" => "Phone",

        "gr" => "Τηλέφωνο"

    ],

    "contact_location" => [

        "en" => "Location",

        "gr" => "Τοποθεσία"

    ],

    "greece" => [

        "en" => "Greece",

        "gr" => "Ελλάδα"

    ],

    "send_us_message" => [

        "en" => "Send us a Message",

        "gr" => "Στείλε μας ένα Μήνυμα"

    ],

    "full_name" => [

        "en" => "Full Name",

        "gr" => "Ονοματεπώνυμο"

    ],

    "enter_name" => [

        "en" => "Enter your name",

        "gr" => "Εισάγετε το όνομά σας"

    ],

    "enter_email" => [

        "en" => "Enter your email",

        "gr" => "Εισάγετε το email σας"

    ],

    "subject" => [

        "en" => "Subject",

        "gr" => "Θέμα"

    ],

    "how_can_help" => [

        "en" => "How can we help?",

        "gr" => "Πώς μπορούμε να βοηθήσουμε;"

    ],

    "message" => [

        "en" => "Message",

        "gr" => "Μήνυμα"

    ],

    "write_message" => [

        "en" => "Write your message here...",

        "gr" => "Γράψε το μήνυμά σου εδώ..."

    ],

    "send_message" => [

        "en" => "Send Message",

        "gr" => "Αποστολή Μηνύματος"

    ],

    "contact_message_sent" => [

    "en" => "Your message was sent successfully!",

    "gr" => "Το μήνυμά σας στάλθηκε με επιτυχία!"

],

"contact_error_empty" => [

    "en" => "Please fill in all fields.",

    "gr" => "Παρακαλώ συμπληρώστε όλα τα πεδία."

],

"contact_error_email" => [

    "en" => "Please enter a valid email address.",

    "gr" => "Παρακαλώ εισάγετε έγκυρη διεύθυνση email."

],

"contact_error_long" => [

    "en" => "The message exceeds the maximum allowed length.",

    "gr" => "Το μήνυμα ξεπερνά το μέγιστο επιτρεπόμενο μήκος."

],

"contact_error_general" => [

    "en" => "Something went wrong. Please try again.",

    "gr" => "Παρουσιάστηκε κάποιο πρόβλημα. Παρακαλώ δοκιμάστε ξανά."

],

        /*

    |--------------------------------------------------------------------------

    | FAQ PAGE

    |--------------------------------------------------------------------------

    */

    /* About + FAQ (current EnergyMatch model) */
    "abt_title" => ["en" => "About EnergyMatch", "gr" => "Σχετικά με το EnergyMatch"],
    "abt_intro" => ["en" => "We bring together people with different resources and form fair 5-member energy communities around a shared solar project.", "gr" => "Φέρνουμε κοντά ανθρώπους με διαφορετικούς πόρους και σχηματίζουμε δίκαιες ενεργειακές κοινότητες 5 μελών γύρω από ένα κοινό φωτοβολταϊκό έργο."],
    "abt_mission_title" => ["en" => "Our mission", "gr" => "Η αποστολή μας"],
    "abt_mission_1" => ["en" => "A solar project needs many things at once: money, land, hands, know-how and equipment. Very few people have all of them.", "gr" => "Ένα φωτοβολταϊκό έργο χρειάζεται πολλά μαζί: χρήματα, γη, χέρια, γνώση και εξοπλισμό. Ελάχιστοι τα έχουν όλα."],
    "abt_mission_2" => ["en" => "EnergyMatch lets everyone take part with what they already have, and finds the people who complete the picture.", "gr" => "Το EnergyMatch επιτρέπει σε όλους να συμμετέχουν με ό,τι ήδη έχουν και βρίσκει τους ανθρώπους που συμπληρώνουν την εικόνα."],
    "abt_fair_title" => ["en" => "Fair by design", "gr" => "Δίκαιο από τον σχεδιασμό"],
    "abt_fair_1" => ["en" => "Every contribution is turned into a value in euros with public reference data: Eurostat for land and labour, market prices for equipment.", "gr" => "Κάθε συνεισφορά μετατρέπεται σε αξία σε ευρώ με δημόσια δεδομένα αναφοράς: Eurostat για γη και εργασία, τιμές αγοράς για εξοπλισμό."],
    "abt_fair_2" => ["en" => "Your share of the community (Fair Share) follows your contribution, and it never falls below the minimum share you asked for.", "gr" => "Το μερίδιό σου στην κοινότητα (Fair Share) ακολουθεί τη συνεισφορά σου και δεν πέφτει ποτέ κάτω από το ελάχιστο ποσοστό που ζήτησες."],
    "abt_vision_title" => ["en" => "Our vision", "gr" => "Το όραμά μας"],
    "abt_vision_1" => ["en" => "Clean energy should not be only for those who can pay for a whole installation on their own.", "gr" => "Η καθαρή ενέργεια δεν πρέπει να είναι μόνο για όσους μπορούν να πληρώσουν μόνοι τους μια ολόκληρη εγκατάσταση."],
    "abt_vision_2" => ["en" => "Small contributions, added together fairly, can become a working energy community.", "gr" => "Μικρές συνεισφορές, όταν ενώνονται δίκαια, μπορούν να γίνουν μια ενεργειακή κοινότητα που λειτουργεί."],
    "abt_idea_label" => ["en" => "The idea", "gr" => "Η ιδέα"],
    "abt_idea_title" => ["en" => "Different resources, one shared project", "gr" => "Διαφορετικοί πόροι, ένα κοινό έργο"],
    "abt_idea_1" => ["en" => "One person has savings, another a field with good sun, a third is an electrician, a fourth has a few panels from an older installation. Alone, none of them can build a solar park.", "gr" => "Κάποιος έχει οικονομίες, κάποιος άλλος ένα χωράφι με καλό ήλιο, ένας τρίτος είναι ηλεκτρολόγος και ένας τέταρτος έχει μερικά πάνελ από παλιότερη εγκατάσταση. Μόνος του κανείς δεν μπορεί να στήσει φωτοβολταϊκό πάρκο."],
    "abt_idea_2" => ["en" => "The hard part is not only finding each other, but agreeing who gets what. EnergyMatch answers both: it values every contribution with the same rules and only proposes groups in which every member is treated fairly.", "gr" => "Το δύσκολο δεν είναι μόνο να βρεθούν, αλλά και να συμφωνήσουν ποιος παίρνει τι. Το EnergyMatch απαντά και στα δύο: αποτιμά κάθε συνεισφορά με τους ίδιους κανόνες και προτείνει μόνο ομάδες όπου κάθε μέλος αντιμετωπίζεται δίκαια."],
    "abt_idea_3" => ["en" => "The values are model estimates for comparing contributions, not official valuations or cash amounts.", "gr" => "Οι αξίες είναι εκτιμήσεις μοντέλου για τη σύγκριση των συνεισφορών, όχι επίσημες εκτιμήσεις ή χρηματικά ποσά."],
    "abt_how_title" => ["en" => "How EnergyMatch works", "gr" => "Πώς λειτουργεί το EnergyMatch"],
    "abt_how_subtitle" => ["en" => "Four steps from your profile to an active community.", "gr" => "Τέσσερα βήματα από το προφίλ σου μέχρι μια ενεργή κοινότητα."],
    "abt_step1_title" => ["en" => "Declare your contribution", "gr" => "Δήλωσε τη συνεισφορά σου"],
    "abt_step1_text" => ["en" => "Capital, land, manual or skilled labour, equipment, or a combination. Also choose your minimum share.", "gr" => "Κεφάλαιο, γη, χειρωνακτική ή εξειδικευμένη εργασία, εξοπλισμό ή συνδυασμό τους. Διάλεξε και το ελάχιστο ποσοστό σου."],
    "abt_step2_title" => ["en" => "It is valued in euros", "gr" => "Αποτιμάται σε ευρώ"],
    "abt_step2_text" => ["en" => "The platform calculates the value of each contribution automatically, with the same rules for everyone, and shows you every step of the calculation.", "gr" => "Η πλατφόρμα υπολογίζει αυτόματα την αξία κάθε συνεισφοράς, με τους ίδιους κανόνες για όλους, και σου δείχνει κάθε βήμα του υπολογισμού."],
    "abt_step3_title" => ["en" => "Find a fair group of 5", "gr" => "Βρες μια δίκαιη πεντάδα"],
    "abt_step3_text" => ["en" => "EnergyMatch proposes groups where every Fair Share is at least the Minimum Share, best first. You can also choose the 4 members yourself.", "gr" => "Το EnergyMatch προτείνει ομάδες όπου κάθε Fair Share είναι τουλάχιστον ίσο με το Minimum Share, με τις καλύτερες πρώτες. Μπορείς και να διαλέξεις μόνη/ος σου τα 4 μέλη."],
    "abt_step4_title" => ["en" => "All five accept", "gr" => "Αποδέχονται και οι πέντε"],
    "abt_step4_text" => ["en" => "Each member receives an invitation. When all five accept, the community becomes active and gets its own group chat.", "gr" => "Κάθε μέλος λαμβάνει πρόσκληση. Όταν αποδεχτούν και οι πέντε, η κοινότητα γίνεται ενεργή και αποκτά τη δική της συνομιλία."],
    "abt_example_label" => ["en" => "A real example", "gr" => "Ένα πραγματικό παράδειγμα"],
    "abt_example_title" => ["en" => "Five people, one valid community", "gr" => "Πέντε άνθρωποι, μία έγκυρη κοινότητα"],
    "abt_example_intro" => ["en" => "Together their contributions are worth 20,916.19 €. Each one’s Fair Share is their value divided by that total.", "gr" => "Μαζί οι συνεισφορές τους αξίζουν 20.916,19 €. Το Fair Share του καθενός είναι η αξία του διά αυτό το σύνολο."],
    "abt_ex1_name" => ["en" => "Eleni", "gr" => "Ελένη"],
    "abt_ex1_what" => ["en" => "Capital 5,000 €", "gr" => "Κεφάλαιο 5.000 €"],
    "abt_ex2_name" => ["en" => "Giorgos", "gr" => "Γιώργος"],
    "abt_ex2_what" => ["en" => "2,000 € + 300 h manual labour", "gr" => "2.000 € + 300 ώρες χειρωνακτική"],
    "abt_ex3_name" => ["en" => "Maria", "gr" => "Μαρία"],
    "abt_ex3_what" => ["en" => "Electrician 400 h + inverter", "gr" => "Ηλεκτρολόγος 400 ώρες + inverter"],
    "abt_ex4_name" => ["en" => "Sofia", "gr" => "Σοφία"],
    "abt_ex4_what" => ["en" => "2 batteries of 10 kWh", "gr" => "2 μπαταρίες των 10 kWh"],
    "abt_ex5_name" => ["en" => "Dimitris", "gr" => "Δημήτρης"],
    "abt_ex5_what" => ["en" => "Engineer 250 h + 1,500 €", "gr" => "Μηχανικός 250 ώρες + 1.500 €"],
    "abt_ex_value" => ["en" => "Value", "gr" => "Αξία"],
    "abt_ex_asks" => ["en" => "asks for", "gr" => "ζητά"],
    "abt_example_result_title" => ["en" => "Valid: everyone gets more than they asked for", "gr" => "Έγκυρη: όλοι παίρνουν περισσότερα από όσα ζήτησαν"],
    "abt_example_result_text" => ["en" => "The smallest margin is Sofia’s: 15.15% against the 10% she asked for (+5.15). The bigger this smallest margin, the higher the group is ranked.", "gr" => "Το μικρότερο περιθώριο είναι της Σοφίας: 15,15% έναντι του 10% που ζήτησε (+5,15). Όσο μεγαλύτερο είναι αυτό το μικρότερο περιθώριο, τόσο πιο ψηλά κατατάσσεται η ομάδα."],
    "abt_cta_title" => ["en" => "Ready to find your community?", "gr" => "Έτοιμος/η να βρεις την κοινότητά σου;"],
    "abt_cta_text" => ["en" => "Create an account, declare what you can contribute and see which fair groups you can be part of.", "gr" => "Δημιούργησε λογαριασμό, δήλωσε τι μπορείς να συνεισφέρεις και δες σε ποιες δίκαιες ομάδες μπορείς να συμμετέχεις."],
    "abt_cta_button" => ["en" => "Join EnergyMatch", "gr" => "Γίνε μέλος του EnergyMatch"],
    "faqn_title" => ["en" => "Frequently asked questions", "gr" => "Συχνές ερωτήσεις"],
    "faqn_intro" => ["en" => "Everything about contributions, valuation, Fair Share and communities in EnergyMatch.", "gr" => "Όλα όσα χρειάζεται να ξέρεις για τις συνεισφορές, την αποτίμηση, το Fair Share και τις κοινότητες του EnergyMatch."],
    "faqn_cat_general" => ["en" => "General", "gr" => "Γενικά"],
    "faqn_cat_contrib" => ["en" => "Contributions and valuation", "gr" => "Συνεισφορές και αποτίμηση"],
    "faqn_cat_fair" => ["en" => "Fair Share and groups", "gr" => "Fair Share και ομάδες"],
    "faqn_cat_community" => ["en" => "Communities", "gr" => "Κοινότητες"],
    "faqn_cat_account" => ["en" => "Account", "gr" => "Λογαριασμός"],
    "faqn_q1" => ["en" => "What is EnergyMatch?", "gr" => "Τι είναι το EnergyMatch;"],
    "faqn_a1" => ["en" => "A platform that matches people into energy communities of 5 members. Each member brings something different (capital, land, labour or equipment), and the platform makes sure the shares are fair.", "gr" => "Μια πλατφόρμα που ενώνει ανθρώπους σε ενεργειακές κοινότητες 5 μελών. Κάθε μέλος φέρνει κάτι διαφορετικό (κεφάλαιο, γη, εργασία ή εξοπλισμό) και η πλατφόρμα φροντίζει τα μερίδια να είναι δίκαια."],
    "faqn_q2" => ["en" => "What is an energy community here?", "gr" => "Τι είναι μια ενεργειακή κοινότητα εδώ;"],
    "faqn_a2" => ["en" => "A fixed group of exactly 5 members who combine their resources for a shared solar project. Each member owns a percentage of the community, their Fair Share.", "gr" => "Μια σταθερή ομάδα ακριβώς 5 μελών που συνδυάζουν τους πόρους τους για ένα κοινό φωτοβολταϊκό έργο. Κάθε μέλος κατέχει ένα ποσοστό της κοινότητας, το Fair Share του."],
    "faqn_q3" => ["en" => "How does it work, in short?", "gr" => "Πώς λειτουργεί, με λίγα λόγια;"],
    "faqn_a3" => ["en" => "1. You declare your contribution and your minimum share. 2. The platform values it in euros. 3. It proposes groups of 5 where everyone gets at least their minimum share. 4. When all five accept, the community becomes active.", "gr" => "1. Δηλώνεις τη συνεισφορά σου και το ελάχιστο ποσοστό σου. 2. Η πλατφόρμα την αποτιμά σε ευρώ. 3. Προτείνει πεντάδες όπου όλοι παίρνουν τουλάχιστον το ελάχιστο ποσοστό τους. 4. Όταν αποδεχτούν και οι πέντε, η κοινότητα γίνεται ενεργή."],
    "faqn_q4" => ["en" => "What can I contribute?", "gr" => "Τι μπορώ να συνεισφέρω;"],
    "faqn_a4" => ["en" => "One or more of the following, in any combination:", "gr" => "Ένα ή περισσότερα από τα παρακάτω, σε οποιονδήποτε συνδυασμό:"],
    "faqn_a4_capital" => ["en" => "Capital: money for the project", "gr" => "Κεφάλαιο: χρήματα για το έργο"],
    "faqn_a4_land" => ["en" => "Land: a plot or field for the installation", "gr" => "Γη: οικόπεδο ή χωράφι για την εγκατάσταση"],
    "faqn_a4_manual" => ["en" => "Manual labour: general work on the installation", "gr" => "Χειρωνακτική εργασία: γενική εργασία στην εγκατάσταση"],
    "faqn_a4_skilled" => ["en" => "Skilled labour: engineer, PV technician, electrician, installer", "gr" => "Εξειδικευμένη εργασία: μηχανικός, τεχνικός φωτοβολταϊκών, ηλεκτρολόγος, εγκαταστάτης"],
    "faqn_a4_equipment" => ["en" => "Equipment: PV panels, batteries, electrical equipment (up to 10 groups)", "gr" => "Εξοπλισμός: φωτοβολταϊκά πάνελ, μπαταρίες, ηλεκτρολογικός εξοπλισμός (έως 10 ομάδες)"],
    "faqn_q5" => ["en" => "How is land valued?", "gr" => "Πώς αποτιμάται η γη;"],
    "faqn_a5_1" => ["en" => "Value = stremmata × 1,357.10 € × f. The price per stremma comes from the Eurostat average land price for Greece.", "gr" => "Αξία = στρέμματα × 1.357,10 € × f. Η τιμή ανά στρέμμα προέρχεται από τη μέση τιμή γης της Eurostat για την Ελλάδα."],
    "faqn_a5_2" => ["en" => "The factor f adjusts the price to the sun of your location (solar data from PVGIS) and stays between 0.80 and 1.20. The Solar Score is shown only for information.", "gr" => "Ο συντελεστής f προσαρμόζει την τιμή στον ήλιο της τοποθεσίας σου (ηλιακά δεδομένα PVGIS) και μένει από 0,80 έως 1,20. Το Solar Score εμφανίζεται μόνο ενημερωτικά."],
    "faqn_q6" => ["en" => "How is labour valued?", "gr" => "Πώς αποτιμάται η εργασία;"],
    "faqn_a6_1" => ["en" => "Value = hours × mean hourly earnings in Greece for that kind of work (Eurostat 2022), up to 1,000 hours.", "gr" => "Αξία = ώρες × μέσες ωριαίες αποδοχές στην Ελλάδα για αυτό το είδος εργασίας (Eurostat 2022), έως 1.000 ώρες."],
    "faqn_a6_2" => ["en" => "Manual labour 6.96 €/h, engineer 12.15 €/h, PV technician 10.35 €/h, electrician / installer / other 9.11 €/h.", "gr" => "Χειρωνακτική 6,96 €/ώρα, μηχανικός 12,15 €/ώρα, τεχνικός φωτοβολταϊκών 10,35 €/ώρα, ηλεκτρολόγος / εγκαταστάτης / άλλη 9,11 €/ώρα."],
    "faqn_q7" => ["en" => "How is equipment valued?", "gr" => "Πώς αποτιμάται ο εξοπλισμός;"],
    "faqn_a7_1" => ["en" => "Value = quantity × capacity × price new × age factor × condition factor. Prices new: panels 0.120 €/W, batteries 198.10 €/kWh, electrical equipment 94.66 €/kW (sun.store market prices).", "gr" => "Αξία = ποσότητα × ισχύς × τιμή καινούργιου × συντελεστής ηλικίας × συντελεστής κατάστασης. Τιμές καινούργιου: πάνελ 0,120 €/W, μπαταρίες 198,10 €/kWh, ηλεκτρολογικός εξοπλισμός 94,66 €/kW (τιμές αγοράς sun.store)."],
    "faqn_a7_2" => ["en" => "The age factor falls evenly over the service life (25 years for panels, 10 for batteries and electrical equipment) but never below 20%. Condition: excellent 1.00, good 0.90, fair 0.75, poor 0.50.", "gr" => "Ο συντελεστής ηλικίας μειώνεται ομοιόμορφα στη διάρκεια ζωής (25 χρόνια για πάνελ, 10 για μπαταρίες και ηλεκτρολογικό εξοπλισμό), αλλά ποτέ κάτω από 20%. Κατάσταση: άριστη 1,00, καλή 0,90, μέτρια 0,75, χαμηλή 0,50."],
    "faqn_q8" => ["en" => "Why is everything turned into euros?", "gr" => "Γιατί όλα μετατρέπονται σε ευρώ;"],
    "faqn_a8" => ["en" => "Because a common unit is needed to compare a field with working hours or batteries. These values are model estimates for comparison, not official valuations, and you can see every step of the calculation in your profile.", "gr" => "Γιατί χρειάζεται μια κοινή μονάδα για να συγκριθεί ένα χωράφι με ώρες εργασίας ή μπαταρίες. Οι αξίες αυτές είναι εκτιμήσεις μοντέλου για σύγκριση, όχι επίσημες εκτιμήσεις, και βλέπεις κάθε βήμα του υπολογισμού στο προφίλ σου."],
    "faqn_q9" => ["en" => "What is the Fair Share?", "gr" => "Τι είναι το Fair Share;"],
    "faqn_a9_1" => ["en" => "Your percentage of the community: Fair Share = your value ÷ total value of the 5 members × 100.", "gr" => "Το ποσοστό σου στην κοινότητα: Fair Share = η αξία σου ÷ συνολική αξία των 5 μελών × 100."],
    "faqn_a9_2" => ["en" => "For example, if you contribute 4,000 € in a group worth 20,000 €, your Fair Share is 20%. The same person can have a different Fair Share in different groups.", "gr" => "Για παράδειγμα, αν συνεισφέρεις 4.000 € σε ομάδα αξίας 20.000 €, το Fair Share σου είναι 20%. Ο ίδιος άνθρωπος μπορεί να έχει διαφορετικό Fair Share σε διαφορετικές ομάδες."],
    "faqn_q10" => ["en" => "What is the Minimum Share and how do I choose it?", "gr" => "Τι είναι το Minimum Share και πώς το διαλέγω;"],
    "faqn_a10_1" => ["en" => "The smallest percentage you accept in order to join a community. You set it in your profile.", "gr" => "Το μικρότερο ποσοστό που δέχεσαι για να μπεις σε μια κοινότητα. Το ορίζεις στο προφίλ σου."],
    "faqn_a10_2" => ["en" => "A high Minimum Share means fewer possible groups. Your summary shows your value; if the groups you see are few, try a lower minimum.", "gr" => "Υψηλό Minimum Share σημαίνει λιγότερες πιθανές ομάδες. Η σύνοψη του προφίλ σου δείχνει την αξία σου· αν βλέπεις λίγες ομάδες, δοκίμασε χαμηλότερο ελάχιστο."],
    "faqn_q11" => ["en" => "When is a group valid?", "gr" => "Πότε μια ομάδα είναι έγκυρη;"],
    "faqn_a11" => ["en" => "Only when every one of the 5 members has a Fair Share at least equal to their Minimum Share. If even one member would get less than they asked for, the group is not proposed.", "gr" => "Μόνο όταν και τα 5 μέλη έχουν Fair Share τουλάχιστον ίσο με το Minimum Share τους. Αν έστω ένα μέλος έπαιρνε λιγότερο από όσο ζήτησε, η ομάδα δεν προτείνεται."],
    "faqn_q12" => ["en" => "How are the best groups chosen?", "gr" => "Πώς επιλέγονται οι καλύτερες ομάδες;"],
    "faqn_a12_1" => ["en" => "For each valid group the platform finds the member with the smallest margin (Fair Share minus Minimum Share).", "gr" => "Για κάθε έγκυρη ομάδα η πλατφόρμα βρίσκει το μέλος με το μικρότερο περιθώριο (Fair Share μείον Minimum Share)."],
    "faqn_a12_2" => ["en" => "Groups are ranked by that smallest margin, largest first (maximin). The first proposals are also chosen to be different from each other: each one shares at most 2 of its other members with the proposals above it. All other valid groups follow after them, so none is lost.", "gr" => "Οι ομάδες κατατάσσονται με βάση αυτό το μικρότερο περιθώριο, από το μεγαλύτερο (maximin). Επιπλέον, οι πρώτες προτάσεις επιλέγονται ώστε να διαφέρουν μεταξύ τους: η καθεμία έχει το πολύ 2 κοινά μέλη με όσες εμφανίζονται πριν από αυτήν. Όλες οι υπόλοιπες έγκυρες ομάδες ακολουθούν μετά, οπότε δεν χάνεται καμία."],
    "faqn_q13" => ["en" => "Why don’t I see any community proposals?", "gr" => "Γιατί δεν βλέπω προτάσεις κοινοτήτων;"],
    "faqn_a13" => ["en" => "Usually because your Minimum Share (or that of others) is too high for the available members, there are not yet enough members, your profile has no saved contribution, or you already belong to an active community.", "gr" => "Συνήθως επειδή το Minimum Share σου (ή άλλων) είναι πολύ υψηλό για τα διαθέσιμα μέλη, δεν υπάρχουν ακόμα αρκετά μέλη, το προφίλ σου δεν έχει αποθηκευμένη συνεισφορά ή ανήκεις ήδη σε ενεργή κοινότητα."],
    "faqn_q14" => ["en" => "Automatic or manual community?", "gr" => "Αυτόματη ή χειροκίνητη κοινότητα;"],
    "faqn_a14" => ["en" => "In Matches, «Find my energy communities» shows the best valid groups that include you. With «Manual community» you choose the other 4 members yourself, with filters for name, region and type of contribution; the group is checked with the same Fair Share rules.", "gr" => "Στις Αντιστοιχίσεις, το «Βρες τις ενεργειακές μου κοινότητες» δείχνει τις καλύτερες έγκυρες ομάδες που σε περιλαμβάνουν. Με τη «Χειροκίνητη κοινότητα» διαλέγεις μόνη/ος σου τα άλλα 4 μέλη, με φίλτρα για όνομα, περιοχή και τύπο συνεισφοράς· η ομάδα ελέγχεται με τους ίδιους κανόνες Fair Share."],
    "faqn_q15" => ["en" => "What happens after a community is proposed?", "gr" => "Τι γίνεται αφού προταθεί μια κοινότητα;"],
    "faqn_a15" => ["en" => "The other members receive an invitation in their notifications. When all five accept, the community becomes active. If one member declines, the proposal is cancelled. Each user can belong to one active community at a time.", "gr" => "Τα υπόλοιπα μέλη λαμβάνουν πρόσκληση στις ειδοποιήσεις τους. Όταν αποδεχτούν και οι πέντε, η κοινότητα γίνεται ενεργή. Αν ένα μέλος απορρίψει, η πρόταση ακυρώνεται. Κάθε χρήστης μπορεί να ανήκει σε μία ενεργή κοινότητα τη φορά."],
    "faqn_q16" => ["en" => "Can I change my profile?", "gr" => "Μπορώ να αλλάξω το προφίλ μου;"],
    "faqn_a16" => ["en" => "Yes, at any time before you join an active community. While your community is active, your profile is locked, so that the agreed shares stay the same.", "gr" => "Ναι, οποιαδήποτε στιγμή πριν μπεις σε ενεργή κοινότητα. Όσο η κοινότητά σου είναι ενεργή, το προφίλ σου κλειδώνει, ώστε τα συμφωνημένα μερίδια να μένουν ίδια."],
    "faqn_q17" => ["en" => "Can I leave a community?", "gr" => "Μπορώ να φύγω από μια κοινότητα;"],
    "faqn_a17" => ["en" => "Yes. Because communities are fixed groups of 5, when one member leaves the community is cancelled and the other members are notified. Your profile unlocks and you can look for a new community.", "gr" => "Ναι. Επειδή οι κοινότητες είναι σταθερές ομάδες 5 μελών, όταν ένα μέλος φύγει η κοινότητα ακυρώνεται και τα άλλα μέλη ειδοποιούνται. Το προφίλ σου ξεκλειδώνει και μπορείς να αναζητήσεις νέα κοινότητα."],
    "faqn_q18" => ["en" => "What does «Remember me» do?", "gr" => "Τι κάνει το «Να με θυμάσαι»;"],
    "faqn_a18" => ["en" => "You stay signed in for 30 days on that browser, even after closing it. Signing out or changing your password ends it.", "gr" => "Μένεις συνδεδεμένη/ος για 30 μέρες σε αυτόν τον browser, ακόμα κι αν τον κλείσεις. Η αποσύνδεση ή η αλλαγή κωδικού το τερματίζει."],
    "faqn_q19" => ["en" => "I forgot my password. What do I do?", "gr" => "Ξέχασα τον κωδικό μου. Τι κάνω;"],
    "faqn_a19" => ["en" => "On the sign-in page click «Forgot password?», enter the e-mail of your account and choose a new password.", "gr" => "Στη σελίδα Σύνδεσης πάτα «Ξέχασες τον κωδικό;», γράψε το email του λογαριασμού σου και όρισε νέο κωδικό."],
    "faqn_q20" => ["en" => "What happens if I am reported?", "gr" => "Τι γίνεται αν με αναφέρουν;"],
    "faqn_a20" => ["en" => "A report counts only if the administrator approves it. Every approved report is a warning in your notifications (e.g. 2/5). At 5 approved reports from different members the account is deleted and its community is cancelled. See the {conduct}.", "gr" => "Μια αναφορά μετράει μόνο αν την εγκρίνει ο διαχειριστής. Κάθε εγκεκριμένη αναφορά είναι μια προειδοποίηση στις ειδοποιήσεις σου (π.χ. 2/5). Στις 5 εγκεκριμένες αναφορές από διαφορετικά μέλη ο λογαριασμός διαγράφεται και η κοινότητά του ακυρώνεται. Δες τον {conduct}."],
    "faqn_q21" => ["en" => "How do I delete my account?", "gr" => "Πώς διαγράφω τον λογαριασμό μου;"],
    "faqn_a21_1" => ["en" => "Open the menu with your name (top right) → Settings. At the bottom, under «Delete account», type DELETE and confirm.", "gr" => "Άνοιξε το μενού με το όνομά σου (πάνω δεξιά) → Ρυθμίσεις. Στο τέλος της σελίδας, στο «Διαγραφή λογαριασμού», πληκτρολόγησε DELETE και επιβεβαίωσε."],
    "faqn_a21_2" => ["en" => "The deletion is permanent and cannot be undone: your profile, contributions and messages are removed. Your active community and your pending proposals are cancelled, and the other members are informed so they can look for a new community. To use EnergyMatch again you need a new account.", "gr" => "Η διαγραφή είναι οριστική και δεν αναιρείται: σβήνονται το προφίλ, οι συνεισφορές και τα μηνύματά σου. Η ενεργή κοινότητά σου και οι προτάσεις σε αναμονή ακυρώνονται, και τα άλλα μέλη ενημερώνονται ώστε να ψάξουν νέα κοινότητα. Για να ξαναχρησιμοποιήσεις το EnergyMatch χρειάζεσαι νέο λογαριασμό."],
    "conduct_back" => ["en" => "Back", "gr" => "Επιστροφή"],

        /*

    |--------------------------------------------------------------------------

    | REGISTER PAGE

    |--------------------------------------------------------------------------

    */

    "create_account" => [

        "en" => "Create Account",

        "gr" => "Δημιουργία Λογαριασμού"

    ],

    "register_name" => [

        "en" => "Name",

        "gr" => "Όνομα"

    ],

    "register_surname" => [

        "en" => "Surname",

        "gr" => "Επώνυμο"

    ],

    "register_address" => [

        "en" => "Address",

        "gr" => "Διεύθυνση"

    ],

    "register_region" => [

        "en" => "Region",

        "gr" => "Περιφέρεια"

    ],

    "register_city" => [

        "en" => "City",

        "gr" => "Πόλη"

    ],

    "register_postal_code" => [

        "en" => "Postal Code",

        "gr" => "Ταχυδρομικός Κώδικας"

    ],

    "register_email" => [

        "en" => "Email Address",

        "gr" => "Διεύθυνση Email"

    ],

    "confirm_password" => [

        "en" => "Confirm Password",

        "gr" => "Επιβεβαίωση Κωδικού"

    ],

    "agree_terms" => ["en" => "I accept the", "gr" => "Αποδέχομαι τον"],
    "conduct_link_text" => ["en" => "Code of conduct", "gr" => "Κώδικα συμπεριφοράς"],
    "conduct_title" => ["en" => "Code of conduct", "gr" => "Κώδικας συμπεριφοράς"],
    "conduct_intro" => ["en" => "EnergyMatch communities are built on trust between people who often meet for the first time. By creating an account you accept these rules.", "gr" => "Οι κοινότητες του EnergyMatch στηρίζονται στην εμπιστοσύνη ανάμεσα σε ανθρώπους που συχνά γνωρίζονται για πρώτη φορά. Με τη δημιουργία λογαριασμού αποδέχεσαι αυτούς τους κανόνες."],
    "conduct_rule1_title" => ["en" => "Honest contributions", "gr" => "Ειλικρινής συνεισφορά"],
    "conduct_rule1_text" => ["en" => "Declare only what you really have and can offer: capital, land, hours of work and equipment. The Fair Share of everyone depends on it.", "gr" => "Δήλωσε μόνο ό,τι πραγματικά έχεις και μπορείς να προσφέρεις: κεφάλαιο, γη, ώρες εργασίας και εξοπλισμό. Από αυτό εξαρτάται το Fair Share όλων."],
    "conduct_rule2_title" => ["en" => "Respect", "gr" => "Σεβασμός"],
    "conduct_rule2_text" => ["en" => "Treat the other members politely in the community chat and in every contact, even when you disagree.", "gr" => "Συμπεριφέρσου ευγενικά στα άλλα μέλη, στη συνομιλία της κοινότητας και σε κάθε επαφή, ακόμα κι όταν διαφωνείς."],
    "conduct_rule3_title" => ["en" => "No harassment", "gr" => "Καμία παρενόχληση"],
    "conduct_rule3_text" => ["en" => "Threats, insults, pressure or unwanted repeated messages are not allowed.", "gr" => "Δεν επιτρέπονται απειλές, προσβολές, πιέσεις ή ανεπιθύμητα επαναλαμβανόμενα μηνύματα."],
    "conduct_rule4_title" => ["en" => "Keep your commitments", "gr" => "Τήρηση δεσμεύσεων"],
    "conduct_rule4_text" => ["en" => "When you accept a community, take part and keep what you agreed. If you cannot continue, leave it properly so the others know.", "gr" => "Όταν αποδέχεσαι μια κοινότητα, συμμετέχεις και τηρείς ό,τι συμφωνήθηκε. Αν δεν μπορείς να συνεχίσεις, αποχώρησε κανονικά ώστε να ενημερωθούν οι άλλοι."],
    "conduct_rule5_title" => ["en" => "Privacy", "gr" => "Ιδιωτικότητα"],
    "conduct_rule5_text" => ["en" => "Do not share the personal details of other members outside the platform without their permission.", "gr" => "Μη μοιράζεσαι προσωπικά στοιχεία άλλων μελών εκτός πλατφόρμας χωρίς τη συγκατάθεσή τους."],
    "conduct_reports_label" => ["en" => "Reports", "gr" => "Αναφορές"],
    "conduct_reports_title" => ["en" => "What happens when a rule is broken", "gr" => "Τι γίνεται όταν παραβιάζεται ένας κανόνας"],
    "conduct_step1" => ["en" => "A member of your community can report you, choosing the reason.", "gr" => "Ένα μέλος της κοινότητάς σου μπορεί να σε αναφέρει, επιλέγοντας τον λόγο."],
    "conduct_step2" => ["en" => "The administrator examines the report. It counts only if the administrator approves it.", "gr" => "Ο διαχειριστής εξετάζει την αναφορά. Μετράει μόνο αν την εγκρίνει."],
    "conduct_step3" => ["en" => "Every approved report is a warning, which you see in your notifications (e.g. 2/{limit}).", "gr" => "Κάθε εγκεκριμένη αναφορά είναι μια προειδοποίηση, που τη βλέπεις στις ειδοποιήσεις σου (π.χ. 2/{limit})."],
    "conduct_step4" => ["en" => "At {limit} approved reports from different members the account is blocked: its community is cancelled and the other members are informed. At the next visit the user sees only a farewell message and the account is deleted for good.", "gr" => "Στις {limit} εγκεκριμένες αναφορές από διαφορετικά μέλη ο λογαριασμός αποκλείεται: η κοινότητά του ακυρώνεται και τα υπόλοιπα μέλη ενημερώνονται. Στην επόμενη είσοδο ο χρήστης βλέπει μόνο ένα μήνυμα αποχαιρετισμού και ο λογαριασμός διαγράφεται οριστικά."],
    "conduct_note" => ["en" => "The member who reported you is never shown. Several reports from the same member count once.", "gr" => "Το μέλος που σε ανέφερε δεν εμφανίζεται ποτέ. Πολλές αναφορές από το ίδιο μέλος μετράνε μία φορά."],

    "already_account" => [

        "en" => "Already have an account?",

        "gr" => "Έχεις ήδη λογαριασμό;"

    ],

        /*

    |--------------------------------------------------------------------------

    | SETTINGS PAGE

    |--------------------------------------------------------------------------

    */

    "account_settings" => [

        "en" => "Account Settings",

        "gr" => "Ρυθμίσεις Λογαριασμού"

    ],

    "settings_intro" => [

        "en" => "Manage your personal information, security and EnergyMatch preferences.",

        "gr" => "Διαχειρίσου τα προσωπικά σου στοιχεία, την ασφάλεια και τις προτιμήσεις του EnergyMatch."

    ],

    "personal_information" => [

        "en" => "Personal Information",

        "gr" => "Προσωπικά Στοιχεία"

    ],

    "personal_information_description" => [

        "en" => "Update the personal information associated with your account.",

        "gr" => "Ενημέρωσε τα προσωπικά στοιχεία που συνδέονται με τον λογαριασμό σου."

    ],

    "save_changes" => [

        "en" => "Save Changes",

        "gr" => "Αποθήκευση Αλλαγών"

    ],

    "account_updated" => [

        "en" => "Your account information was updated successfully.",

        "gr" => "Τα στοιχεία του λογαριασμού σου ενημερώθηκαν επιτυχώς."

    ],

    "username_exists" => [

        "en" => "This username is already being used by another account.",

        "gr" => "Αυτό το όνομα χρήστη χρησιμοποιείται ήδη από άλλον λογαριασμό."

    ],

    "email_exists" => [

        "en" => "This email address is already being used by another account.",

        "gr" => "Αυτή η διεύθυνση email χρησιμοποιείται ήδη από άλλον λογαριασμό."

    ],

    "invalid_email" => [

        "en" => "Please enter a valid email address.",

        "gr" => "Παρακαλώ εισήγαγε μια έγκυρη διεύθυνση email."

    ],

    "settings_empty_fields" => [

        "en" => "Please complete all required fields.",

        "gr" => "Παρακαλώ συμπλήρωσε όλα τα υποχρεωτικά πεδία."

    ],

    "settings_error" => [

        "en" => "Something went wrong. Please try again.",

        "gr" => "Παρουσιάστηκε κάποιο πρόβλημα. Προσπάθησε ξανά."

    ],

    "security_description" => [

        "en" => "Manage your password and account security.",

        "gr" => "Διαχειρίσου τον κωδικό πρόσβασης και την ασφάλεια του λογαριασμού σου."

    ],

    "language_setting" => [

        "en" => "Language",

        "gr" => "Γλώσσα"

    ],

    "language_description" => [

        "en" => "Choose the language you want to use in EnergyMatch.",

        "gr" => "Επίλεξε τη γλώσσα που θέλεις να χρησιμοποιείς στο EnergyMatch."

    ],

        /*

    |--------------------------------------------------------------------------

    | CHANGE PASSWORD

    |--------------------------------------------------------------------------

    */

    "change_password" => [

        "en" => "Change Password",

        "gr" => "Αλλαγή Κωδικού"

    ],

    "current_password" => [

        "en" => "Current Password",

        "gr" => "Τρέχων Κωδικός"

    ],

    "new_password" => [

        "en" => "New Password",

        "gr" => "Νέος Κωδικός"

    ],

    "confirm_new_password" => [

        "en" => "Confirm New Password",

        "gr" => "Επιβεβαίωση Νέου Κωδικού"

    ],

    "change_password_button" => [

        "en" => "Change Password",

        "gr" => "Αλλαγή Κωδικού"

    ],

    "password_changed_success" => [

        "en" => "Your password was changed successfully.",

        "gr" => "Ο κωδικός πρόσβασής σου άλλαξε επιτυχώς."

    ],

    "wrong_current_password" => [

        "en" => "The current password you entered is incorrect.",

        "gr" => "Ο τρέχων κωδικός που εισήγαγες είναι λανθασμένος."

    ],

    "passwords_not_match" => [

        "en" => "The new passwords do not match.",

        "gr" => "Οι νέοι κωδικοί δεν ταιριάζουν."

    ],

    "password_too_short" => [

        "en" => "The new password must contain at least 6 characters.",

        "gr" => "Ο νέος κωδικός πρέπει να περιέχει τουλάχιστον 6 χαρακτήρες."

    ],

    "danger_zone" => [

    "en" => "Danger Zone",

    "gr" => "Επικίνδυνη Ζώνη"

    ],

    "delete_account" => [

        "en" => "Delete Account",

        "gr" => "Διαγραφή Λογαριασμού"

    ],

    "delete_account_description" => [

        "en" => "Permanently delete your EnergyMatch account and associated data.",

        "gr" => "Διέγραψε οριστικά τον λογαριασμό σου στο EnergyMatch και τα σχετικά δεδομένα."

    ],

    "delete_warning_title" => [

        "en" => "This action cannot be undone.",

        "gr" => "Αυτή η ενέργεια δεν μπορεί να αναιρεθεί."

    ],

    "delete_warning_text" => [

        "en" => "Your account, energy profile, contributions and messages will be permanently deleted. Your active community and pending proposals will be cancelled and their members informed.",

        "gr" => "Ο λογαριασμός, το ενεργειακό προφίλ, οι συνεισφορές και τα μηνύματά σου θα διαγραφούν οριστικά. Η ενεργή κοινότητά σου και οι προτάσεις σε αναμονή θα ακυρωθούν και τα μέλη τους θα ενημερωθούν."

    ],

    "delete_confirmation_label" => [

        "en" => "Type DELETE to confirm",

        "gr" => "Πληκτρολόγησε DELETE για επιβεβαίωση"

    ],

    "delete_confirmation_error" => [

        "en" => "Please type DELETE exactly to confirm account deletion.",

        "gr" => "Πληκτρολόγησε ακριβώς DELETE για να επιβεβαιώσεις τη διαγραφή."

    ],

    "type_delete_first" => [

        "en" => "Please type DELETE before continuing.",

        "gr" => "Πληκτρολόγησε DELETE πριν συνεχίσεις."

    ],

    "final_delete_confirmation" => [

        "en" => "Are you sure you want to permanently delete your account?",

        "gr" => "Είσαι σίγουρος/η ότι θέλεις να διαγράψεις οριστικά τον λογαριασμό σου;"

    ],

    "delete_account_error" => [

        "en" => "The account could not be deleted. No changes were made.",

        "gr" => "Δεν ήταν δυνατή η διαγραφή του λογαριασμού. Δεν πραγματοποιήθηκε καμία αλλαγή."

    ],

    /* REPORT USER PAGE */

    "report_user_title" => ["en" => "Report User", "gr" => "Αναφορά χρήστη"],

    "report_submitted" => ["en" => "Report Submitted", "gr" => "Η αναφορά υποβλήθηκε"],

    "report_submitted_text" => ["en" => "Your report was recorded successfully and is pending review.", "gr" => "Η αναφορά σου καταγράφηκε επιτυχώς και βρίσκεται σε αναμονή για έλεγχο."],

    "back_to_community" => ["en" => "Back to Community", "gr" => "Πίσω στην Κοινότητα"],

    "report_intro" => ["en" => "Complete the form to submit a report about a member of your community.", "gr" => "Συμπλήρωσε τη φόρμα για να υποβάλεις αναφορά για μέλος της κοινότητάς σου."],

    "reported_user" => ["en" => "Reported user", "gr" => "Χρήστης που αναφέρεται"],

    "report_notice" => ["en" => "Submitting a report does not automatically remove the user from the community. The report is stored for review.", "gr" => "Η υποβολή αναφοράς δεν αφαιρεί αυτόματα τον χρήστη από την κοινότητα. Η αναφορά αποθηκεύεται για έλεγχο."],

    "reason_for_report" => ["en" => "Reason for report", "gr" => "Λόγος αναφοράς"],

    "select_reason" => ["en" => "Select a reason", "gr" => "Επίλεξε λόγο"],

    "reason_inappropriate_behavior" => ["en" => "Inappropriate behavior", "gr" => "Ακατάλληλη συμπεριφορά"],

    "reason_harassment" => ["en" => "Harassment", "gr" => "Παρενόχληση"],

    "reason_misleading_information" => ["en" => "Misleading information", "gr" => "Παραπλανητικές πληροφορίες"],

    "reason_collaboration_problem" => ["en" => "Collaboration problem", "gr" => "Πρόβλημα συνεργασίας"],

    "reason_other" => ["en" => "Other", "gr" => "Άλλο"],

    "additional_information" => ["en" => "Additional information", "gr" => "Πρόσθετες πληροφορίες"],

    "report_description_placeholder" => ["en" => "Optionally describe what happened...", "gr" => "Περιέγραψε προαιρετικά τι συνέβη..."],

    "report_description_help" => ["en" => "Optional — up to 1000 characters.", "gr" => "Προαιρετικό — έως 1000 χαρακτήρες."],

    "cancel" => ["en" => "Cancel", "gr" => "Ακύρωση"],

    "submit_report" => ["en" => "Submit Report", "gr" => "Υποβολή Αναφοράς"],

    "report_form_expired" => ["en" => "The form expired. Refresh the page and try again.", "gr" => "Η φόρμα έληξε. Ανανέωσε τη σελίδα και προσπάθησε ξανά."],

    "report_select_reason_error" => ["en" => "Please select a reason for the report.", "gr" => "Επίλεξε έναν λόγο αναφοράς."],

    "report_description_too_long" => ["en" => "Additional information can contain up to 1000 characters.", "gr" => "Οι πρόσθετες πληροφορίες μπορούν να έχουν έως 1000 χαρακτήρες."],

    "report_check_error" => ["en" => "An error occurred while checking the report.", "gr" => "Παρουσιάστηκε σφάλμα κατά τον έλεγχο της αναφοράς."],

    "report_duplicate_error" => ["en" => "You already have a pending report for this user in this community.", "gr" => "Έχεις ήδη υποβάλει εκκρεμή αναφορά για αυτόν τον χρήστη σε αυτή την κοινότητα."],
    "report_access_denied" => ["en" => "You can report only members of a community you belong to, and not yourself.", "gr" => "Μπορείς να αναφέρεις μόνο μέλη μιας κοινότητας στην οποία ανήκεις, και όχι τον εαυτό σου."],

    "report_save_error" => ["en" => "The report could not be saved.", "gr" => "Δεν ήταν δυνατή η αποθήκευση της αναφοράς."],

    /* LEAVE COMMUNITY PAGE */

    "leave_community_page_title" => ["en" => "Leave Community", "gr" => "Αποχώρηση από Κοινότητα"],

    "leave_success_title" => ["en" => "You left the community", "gr" => "Η αποχώρηση ολοκληρώθηκε"],

    "leave_success_text" => ["en" => "Your departure was recorded and this specific 5-member community was cancelled.", "gr" => "Η αποχώρησή σου καταγράφηκε και η συγκεκριμένη 5μελής κοινότητα ακυρώθηκε."],

    "back_to_dashboard" => ["en" => "Back to Dashboard", "gr" => "Πίσω στο Dashboard"],

    "find_new_community" => ["en" => "Find a New Community", "gr" => "Εύρεση νέας κοινότητας"],

    "leave_intro" => ["en" => "Complete the form to record why you want to leave the community.", "gr" => "Συμπλήρωσε τη φόρμα για να καταγράψεις τον λόγο που θέλεις να αποχωρήσεις."],

    "important" => ["en" => "Important:", "gr" => "Προσοχή:"],

    "leave_warning" => ["en" => "This community is a fixed 5-member group. If you leave, this specific community will be cancelled.", "gr" => "Η κοινότητα αποτελεί συγκεκριμένη ομάδα 5 μελών. Με την αποχώρησή σου, η συγκεκριμένη κοινότητα θα ακυρωθεί."],

    "leave_reason" => ["en" => "Reason for leaving", "gr" => "Λόγος αποχώρησης"],

    "leave_select_reason" => ["en" => "Select a reason", "gr" => "Επίλεξε λόγο"],

    "leave_reason_not_interested" => ["en" => "I am no longer interested in this community", "gr" => "Δεν με ενδιαφέρει πλέον η κοινότητα"],

    "leave_reason_group_composition" => ["en" => "I do not agree with the group composition", "gr" => "Δεν συμφωνώ με τη σύνθεση της ομάδας"],

    "leave_reason_cannot_contribute" => ["en" => "I can no longer provide my contribution", "gr" => "Δεν μπορώ πλέον να προσφέρω τη συνεισφορά μου"],

    "leave_reason_personal" => ["en" => "Personal reason", "gr" => "Προσωπικός λόγος"],

    "leave_reason_other" => ["en" => "Other", "gr" => "Άλλο"],

    "leave_additional_info" => ["en" => "Additional information", "gr" => "Πρόσθετες πληροφορίες"],

    "leave_description_placeholder" => ["en" => "Optionally provide more information...", "gr" => "Γράψε προαιρετικά περισσότερες πληροφορίες..."],

    "leave_description_help" => ["en" => "Optional — up to 1000 characters.", "gr" => "Προαιρετικό — έως 1000 χαρακτήρες."],

    "leave_cancel" => ["en" => "Cancel", "gr" => "Ακύρωση"],

    "leave_submit" => ["en" => "Leave Community", "gr" => "Οριστική Αποχώρηση"],

    "leave_confirm" => ["en" => "Are you sure you want to leave this community?", "gr" => "Είσαι σίγουρος ότι θέλεις να αποχωρήσεις από αυτή την κοινότητα;"],

    "leave_form_expired" => ["en" => "The form expired. Refresh the page and try again.", "gr" => "Η φόρμα έληξε. Ανανέωσε τη σελίδα και προσπάθησε ξανά."],

    "leave_select_reason_error" => ["en" => "Please select a reason for leaving.", "gr" => "Επίλεξε έναν λόγο αποχώρησης."],

    "leave_description_too_long" => ["en" => "Additional information can contain up to 1000 characters.", "gr" => "Οι πρόσθετες πληροφορίες μπορούν να έχουν έως 1000 χαρακτήρες."],

    "leave_failed" => ["en" => "Could not leave the community. Please try again.", "gr" => "Δεν ήταν δυνατή η αποχώρηση από την κοινότητα. Προσπάθησε ξανά."],

    /* ACCEPTED INVITATIONS PAGE */





    "community_created" => ["en" => "Created:", "gr" => "Δημιουργήθηκε:"],




    "accepted_invitation_waiting_message" => ["en" => "You have accepted this invitation. The community will become active when the required acceptances are completed.", "gr" => "Έχεις αποδεχτεί την πρόσκληση. Η κοινότητα θα ενεργοποιηθεί όταν ολοκληρωθούν οι απαραίτητες αποδοχές."],

    "view_community" => ["en" => "View Community", "gr" => "Προβολή Κοινότητας"],

    /* CREATE COMMUNITY PAGE */
    "create_you_must_select_exactly_4_other_members_together_with_y" => ["en" => "You must select exactly 4 other members. Together with you, the community will contain 5 people.", "gr" => "Πρέπει να επιλέξεις ακριβώς 4 άλλα μέλη. Μαζί με εσένα η κοινότητα θα έχει 5 άτομα."],
    "create_this_5_member_group_does_not_pass_the_fair_share_rules" => ["en" => "This 5-member group does not pass the Fair Share rules.", "gr" => "Η συγκεκριμένη 5μελής ομάδα δεν περνά τους κανόνες Fair Share."],
    "create_this_exact_5_member_community_has_already_been_proposed" => ["en" => "This exact 5-member community is already pending or active. Choose a different member combination.", "gr" => "Αυτή η ακριβώς 5μελής ομάδα είναι ήδη σε αναμονή ή ενεργή. Επίλεξε διαφορετικό συνδυασμό μελών."],
    "create_en" => ["en" => "en", "gr" => "el"],
    "create_manual_community" => ["en" => "Manual Community", "gr" => "Χειροκίνητη Κοινότητα"],
    "create_community_submit" => ["en" => "Create Community", "gr" => "Δημιουργία Κοινότητας"],
    "create_select_exactly_4_other_members_together_with_you_the_gr" => ["en" => "Select exactly 4 other members. Together with you, the group will contain 5 people and will be checked using the same Fair Share rules as automatic matching.", "gr" => "Επίλεξε ακριβώς 4 άλλα μέλη. Μαζί με εσένα η ομάδα θα αποτελείται από 5 άτομα και θα ελεγχθεί με τους ίδιους κανόνες Fair Share που χρησιμοποιεί το αυτόματο matching."],
    "create_economic_contribution_value_does_not_necessarily_mean_c" => ["en" => "Economic Contribution Value does not necessarily mean cash. It may come from capital, land, labor, or equipment.", "gr" => "Η Οικονομική Αξία Συνεισφοράς δεν σημαίνει απαραίτητα μετρητά. Μπορεί να προέρχεται από κεφάλαιο, γη, εργασία ή εξοπλισμό."],
    "create_creator" => ["en" => "Creator", "gr" => "Δημιουργός"],
    "create_economic_contribution_value" => ["en" => "Economic Contribution Value", "gr" => "Οικονομική Αξία Συνεισφοράς"],
    "create_selected_group_validation_result" => ["en" => "Selected group validation result", "gr" => "Αποτέλεσμα ελέγχου της επιλεγμένης ομάδας"],
    "create_preview_label" => ["en" => "Preview – nothing has been sent yet", "gr" => "Προεπισκόπηση – δεν έχει σταλεί τίποτα ακόμα"],
    "create_preview_auto_name" => ["en" => "New energy community (name given automatically)", "gr" => "Νέα ενεργειακή κοινότητα (το όνομα θα δοθεί αυτόματα)"],
    "create_preview_intro" => ["en" => "The group is valid: every member gets at least their minimum share. Check it before sending.", "gr" => "Η ομάδα είναι έγκυρη: κάθε μέλος παίρνει τουλάχιστον το ελάχιστο ποσοστό του. Έλεγξέ την πριν τη στείλεις."],
    "create_preview_members" => ["en" => "Members", "gr" => "Μέλη"],
    "create_preview_min_margin" => ["en" => "Smallest margin", "gr" => "Μικρότερο περιθώριο"],
    "create_preview_status" => ["en" => "After sending", "gr" => "Μετά την αποστολή"],
    "create_preview_auto_accepted" => ["en" => "Accepted (creator)", "gr" => "Αποδεκτή (δημιουργός)"],
    "create_preview_will_be_invited" => ["en" => "Gets an invitation", "gr" => "Λαμβάνει πρόσκληση"],
    "create_preview_sure" => ["en" => "Are you sure you want to send the invitations?", "gr" => "Είσαι σίγουρος/η ότι θέλεις να στείλεις τις προσκλήσεις;"],
    "create_preview_sure_text" => ["en" => "The other 4 members will be notified. The community becomes active when all 5 accept; if someone rejects, it is cancelled.", "gr" => "Τα άλλα 4 μέλη θα ειδοποιηθούν. Η κοινότητα ενεργοποιείται όταν αποδεχτούν και οι 5· αν κάποιος απορρίψει, ακυρώνεται."],
    "create_preview_back" => ["en" => "Back to make changes", "gr" => "Πίσω για αλλαγές"],
    "create_preview_send" => ["en" => "Yes, send the invitations", "gr" => "Ναι, στείλε τις προσκλήσεις"],
    "create_member" => ["en" => "Member", "gr" => "Μέλος"],
    "create_contribution" => ["en" => "Contribution", "gr" => "Συνεισφορά"],
    "create_check" => ["en" => "Check", "gr" => "Έλεγχος"],
    "create_community_name_optional" => ["en" => "Community Name (optional)", "gr" => "Όνομα Κοινότητας (προαιρετικό)"],
    "create_e_g_solar_community_athens" => ["en" => "e.g. Solar Community Athens", "gr" => "π.χ. Solar Community Athens"],
    "create_selected_members" => ["en" => "Selected members:", "gr" => "Επιλεγμένα μέλη:"],
    "create_location_not_provided" => ["en" => "Location not provided", "gr" => "Δεν δηλώθηκε περιοχή"],
    "create_economic_value" => ["en" => "Economic Value", "gr" => "Οικονομική Αξία"],
    "create_back" => ["en" => "Back", "gr" => "Πίσω"],
    "create_filter_search" => ["en" => "Search by name or username", "gr" => "Αναζήτηση με όνομα ή username"],
    "create_filter_region" => ["en" => "Region", "gr" => "Περιοχή"],
    "create_filter_all_regions" => ["en" => "All regions", "gr" => "Όλες οι περιοχές"],
    "create_filter_types" => ["en" => "Contribution type", "gr" => "Τύπος συνεισφοράς"],
    "create_filter_all" => ["en" => "All", "gr" => "Όλοι"],
    "create_filter_capital" => ["en" => "Capital", "gr" => "Κεφάλαιο"],
    "create_filter_land" => ["en" => "Land", "gr" => "Γη"],
    "create_filter_manual" => ["en" => "Manual labour", "gr" => "Χειρωνακτική"],
    "create_filter_skilled" => ["en" => "Skilled labour", "gr" => "Εξειδικευμένη"],
    "create_filter_equipment" => ["en" => "Equipment", "gr" => "Εξοπλισμός"],
    "create_filter_of" => ["en" => "of", "gr" => "από"],
    "create_filter_members" => ["en" => "members shown", "gr" => "μέλη εμφανίζονται"],
    "create_filter_reset" => ["en" => "Clear filters", "gr" => "Καθαρισμός φίλτρων"],
    "create_filter_no_results" => ["en" => "No members match these filters.", "gr" => "Κανένα μέλος δεν ταιριάζει με αυτά τα φίλτρα."],
    "create_validate_create_community" => ["en" => "Check & preview", "gr" => "Έλεγχος & προεπισκόπηση"],
    "create_there_are_not_yet_4_available_users_with_a_valid_econom" => ["en" => "There are not yet 4 available users with a valid economic contribution value.", "gr" => "Δεν υπάρχουν ακόμη 4 διαθέσιμοι χρήστες με έγκυρη οικονομική αξία συνεισφοράς."],
    "back_to_matches" => ["en" => "Back to Matches", "gr" => "Πίσω στα Matches"],
    /* NOTIFICATIONS PAGE */
    "notifications_title" => ["en" => "Notifications", "gr" => "Ειδοποιήσεις"],
    "notifications_description" => ["en" => "Review your invitations and updates from the other community members.", "gr" => "Δες τις προσκλήσεις σου και τις ενημερώσεις από τα υπόλοιπα μέλη της κοινότητας."],
    "invitation_accepted_message" => ["en" => "✓ Invitation accepted.", "gr" => "✓ Αποδέχτηκες την πρόσκληση."],
    "invitation_rejected_message" => ["en" => "Invitation rejected.", "gr" => "Η πρόσκληση απορρίφθηκε."],
    "cannot_accept_invitation_yet" => ["en" => "You cannot accept this invitation yet.", "gr" => "Δεν μπορείς να αποδεχτείς αυτή την πρόσκληση ακόμη."],
    "already_active_community_warning" => ["en" => "You are already a member of another active Energy Community. You must first leave your current community and then return here to accept the new invitation.", "gr" => "Συμμετέχεις ήδη σε άλλη ενεργή Ενεργειακή Κοινότητα. Πρέπει πρώτα να αποχωρήσεις από την υπάρχουσα κοινότητά σου και μετά να επιστρέψεις εδώ για να αποδεχτείς τη νέα πρόσκληση."],
    "view_current_community" => ["en" => "View current community", "gr" => "Προβολή υπάρχουσας κοινότητας"],
    "new_badge" => ["en" => "New", "gr" => "Νέο"],
    "notif_categories" => ["en" => "Notification categories", "gr" => "Κατηγορίες ειδοποιήσεων"],
    "notif_cat_all" => ["en" => "All", "gr" => "Όλες"],
    "notif_cat_invitations" => ["en" => "Invitations", "gr" => "Προσκλήσεις"],
    "notif_cat_community" => ["en" => "Community", "gr" => "Κοινότητα"],
    "notif_cat_chat" => ["en" => "Chat", "gr" => "Συνομιλία"],
    "notif_cat_support" => ["en" => "Support", "gr" => "Υποστήριξη"],
    "notif_accepted" => ["en" => "{actor} accepted the proposal {community}.", "gr" => "Ο/Η {actor} αποδέχτηκε την πρόταση {community}."],
    "notif_accepted_count" => ["en" => "{count}/5 members have accepted so far.", "gr" => "Μέχρι τώρα έχουν αποδεχτεί {count}/5 μέλη."],
    "notif_rejected" => ["en" => "{actor} declined the proposal {community}, so it was cancelled. You can find new proposals in Matches.", "gr" => "Ο/Η {actor} απέρριψε την πρόταση {community}, οπότε ακυρώθηκε. Μπορείς να βρεις νέες προτάσεις στις Αντιστοιχίσεις."],
    "notif_activated" => ["en" => "The community {community} is now active! Your profile is locked so the agreed shares stay the same. Any other proposals you were waiting for were cancelled automatically.", "gr" => "Η κοινότητα {community} είναι πλέον ενεργή! Το προφίλ σου κλείδωσε ώστε τα συμφωνημένα μερίδια να μείνουν ίδια. Όσες άλλες προτάσεις σου περίμεναν ακυρώθηκαν αυτόματα."],
    "notif_conflict" => ["en" => "The proposal {community} was cancelled because {actor} joined another community.", "gr" => "Η πρόταση {community} ακυρώθηκε, επειδή ο/η {actor} μπήκε σε άλλη κοινότητα."],
    "notif_left" => ["en" => "{actor} left the community {community}. The community was cancelled and your profile is unlocked.", "gr" => "Ο/Η {actor} αποχώρησε από την κοινότητα {community}. Η κοινότητα ακυρώθηκε και το προφίλ σου ξεκλείδωσε."],
    "notif_chat" => ["en" => "New messages in the chat of {community}.", "gr" => "Νέα μηνύματα στη συνομιλία της κοινότητας {community}."],
    "notif_report_reviewed" => ["en" => "Your report in {community} was reviewed by the administrator.", "gr" => "Η αναφορά σου στην κοινότητα {community} εξετάστηκε από τον διαχειριστή."],
    "notif_report_dismissed" => ["en" => "Your report in {community} was dismissed by the administrator.", "gr" => "Η αναφορά σου στην κοινότητα {community} απορρίφθηκε από τον διαχειριστή."],
    "notif_contact_answered" => ["en" => "Your message {subject} was answered.", "gr" => "Απαντήθηκε το μήνυμά σου {subject}."],
    "notif_account_warning" => ["en" => "Warning {count}: a report about you was approved by the administrator (reason: {reason}). At 5 approved reports your account is deleted.", "gr" => "Προειδοποίηση {count}: μια αναφορά εις βάρος σου εγκρίθηκε από τον διαχειριστή (λόγος: {reason}). Στις 5 εγκεκριμένες αναφορές ο λογαριασμός σου διαγράφεται."],
    "notif_member_removed" => ["en" => "The community {community} was cancelled because a member was removed by the administrator after repeated approved reports.", "gr" => "Η κοινότητα {community} ακυρώθηκε, επειδή ένα μέλος της αφαιρέθηκε από τον διαχειριστή μετά από επανειλημμένες εγκεκριμένες αναφορές."],
    "notif_member_account_deleted" => ["en" => "The community {community} was cancelled because a member deleted their account. You can look for a new community in Matches.", "gr" => "Η κοινότητα {community} ακυρώθηκε, επειδή ένα μέλος της διέγραψε τον λογαριασμό του. Μπορείς να ψάξεις νέα κοινότητα στις Αντιστοιχίσεις."],
    "notif_link_conduct" => ["en" => "Code of conduct", "gr" => "Κώδικας συμπεριφοράς"],
    "notif_admin_reply" => ["en" => "Reply from EnergyMatch", "gr" => "Απάντηση από το EnergyMatch"],
    "notif_link_community" => ["en" => "Go to the community", "gr" => "Πήγαινε στην κοινότητα"],
    "notif_link_chat" => ["en" => "Open the chat", "gr" => "Άνοιγμα συνομιλίας"],
    "notif_link_matches" => ["en" => "Find new proposals", "gr" => "Βρες νέες προτάσεις"],
    "notif_link_accepted" => ["en" => "Accepted invitations", "gr" => "Αποδεκτές προσκλήσεις"],
    "notif_empty_category" => ["en" => "No notifications in this category.", "gr" => "Δεν υπάρχουν ειδοποιήσεις σε αυτή την κατηγορία."],
    "community_updates_pages" => ["en" => "Community updates pages", "gr" => "Σελίδες ενημερώσεων κοινότητας"],
    "community_invitations" => ["en" => "Community Invitations", "gr" => "Προσκλήσεις κοινότητας"],
    "new_community_invitation" => ["en" => "New Community Invitation", "gr" => "Νέα πρόσκληση κοινότητας"],
    "invited_to_community_suffix" => ["en" => "invited you to join this 5-member Energy Community.", "gr" => "σε προσκάλεσε να συμμετέχεις σε αυτή τη 5μελή Ενεργειακή Κοινότητα."],
    "members_label" => ["en" => "Members", "gr" => "Μέλη"],
    "total_group_value" => ["en" => "Total Group Value", "gr" => "Συνολική αξία ομάδας"],
    "accepted_label" => ["en" => "Accepted", "gr" => "Έχουν αποδεχτεί"],
    "your_fair_share" => ["en" => "Your Fair Share", "gr" => "Το Fair Share σου"],
    "view_community_details" => ["en" => "View Community Details", "gr" => "Προβολή στοιχείων κοινότητας"],
    "community_details_intro" => ["en" => "Here you can review all 5 members, their economic contribution, Minimum Share, Fair Share and invitation status.", "gr" => "Εδώ βλέπεις ποιοι είναι οι 5 χρήστες, την οικονομική συνεισφορά τους, το Minimum Share, το Fair Share και την κατάσταση της πρόσκλησής τους."],
    "member_label" => ["en" => "Member", "gr" => "Μέλος"],
    "contribution_label" => ["en" => "Contribution", "gr" => "Συνεισφορά"],
    "margin_label" => ["en" => "Margin", "gr" => "Περιθώριο"],
    "status_label" => ["en" => "Status", "gr" => "Κατάσταση"],
    "you_label" => ["en" => "You", "gr" => "Εσύ"],
    "creator_label" => ["en" => "Creator", "gr" => "Δημιουργός"],
    "invitation_status_accepted" => ["en" => "Accepted", "gr" => "Αποδέχτηκε"],
    "invitation_status_rejected" => ["en" => "Rejected", "gr" => "Απέρριψε"],
    "invitation_status_pending" => ["en" => "Pending", "gr" => "Σε αναμονή"],
    "fair_share_decision_note" => ["en" => "Fair Share shows the participation percentage produced by each member's economic contribution value. Compare it with your Minimum Share before deciding.", "gr" => "Το Fair Share δείχνει το ποσοστό συμμετοχής που προκύπτει από την οικονομική αξία της συνεισφοράς κάθε μέλους. Σύγκρινέ το με το Minimum Share σου πριν αποφασίσεις."],
    "accept_invitation" => ["en" => "Accept", "gr" => "Αποδοχή"],
    "reject_invitation_confirm" => ["en" => "Are you sure you want to reject this invitation?", "gr" => "Θέλεις σίγουρα να απορρίψεις την πρόσκληση;"],
    "reject_invitation" => ["en" => "Reject", "gr" => "Απόρριψη"],
    "no_new_notifications" => ["en" => "No new notifications", "gr" => "Δεν υπάρχουν νέες ειδοποιήσεις"],
    "notifications_empty_text" => ["en" => "New invitations and updates from your community members will appear here.", "gr" => "Οι νέες προσκλήσεις και οι ενημερώσεις από τα μέλη της κοινότητάς σου θα εμφανίζονται εδώ."],

    /* VIEW PROFILE PAGE */
    "view_profile_back_matches" => ["en" => "Back to Matches", "gr" => "Πίσω στις Αντιστοιχίσεις"],
    "view_profile_member_label" => ["en" => "EnergyMatch Member", "gr" => "Μέλος EnergyMatch"],
    "view_profile_available_matching" => ["en" => "Available for matching", "gr" => "Διαθέσιμος για αντιστοίχιση"],
    "view_profile_participation_label" => ["en" => "Participation Profile", "gr" => "Προφίλ Συμμετοχής"],
    "view_profile_contribution_summary" => ["en" => "Contribution Summary", "gr" => "Σύνοψη Συνεισφοράς"],
    "view_profile_total_value" => ["en" => "Total Estimated Contribution", "gr" => "Συνολική Εκτιμώμενη Συνεισφορά"],
    "view_profile_contribution_types" => ["en" => "Contribution Types", "gr" => "Τύποι Συνεισφοράς"],
    "view_profile_fair_share_note" => ["en" => "Fair Share is not fixed on a member profile. It is calculated when a 5-member community is evaluated, based on each member's economic contribution value.", "gr" => "Το Δίκαιο Ποσοστό δεν είναι σταθερό στο προφίλ ενός μέλους. Υπολογίζεται όταν αξιολογείται μια 5μελής κοινότητα, με βάση την οικονομική αξία συνεισφοράς κάθε μέλους."],
    "view_profile_resources_label" => ["en" => "Community Resources", "gr" => "Πόροι Κοινότητας"],
    "view_profile_contributions_title" => ["en" => "Available Contributions", "gr" => "Διαθέσιμες Συνεισφορές"],
    "view_profile_contribution" => ["en" => "Contribution", "gr" => "Συνεισφορά"],
    "view_profile_estimated_value" => ["en" => "Estimated Value", "gr" => "Εκτιμώμενη Αξία"],
    "view_profile_no_contributions" => ["en" => "No contributions available", "gr" => "Δεν υπάρχουν διαθέσιμες συνεισφορές"],
    "view_profile_no_contributions_text" => ["en" => "This member has not saved a contribution yet.", "gr" => "Το μέλος δεν έχει αποθηκεύσει ακόμη κάποια συνεισφορά."],
    "view_profile_member_details" => ["en" => "Member Details", "gr" => "Στοιχεία Μέλους"],
    "view_profile_about_member" => ["en" => "About This Member", "gr" => "Σχετικά με το Μέλος"],
    "view_profile_description_title" => ["en" => "Profile Description", "gr" => "Περιγραφή Προφίλ"],
    "view_profile_no_description" => ["en" => "No profile description has been added yet.", "gr" => "Δεν έχει προστεθεί ακόμη περιγραφή προφίλ."],
    "view_profile_find_more" => ["en" => "Find More Members", "gr" => "Βρες Περισσότερα Μέλη"],
    "view_profile_database_error" => ["en" => "Database error.", "gr" => "Σφάλμα βάσης δεδομένων."],

    /* DASHBOARD - HELP & INFORMATION SECTION */
    "dashboard_help_title" => ["en" => "Need help?", "gr" => "Χρειάζεσαι βοήθεια;"],
    "dashboard_help_text" => ["en" => "Learn more about EnergyMatch, find quick answers or get in touch with our team.", "gr" => "Μάθε περισσότερα για το EnergyMatch, βρες γρήγορες απαντήσεις ή επικοινώνησε με την ομάδα μας."],
    "dashboard_about_text" => ["en" => "What EnergyMatch is and how it brings people together.", "gr" => "Τι είναι το EnergyMatch και πώς φέρνει κοντά τους ανθρώπους."],
    "dashboard_faq_text" => ["en" => "Answers about contributions, Fair Share and communities.", "gr" => "Απαντήσεις για συνεισφορές, Fair Share και κοινότητες."],
    "dashboard_contact_text" => ["en" => "Send us a message and we will get back to you.", "gr" => "Στείλε μας μήνυμα και θα σου απαντήσουμε."],
    "dashboard_about_btn" => ["en" => "Read more", "gr" => "Διάβασε περισσότερα"],
    "dashboard_faq_btn" => ["en" => "View FAQ", "gr" => "Δες τις ερωτήσεις"],
    "dashboard_contact_btn" => ["en" => "Contact us", "gr" => "Επικοινώνησε"],

    /* PROFILE SUMMARY (profile_summary.php) */
    "summary_capital_money" => ["en" => "Capital / Money", "gr" => "Κεφάλαιο / Χρήματα"],
    "summary_land_space" => ["en" => "Land / Space", "gr" => "Γη / Χώρος"],
    "summary_manual_labor" => ["en" => "Manual Labor", "gr" => "Χειρωνακτική Εργασία"],
    "summary_skilled_labor_expertise" => ["en" => "Skilled Labor / Expertise", "gr" => "Εξειδικευμένη Εργασία"],
    "summary_equipment_infrastructure" => ["en" => "Equipment / Infrastructure", "gr" => "Εξοπλισμός / Υποδομή"],
    "summary_pv_panels" => ["en" => "PV panels", "gr" => "Φωτοβολταϊκά πάνελ"],
    "summary_storage_batteries" => ["en" => "Storage batteries", "gr" => "Μπαταρίες αποθήκευσης"],
    "summary_electrical_equipment" => ["en" => "Electrical equipment", "gr" => "Ηλεκτρολογικός εξοπλισμός"],
    "summary_excellent" => ["en" => "Excellent", "gr" => "Άριστη"],
    "summary_good" => ["en" => "Good", "gr" => "Καλή"],
    "summary_fair" => ["en" => "Fair", "gr" => "Μέτρια"],
    "summary_poor" => ["en" => "Poor", "gr" => "Κακή"],
    "summary_lead_acid" => ["en" => "Lead-acid", "gr" => "Μολύβδου-οξέος"],
    "summary_amount" => ["en" => "Amount", "gr" => "Ποσό"],
    "summary_money_is_already_a_monetary_value" => ["en" => "Money is already a monetary value, so it is counted as is:", "gr" => "Τα χρήματα είναι ήδη χρηματική αξία, οπότε μετράνε όπως είναι:"],
    "summary_area" => ["en" => "Area", "gr" => "Έκταση"],
    "summary_stremmata" => ["en" => "stremmata", "gr" => "στρέμματα"],
    "summary_location" => ["en" => "Location", "gr" => "Τοποθεσία"],
    "summary_coordinates" => ["en" => "Coordinates", "gr" => "Συντεταγμένες"],
    "summary_annual_solar_irradiation_pvgis" => ["en" => "Annual solar irradiation (PVGIS)", "gr" => "Ετήσια ηλιακή ακτινοβολία (PVGIS)"],
    "summary_optimal_panel_slope" => ["en" => "Optimal panel slope", "gr" => "Βέλτιστη κλίση πάνελ"],
    "summary_optimal_azimuth" => ["en" => "Optimal azimuth", "gr" => "Βέλτιστο αζιμούθιο"],
    "summary_scale_1_300_2_100_kwh" => ["en" => "scale 1,300–2,100 kWh/m²", "gr" => "κλίμακα 1.300–2.100 kWh/m²"],
    "summary_specialty" => ["en" => "Specialty", "gr" => "Ειδικότητα"],
    "summary_committed_hours" => ["en" => "Committed hours", "gr" => "Δεσμευμένες ώρες"],
    "summary_hours" => ["en" => "hours", "gr" => "ώρες"],
    "summary_hour" => ["en" => "hour", "gr" => "ώρα"],
    "summary_step2_value_hours_hourly_rate" => ["en" => "2. Value = hours × hourly rate", "gr" => "2. Αξία = ώρες × ωριαία τιμή"],
    "summary_type" => ["en" => "Type", "gr" => "Είδος"],
    "summary_quantity" => ["en" => "Quantity", "gr" => "Ποσότητα"],
    "summary_capacity_per_unit" => ["en" => "Capacity per unit", "gr" => "Ισχύς / χωρητικότητα ανά μονάδα"],
    "summary_age" => ["en" => "Age", "gr" => "Ηλικία"],
    "summary_years" => ["en" => "years", "gr" => "έτη"],
    "summary_condition" => ["en" => "Condition", "gr" => "Κατάσταση"],
    "summary_battery_chemistry" => ["en" => "Battery chemistry", "gr" => "Χημεία μπαταρίας"],
    "summary_eq_step1_new_value" => ["en" => "1. Price new = quantity × capacity per unit × price new", "gr" => "1. Τιμή καινούριου = ποσότητα × ισχύς ανά τεμάχιο × τιμή καινούριου"],
    "summary_eq_step2_age_factor" => ["en" => "2. Age factor = 1 − age / service life (minimum 0.20)", "gr" => "2. Συντελεστής ηλικίας = 1 − ηλικία / διάρκεια ζωής (ελάχιστο 0,20)"],
    "summary_eq_step3_condition" => ["en" => "3. Condition factor", "gr" => "3. Συντελεστής κατάστασης"],
    "summary_eq_step4_value" => ["en" => "4. Value = price new × age factor × condition factor", "gr" => "4. Αξία = τιμή καινούριου × συντελεστής ηλικίας × συντελεστής κατάστασης"],
    "summary_eq_age_minimum" => ["en" => "older than its service life: the minimum 0.20 is used", "gr" => "ξεπέρασε τη διάρκεια ζωής: μετράει το ελάχιστο 0,20"],
    "summary_reference_price_source" => ["en" => "Reference price source", "gr" => "Πηγή τιμής αναφοράς"],
    "summary_my_energy_profile" => ["en" => "My Energy Profile", "gr" => "Το Ενεργειακό μου Προφίλ"],
    "summary_what_you_contribute_how_it_was" => ["en" => "What you contribute, how it was valued and how the value was calculated.", "gr" => "Τι συνεισφέρεις, πόσο αποτιμήθηκε και πώς υπολογίστηκε η αξία."],
    "summary_edit_profile" => ["en" => "Edit Profile", "gr" => "Επεξεργασία Προφίλ"],
    "summary_total_contribution_score" => ["en" => "Total contribution score", "gr" => "Συνολική βαθμολογία συνεισφοράς"],
    "summary_sum_of_all_your_contributions_below" => ["en" => "Sum of all your contributions below", "gr" => "Άθροισμα όλων των συνεισφορών σου"],
    "summary_minimum_share" => ["en" => "Minimum Share", "gr" => "Ελάχιστο Ποσοστό"],
    "summary_your_fair_share_is_calculated_per" => ["en" => "Your Fair Share is calculated per community", "gr" => "Το Fair Share σου υπολογίζεται ανά κοινότητα"],
    "summary_location_region" => ["en" => "Location / Region", "gr" => "Τοποθεσία / Περιοχή"],
    "summary_contribution_type_s" => ["en" => "contribution type(s)", "gr" => "είδος/η συνεισφοράς"],
    "summary_you_have_not_added_any_contribution" => ["en" => "You have not added any contribution yet. Edit your profile to add one.", "gr" => "Δεν έχεις προσθέσει ακόμη συνεισφορά. Πάτα «Επεξεργασία Προφίλ» για να προσθέσεις."],
    "summary_not_calculated" => ["en" => "Not calculated", "gr" => "Δεν υπολογίστηκε"],
    "summary_what_you_declared" => ["en" => "What you declared", "gr" => "Τι δήλωσες"],
    "summary_how_it_was_calculated" => ["en" => "How it was calculated", "gr" => "Πώς υπολογίστηκε"],
    "summary_calculation_details_are_not_available_for" => ["en" => "Calculation details are not available for this entry.", "gr" => "Δεν υπάρχουν διαθέσιμα στοιχεία υπολογισμού για αυτή την καταχώριση."],
    "summary_short_description" => ["en" => "Short Description", "gr" => "Σύντομη Περιγραφή"],
    "summary_no_description_added_yet" => ["en" => "No description added yet.", "gr" => "Δεν έχει προστεθεί περιγραφή."],
    "summary_find_my_energy_communities" => ["en" => "Find my energy communities", "gr" => "Βρες τις ενεργειακές μου κοινότητες"],
    /* LABOUR HOURS LIMIT (profile.php, save_profile.php) */
    "labor_hours_limit_help" => ["en" => "Maximum 1,000 hours (about 6 months of full-time work). Value = hours × €6.96 (Eurostat 2022 mean hourly earnings in Greece, elementary occupations).", "gr" => "Μέγιστο 1.000 ώρες (περίπου 6 μήνες πλήρους απασχόλησης). Αξία = ώρες × 6,96 € (μέσες ωριαίες αποδοχές Eurostat 2022 στην Ελλάδα, ανειδίκευτες εργασίες)."],
    "labor_hours_limit_title" => ["en" => "Too many labour hours", "gr" => "Πάρα πολλές ώρες εργασίας"],
    "labor_hours_limit_text" => ["en" => "You can declare up to 1,000 hours of work (about 6 months full-time). Your profile was not saved. Please lower the hours and save again.", "gr" => "Μπορείς να δηλώσεις έως 1.000 ώρες εργασίας (περίπου 6 μήνες πλήρους απασχόλησης). Το προφίλ δεν αποθηκεύτηκε. Μείωσε τις ώρες και αποθήκευσε ξανά."],

    /* LABOUR VALUATION - Eurostat SES 2022 (profile.php, profile_summary.php) */
    "skilled_hours_help" => ["en" => "Maximum 1,000 hours. Value = hours × mean hourly earnings in Greece for your specialty (Eurostat 2022): Engineer €12.15, PV Technician €10.35, Electrician / Installer / Other €9.11.", "gr" => "Μέγιστο 1.000 ώρες. Αξία = ώρες × μέσες ωριαίες αποδοχές στην Ελλάδα για την ειδικότητά σου (Eurostat 2022): Μηχανικός 12,15 €, Τεχνικός Φωτοβολταϊκών 10,35 €, Ηλεκτρολόγος / Εγκαταστάτης / Άλλη 9,11 €."],
    "summary_labor_step1_rate" => ["en" => "1. Hourly rate = mean hourly earnings in Greece for this occupation group (Eurostat 2022)", "gr" => "1. Ωριαία τιμή = μέσες ωριαίες αποδοχές στην Ελλάδα για αυτή την ομάδα επαγγελμάτων (Eurostat 2022)"],
    "summary_labor_note" => ["en" => "Source: Eurostat Structure of Earnings Survey 2022 (earn_ses22_14), Greece. Up to 1,000 hours are counted.", "gr" => "Πηγή: Eurostat, Έρευνα Διάρθρωσης Αποδοχών 2022 (earn_ses22_14), Ελλάδα. Μετράνε έως 1.000 ώρες."],
    "labor_group_OC2" => ["en" => "Professionals", "gr" => "Επαγγελματίες (π.χ. μηχανικοί)"],
    "labor_group_OC3" => ["en" => "Technicians and associate professionals", "gr" => "Τεχνικοί και βοηθοί επαγγελματίες"],
    "labor_group_OC7" => ["en" => "Craft and related trades workers", "gr" => "Τεχνίτες"],
    "labor_group_OC9" => ["en" => "Elementary occupations", "gr" => "Ανειδίκευτες εργασίες"],

    /* LAND VALUATION - Eurostat x f (profile_summary.php, matches.php, profile.php, view_profile.php) */
    "land_value_title" => ["en" => "Land value", "gr" => "Αποτιμημένη αξία γης"],
    "land_annual_irradiation" => ["en" => "Annual solar irradiation", "gr" => "Ετήσια ηλιακή ακτινοβολία"],
    "land_location_factor" => ["en" => "Location factor f", "gr" => "Συντελεστής τοποθεσίας f"],
    "land_not_calculated" => ["en" => "Not yet calculated", "gr" => "Δεν έχει υπολογιστεί"],
    "land_value_note_short" => ["en" => "Eurostat land price adjusted to the solar potential of the location. Model estimate, not a certified property valuation.", "gr" => "Τιμή γης Eurostat προσαρμοσμένη στο ηλιακό δυναμικό της τοποθεσίας. Εκτίμηση μοντέλου, όχι πιστοποιημένη εκτίμηση ακινήτου."],
    "summary_stremma" => ["en" => "stremma", "gr" => "στρέμμα"],
    "summary_land_step1_price" => ["en" => "1. Price per stremma = Eurostat national average ÷ 10", "gr" => "1. Τιμή ανά στρέμμα = εθνικός μέσος όρος Eurostat ÷ 10"],
    "summary_land_step2_factor" => ["en" => "2. Location factor f = solar irradiation ÷ H_reference", "gr" => "2. Συντελεστής τοποθεσίας f = ηλιακή ακτινοβολία ÷ H_αναφοράς"],
    "summary_land_step3_value" => ["en" => "3. Value = stremmata × price per stremma × f", "gr" => "3. Αξία = στρέμματα × τιμή ανά στρέμμα × f"],
    "summary_land_factor_limited" => ["en" => "limited to the range 0.8 - 1.2", "gr" => "περιορισμένο στο εύρος 0,8 - 1,2"],
    "summary_land_factor_default" => ["en" => "solar data not available, national average used", "gr" => "χωρίς ηλιακά δεδομένα, χρησιμοποιείται ο εθνικός μέσος όρος"],
    "summary_land_note" => ["en" => "One-off land value: Eurostat agricultural land price (Greece, 2022), adjusted to the solar potential of the location (PVGIS). The Solar Score is shown for information and does not change the value.", "gr" => "Εφάπαξ αξία γης: τιμή αγροτικής γης Eurostat (Ελλάδα, 2022), προσαρμοσμένη στο ηλιακό δυναμικό της τοποθεσίας (PVGIS). Το Solar Score είναι ενημερωτικό και δεν αλλάζει την αξία."],

    /* PROFILE LOCK WHILE IN AN ACTIVE COMMUNITY (profile.php, save_profile.php) */
    "profile_locked_title" => ["en" => "Your profile is locked", "gr" => "Το προφίλ σου είναι κλειδωμένο"],
    "profile_locked_text" => ["en" => "You are a member of an active community, so your contributions and Minimum Share cannot change while it is active. This keeps the percentages the members agreed on. If you leave the community, you can edit your profile again.", "gr" => "Είσαι μέλος ενεργής κοινότητας, οπότε οι συνεισφορές και το Minimum Share σου δεν μπορούν να αλλάξουν όσο είναι ενεργή. Έτσι μένουν σταθερά τα ποσοστά που συμφώνησαν τα μέλη. Αν αποχωρήσεις από την κοινότητα, μπορείς να επεξεργαστείς ξανά το προφίλ σου."],
    "profile_locked_attempt" => ["en" => "The changes were not saved: your profile cannot change while you are a member of an active community. Leave the community first if you want to edit it.", "gr" => "Οι αλλαγές δεν αποθηκεύτηκαν: το προφίλ σου δεν μπορεί να αλλάξει όσο είσαι μέλος ενεργής κοινότητας. Αν θέλεις να το επεξεργαστείς, αποχώρησε πρώτα από την κοινότητα."],
    "profile_locked_go_community" => ["en" => "Go to my community", "gr" => "Μετάβαση στην κοινότητά μου"],
    "profile_locked_badge" => ["en" => "Locked", "gr" => "Κλειδωμένο"],

    /* SEARCH HISTORY (matches.php) */
    "search_history_show" => ["en" => "Show search history", "gr" => "Εμφάνιση ιστορικού αναζήτησης"],
    "search_history_recent" => ["en" => "Recent searches", "gr" => "Πρόσφατες αναζητήσεις"],
    "search_history_empty" => ["en" => "No recent searches yet.", "gr" => "Δεν υπάρχουν πρόσφατες αναζητήσεις."],
    "search_history_clear" => ["en" => "Clear history", "gr" => "Καθαρισμός ιστορικού"],
    "search_history_remove" => ["en" => "Remove", "gr" => "Αφαίρεση"],

    /* CSRF PROTECTION (csrf.php) */
    "csrf_error_title" => ["en" => "This form has expired", "gr" => "Η φόρμα δεν είναι πλέον έγκυρη"],
    "csrf_error_text" => ["en" => "For your security, the request was not accepted. Go back, refresh the page and try again.", "gr" => "Για την ασφάλειά σου, το αίτημα δεν έγινε δεκτό. Γύρνα πίσω, ανανέωσε τη σελίδα και δοκίμασε ξανά."],
    "csrf_error_back" => ["en" => "Go back", "gr" => "Επιστροφή"],

    /* ONE ACTIVE COMMUNITY PER USER (community_algorithm.php, matches.php) */
    "already_in_active_community_title" => ["en" => "You already have an active community.", "gr" => "Έχεις ήδη ενεργή κοινότητα."],
    "already_in_active_community_text" => ["en" => "You are already a member of an active community. Each user can belong to one active community at a time.", "gr" => "Είσαι ήδη μέλος μιας ενεργής κοινότητας. Κάθε χρήστης μπορεί να ανήκει σε μία ενεργή κοινότητα τη φορά."],
    "go_to_my_community" => ["en" => "Go to My Community", "gr" => "Μετάβαση στην Κοινότητά μου"],

    /* LAND MAP - AUTOMATIC LOCATION (js/land-map.js) */
    "land_map_help" => ["en" => "Type the location to move the map there, or click on the map. You can drag the pin to the exact spot of your field.", "gr" => "Γράψε την τοποθεσία για να πάει εκεί ο χάρτης ή πάτα πάνω στον χάρτη. Μπορείς να σύρεις την πινέζα στο ακριβές σημείο του χωραφιού."],
    "land_map_searching" => ["en" => "Searching location...", "gr" => "Αναζήτηση τοποθεσίας..."],
    "land_map_found" => ["en" => "Location found. Drag the pin to the exact spot of your field.", "gr" => "Βρέθηκε η τοποθεσία. Σύρε την πινέζα στο ακριβές σημείο του χωραφιού."],
    "land_map_not_found" => ["en" => "Location not found. Try a different name or click on the map.", "gr" => "Δεν βρέθηκε η τοποθεσία. Δοκίμασε άλλο όνομα ή πάτα πάνω στον χάρτη."],
    "land_map_address_filled" => ["en" => "Land location filled in from the map.", "gr" => "Η τοποθεσία γης συμπληρώθηκε από τον χάρτη."],
    "land_map_error" => ["en" => "Location service is not available right now.", "gr" => "Η υπηρεσία τοποθεσίας δεν είναι διαθέσιμη αυτή τη στιγμή."],

    /* EQUIPMENT GROUPS - "+ Add equipment" (profile.php, save_profile.php) */
    "equipment_group" => ["en" => "Equipment", "gr" => "Εξοπλισμός"],
    "add_equipment" => ["en" => "Add equipment", "gr" => "Προσθήκη εξοπλισμού"],
    "remove_equipment" => ["en" => "Remove", "gr" => "Αφαίρεση"],
    "equipment_groups_help" => ["en" => "Declare each group of identical equipment separately. Example: 3 panels of 450 W, 2 years old, and 2 panels of 300 W, 8 years old, are two groups. You can also add batteries or electrical equipment as extra groups.", "gr" => "Δήλωσε κάθε ομάδα ίδιου εξοπλισμού ξεχωριστά. Παράδειγμα: 3 πάνελ των 450 W, 2 ετών, και 2 πάνελ των 300 W, 8 ετών, είναι δύο ομάδες. Μπορείς να προσθέσεις και μπαταρίες ή ηλεκτρολογικό εξοπλισμό ως επιπλέον ομάδες."],
    "equipment_max_rows_help" => ["en" => "Up to 10 equipment groups.", "gr" => "Έως 10 ομάδες εξοπλισμού."],
    "equipment_capacity_per_unit" => ["en" => "Power / capacity per unit", "gr" => "Ισχύς / χωρητικότητα ανά τεμάχιο"],
    "equipment_capacity_pv_panels" => ["en" => "Power per panel", "gr" => "Ισχύς ανά πάνελ"],
    "equipment_capacity_battery_storage" => ["en" => "Capacity per battery", "gr" => "Χωρητικότητα ανά μπαταρία"],
    "equipment_capacity_electrical_equipment" => ["en" => "Power per unit", "gr" => "Ισχύς ανά μονάδα"],
    "battery_chemistry_optional" => ["en" => "Battery chemistry (optional)", "gr" => "Τύπος μπαταρίας (προαιρετικό)"],
    "battery_chemistry_unknown" => ["en" => "I don't know", "gr" => "Δεν γνωρίζω"],
    "equipment_incomplete_title" => ["en" => "Incomplete equipment", "gr" => "Ελλιπής εξοπλισμός"],
    "equipment_incomplete_text" => ["en" => "Fill in type, quantity, power/capacity, age and condition for every equipment group, or remove the empty group. Your profile was not saved.", "gr" => "Συμπλήρωσε τύπο, ποσότητα, ισχύ/χωρητικότητα, ηλικία και κατάσταση σε κάθε ομάδα εξοπλισμού ή αφαίρεσε την κενή ομάδα. Το προφίλ δεν αποθηκεύτηκε."],
    "equipment_too_many_title" => ["en" => "Too many equipment groups", "gr" => "Πάρα πολλές ομάδες εξοπλισμού"],
    "equipment_too_many_text" => ["en" => "You can declare up to 10 equipment groups. Your profile was not saved.", "gr" => "Μπορείς να δηλώσεις έως 10 ομάδες εξοπλισμού. Το προφίλ δεν αποθηκεύτηκε."],

    /* NAVBAR (navbar.php) */
    "nav_my_community" => ["en" => "My Community", "gr" => "Η Κοινότητά μου"],
    "nav_active_community" => ["en" => "Active Community", "gr" => "Ενεργή Κοινότητα"],
    "nav_active_community_text" => ["en" => "Your current community", "gr" => "Η τρέχουσα κοινότητά σου"],
    "nav_accepted_invitations" => ["en" => "My proposals", "gr" => "Οι προτάσεις μου"],
    "nav_accepted_invitations_text" => ["en" => "Sent, accepted and history", "gr" => "Σταλμένες, αποδεκτές και ιστορικό"],
    "member_status_left" => ["en" => "Left", "gr" => "Αποχώρησε"],
    "member_status_rejected" => ["en" => "Declined", "gr" => "Απέρριψε"],
    "member_status_pending" => ["en" => "Has not answered yet", "gr" => "Δεν έχει απαντήσει ακόμη"],
    "member_status_accepted" => ["en" => "Accepted", "gr" => "Αποδέχτηκε"],
    "history_cancelled" => ["en" => "The proposal was cancelled.", "gr" => "Η πρόταση ακυρώθηκε."],
    "history_you_joined" => ["en" => "Cancelled automatically because you joined another community.", "gr" => "Ακυρώθηκε αυτόματα επειδή μπήκες σε άλλη κοινότητα."],
    "proposals_sent_waiting" => ["en" => "Waiting for the answers of the other members.", "gr" => "Περιμένεις τις απαντήσεις των άλλων μελών."],
    "history_conflict" => ["en" => "Cancelled because {name} joined another community.", "gr" => "Ακυρώθηκε επειδή ο/η {name} μπήκε σε άλλη κοινότητα."],
    "history_member_left" => ["en" => "Cancelled because {name} left the community.", "gr" => "Ακυρώθηκε επειδή ο/η {name} αποχώρησε από την κοινότητα."],
    "history_member_deleted" => ["en" => "Cancelled because a member deleted their account.", "gr" => "Ακυρώθηκε επειδή ένα μέλος διέγραψε τον λογαριασμό του."],
    "history_member_removed" => ["en" => "Cancelled because a member was removed by the administrator.", "gr" => "Ακυρώθηκε επειδή ένα μέλος αφαιρέθηκε από τον διαχειριστή."],
    "history_declined" => ["en" => "A member declined the proposal.", "gr" => "Ένα μέλος απέρριψε την πρόταση."],
    "history_declined_by" => ["en" => "Declined by {name}.", "gr" => "Απορρίφθηκε από τον/την {name}."],
    "history_you_left" => ["en" => "You left this community.", "gr" => "Αποχώρησες εσύ από αυτή την κοινότητα."],
    "history_you_declined" => ["en" => "You declined this proposal.", "gr" => "Απέρριψες εσύ αυτή την πρόταση."],
    "proposal_own_badge" => ["en" => "Your proposal", "gr" => "Δική σου πρόταση"],
    "proposals_history_range" => ["en" => "{from}–{to} of {total}, newest first.", "gr" => "{from}–{to} από {total}, πρώτα οι πιο πρόσφατες."],
    "next_page" => ["en" => "Next page", "gr" => "Επόμενη σελίδα"],
    "previous_page" => ["en" => "Previous page", "gr" => "Προηγούμενη σελίδα"],
    "proposals_history_empty" => ["en" => "Nothing here yet.", "gr" => "Δεν υπάρχει τίποτα ακόμη."],
    "proposals_history_text" => ["en" => "Proposals that did not become (or stopped being) a community, and why.", "gr" => "Προτάσεις που δεν έγιναν (ή έπαψαν να είναι) κοινότητα, και γιατί."],
    "proposals_history_title" => ["en" => "History", "gr" => "Ιστορικό"],
    "proposals_accepted_short" => ["en" => "accepted", "gr" => "αποδεκτές"],
    "proposals_accepted_empty" => ["en" => "No accepted invitations are waiting.", "gr" => "Δεν υπάρχουν αποδεκτές προσκλήσεις σε αναμονή."],
    "proposals_accepted_text" => ["en" => "Other members invited you and you accepted. They are waiting for the rest of the group.", "gr" => "Σε προσκάλεσαν άλλα μέλη και αποδέχτηκες. Περιμένουν την απάντηση της υπόλοιπης ομάδας."],
    "proposals_accepted_title" => ["en" => "Invitations I accepted", "gr" => "Προσκλήσεις που αποδέχτηκα"],
    "proposals_sent_empty" => ["en" => "You have no proposals waiting. You can create one from Matches.", "gr" => "Δεν έχεις προτάσεις σε αναμονή. Μπορείς να φτιάξεις μία από τις Αντιστοιχίσεις."],
    "proposals_sent_text" => ["en" => "You created these groups. They become active when all 5 members accept.", "gr" => "Αυτές τις ομάδες τις δημιούργησες εσύ. Γίνονται ενεργές όταν αποδεχτούν και τα 5 μέλη."],
    "proposals_sent_title" => ["en" => "Proposals I sent", "gr" => "Προτάσεις που έστειλα"],
    "my_proposals_intro" => ["en" => "The proposals you sent, the ones you accepted and are waiting for the others, and what happened to older ones.", "gr" => "Οι προτάσεις που έστειλες, όσες αποδέχτηκες και περιμένουν τους άλλους, και τι έγινε με τις παλαιότερες."],
    "my_proposals_title" => ["en" => "My proposals", "gr" => "Οι προτάσεις μου"],
    "nav_notifications" => ["en" => "Notifications", "gr" => "Ειδοποιήσεις"],
    "nav_user_menu" => ["en" => "User menu", "gr" => "Μενού χρήστη"],
    "nav_matches_short" => ["en" => "Matches", "gr" => "Ταίριασμα"],
    "nav_community_short" => ["en" => "Community", "gr" => "Κοινότητα"],

    /* DASHBOARD (dashboard.php) */
    "dash_good_morning" => ["en" => "Good morning", "gr" => "Καλημέρα"],
    "dash_good_afternoon" => ["en" => "Good afternoon", "gr" => "Καλό απόγευμα"],
    "dash_good_evening" => ["en" => "Good evening", "gr" => "Καλησπέρα"],
    "dash_find_communities" => ["en" => "Find communities", "gr" => "Βρες κοινότητες"],
    "dash_total_value" => ["en" => "Total contribution value", "gr" => "Συνολική αξία συνεισφοράς"],
    "dash_no_contributions" => ["en" => "No contributions yet", "gr" => "Δεν έχεις δηλώσει συνεισφορές"],
    "dash_minimum_share" => ["en" => "Minimum share", "gr" => "Ελάχιστο ποσοστό"],
    "dash_minimum_share_text" => ["en" => "Your own limit", "gr" => "Το δικό σου όριο"],
    "dash_region" => ["en" => "Region", "gr" => "Περιοχή"],
    "dash_region_text" => ["en" => "From your profile", "gr" => "Από το προφίλ σου"],
    "dash_kind_capital" => ["en" => "Capital", "gr" => "Κεφάλαιο"],
    "dash_kind_land" => ["en" => "Land", "gr" => "Γη"],
    "dash_kind_labor" => ["en" => "Labour", "gr" => "Εργασία"],
    "dash_kind_equipment" => ["en" => "Equipment", "gr" => "Εξοπλισμός"],
    "dash_steps_title" => ["en" => "Your steps", "gr" => "Τα βήματά σου"],
    "dash_steps_text" => ["en" => "Where you are and what comes next.", "gr" => "Πού βρίσκεσαι και τι ακολουθεί."],
    "dash_step_of" => ["en" => "Step %d of %d", "gr" => "Βήμα %d από %d"],
    "dash_steps_all_done" => ["en" => "All done", "gr" => "Ολοκληρώθηκαν"],
    "dash_step_profile" => ["en" => "Complete your profile", "gr" => "Συμπλήρωσε το προφίλ"],
    "dash_step_profile_done" => ["en" => "%d kinds of contribution declared", "gr" => "%d είδη συνεισφοράς δηλωμένα"],
    "dash_step_profile_todo" => ["en" => "Declare what you can contribute", "gr" => "Δήλωσε τι μπορείς να συνεισφέρεις"],
    "dash_step_profile_btn" => ["en" => "Complete profile", "gr" => "Συμπλήρωση προφίλ"],
    "dash_step_find" => ["en" => "Find a community", "gr" => "Βρες κοινότητα"],
    "dash_step_find_done" => ["en" => "You have joined a community", "gr" => "Συμμετέχεις σε κοινότητα"],
    "dash_step_find_todo" => ["en" => "See the proposed 5-member communities", "gr" => "Δες τις προτεινόμενες κοινότητες 5 μελών"],
    "dash_step_invitations" => ["en" => "Invitations accepted", "gr" => "Αποδοχή προσκλήσεων"],
    "dash_step_invitations_open" => ["en" => "%d invitations waiting for your answer", "gr" => "%d προσκλήσεις περιμένουν απάντηση"],
    "dash_step_invitations_waiting" => ["en" => "Waiting for the other members", "gr" => "Αναμονή για τα υπόλοιπα μέλη"],
    "dash_step_invitations_todo" => ["en" => "All 5 members must accept", "gr" => "Πρέπει να αποδεχτούν και τα 5 μέλη"],
    "dash_step_active" => ["en" => "Active community", "gr" => "Ενεργή κοινότητα"],
    "dash_step_active_done" => ["en" => "Your shares are locked", "gr" => "Τα ποσοστά σου έχουν κλειδώσει"],
    "dash_step_active_todo" => ["en" => "Shares lock when it becomes active", "gr" => "Τα ποσοστά κλειδώνουν με την ενεργοποίηση"],

    /* LANDING PAGE (index.php) */
    "home_eyebrow" => ["en" => "Energy communities", "gr" => "Ενεργειακές κοινότητες"],
    "home_point_match" => ["en" => "Matched into 5-member communities", "gr" => "Κοινότητες 5 μελών με αυτόματο ταίριασμα"],
    "home_point_fair" => ["en" => "Fair share from the value you contribute", "gr" => "Δίκαιο ποσοστό από την αξία που συνεισφέρεις"],
    "home_point_solar" => ["en" => "Land assessed with real solar data", "gr" => "Αποτίμηση γης με πραγματικά ηλιακά δεδομένα"],

    /* ADMIN NAVBAR (admin_navbar.php) */
    "admin_nav_dashboard" => ["en" => "Dashboard", "gr" => "Πίνακας"],
    "admin_nav_users" => ["en" => "Users", "gr" => "Χρήστες"],
    "admin_nav_communities" => ["en" => "Communities", "gr" => "Κοινότητες"],
    "admin_nav_reports" => ["en" => "Reports", "gr" => "Αναφορές"],
    "admin_nav_messages" => ["en" => "Messages", "gr" => "Μηνύματα"],

    /* SIGN IN / REGISTER (sign_in.php, register_form.php, auth_side.php) */
    "auth_signin_side_title" => ["en" => "Welcome back", "gr" => "Καλώς ήρθες ξανά"],
    "auth_signin_side_text" => ["en" => "Sign in to see your community proposals and your community.", "gr" => "Συνδέσου για να δεις τις προτάσεις κοινοτήτων και την κοινότητά σου."],
    "auth_signin_lead" => ["en" => "With your username or email.", "gr" => "Με το όνομα χρήστη ή το email σου."],
    "auth_register_side_title" => ["en" => "Join an energy community", "gr" => "Γίνε μέλος μιας ενεργειακής κοινότητας"],
    "auth_register_side_text" => ["en" => "Two short steps and you are in.", "gr" => "Δύο σύντομα βήματα και είσαι μέσα."],
    "auth_point_kinds" => ["en" => "Land, capital, labour or equipment", "gr" => "Γη, κεφάλαιο, εργασία ή εξοπλισμός"],
    "auth_show_password" => ["en" => "Show password", "gr" => "Εμφάνιση κωδικού"],
    "auth_step_account" => ["en" => "Account", "gr" => "Λογαριασμός"],
    "auth_step_details" => ["en" => "Personal details & address", "gr" => "Προσωπικά & διεύθυνση"],
    "auth_username" => ["en" => "Username", "gr" => "Όνομα χρήστη"],
    "auth_continue" => ["en" => "Continue", "gr" => "Συνέχεια"],
    "auth_back" => ["en" => "Back", "gr" => "Πίσω"],
    "auth_password_mismatch" => ["en" => "The passwords do not match.", "gr" => "Οι κωδικοί δεν ταιριάζουν."],
    "register_error_empty" => ["en" => "Please fill in all required fields.", "gr" => "Συμπλήρωσε όλα τα υποχρεωτικά πεδία."],
    "register_error_email" => ["en" => "Please enter a valid e-mail address.", "gr" => "Γράψε ένα έγκυρο email."],
    "register_error_password_short" => ["en" => "The password must have at least 6 characters.", "gr" => "Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες."],
    "register_error_exists" => ["en" => "This username or e-mail is already used by another account.", "gr" => "Αυτό το όνομα χρήστη ή το email χρησιμοποιείται ήδη από άλλον λογαριασμό."],
    "register_error_username" => ["en" => "The username may contain only Latin letters, digits or _ , without spaces, 3 to 15 characters.", "gr" => "Το όνομα χρήστη επιτρέπεται να έχει μόνο λατινικούς χαρακτήρες, αριθμούς ή _ , χωρίς κενά, από 3 έως 15 χαρακτήρες."],
    "register_error_name" => ["en" => "The first name and the surname must not contain digits.", "gr" => "Το όνομα και το επώνυμο δεν πρέπει να περιέχουν ψηφία."],
    "register_error_phone" => ["en" => "The phone must consist of exactly 10 digits, with no other characters.", "gr" => "Το τηλέφωνο πρέπει να αποτελείται από ακριβώς 10 ψηφία, χωρίς άλλους χαρακτήρες."],
    "register_error_email_format" => ["en" => "Enter an e-mail in the form name@example.com.", "gr" => "Γράψε ένα email της μορφής name@example.com."],
    "register_error_postal_code" => ["en" => "The postal code must consist of exactly 5 digits, e.g. 22100.", "gr" => "Ο ΤΚ πρέπει να αποτελείται από ακριβώς 5 ψηφία, π.χ. 22100."],
    "register_error_place" => ["en" => "City, region and location may contain only letters (spaces, \"-\" and \".\" allowed between words), no digits.", "gr" => "Η πόλη, η περιοχή και η τοποθεσία δέχονται μόνο γράμματα (επιτρέπονται κενά, «-» και «.» ανάμεσα στις λέξεις), όχι ψηφία."],
    "invalid_location_title" => ["en" => "Check the location", "gr" => "Έλεγξε την τοποθεσία"],
    "invalid_location_text" => ["en" => "Location / region may contain only letters (spaces, \"-\" and \".\" allowed between words), no digits. Nothing was saved.", "gr" => "Η τοποθεσία/περιοχή δέχεται μόνο γράμματα (επιτρέπονται κενά, «-» και «.» ανάμεσα στις λέξεις), όχι ψηφία. Δεν αποθηκεύτηκε τίποτα."],
    "land_location_missing_title" => ["en" => "Place your land on the map", "gr" => "Βάλε τη γη σου στον χάρτη"],
    "land_location_missing_text" => ["en" => "Click on the map to mark where your land is; it is needed for the solar data (PVGIS). Nothing was saved.", "gr" => "Πάτησε στον χάρτη για να σημειώσεις πού βρίσκεται η γη σου· χρειάζεται για τα ηλιακά δεδομένα (PVGIS). Δεν αποθηκεύτηκε τίποτα."],
    "land_value_error_title" => ["en" => "Land value not calculated", "gr" => "Δεν υπολογίστηκε η αξία της γης"],
    "land_value_error_text" => ["en" => "Check the land area (stremmata) and try again. Nothing was saved.", "gr" => "Έλεγξε την έκταση (στρέμματα) και δοκίμασε ξανά. Δεν αποθηκεύτηκε τίποτα."],
    "profile_save_error_title" => ["en" => "The profile was not saved", "gr" => "Το προφίλ δεν αποθηκεύτηκε"],
    "profile_save_error_text" => ["en" => "Something went wrong while saving. Please try again; if it happens again, contact us.", "gr" => "Κάτι πήγε στραβά κατά την αποθήκευση. Δοκίμασε ξανά· αν ξανασυμβεί, επικοινώνησε μαζί μας."],
    "best_proposal_badge" => ["en" => "Your best match", "gr" => "Η καλύτερη πρόταση για σένα"],
    "best_proposal_note" => ["en" => "It has the largest minimum margin: in this group even the member with the smallest margin is further above their minimum share than in any other proposal.", "gr" => "Έχει το μεγαλύτερο ελάχιστο περιθώριο: σε αυτή την ομάδα, ακόμα και το μέλος με το μικρότερο περιθώριο βρίσκεται πιο πάνω από το ελάχιστό του σε σχέση με κάθε άλλη πρόταση."],
    "create_selected_label" => ["en" => "Selected:", "gr" => "Επιλεγμένοι:"],
    "create_remove_member" => ["en" => "Remove", "gr" => "Αφαίρεση"],
    "eval_reason_not_five" => ["en" => "A community must contain exactly 5 members.", "gr" => "Μια κοινότητα πρέπει να έχει ακριβώς 5 μέλη."],
    "eval_reason_duplicate" => ["en" => "The same member was selected twice.", "gr" => "Το ίδιο μέλος επιλέχθηκε δύο φορές."],
    "eval_reason_no_value" => ["en" => "Every member must have a contribution with a value above zero.", "gr" => "Κάθε μέλος πρέπει να έχει συνεισφορά με αξία πάνω από μηδέν."],
    "eval_reason_bad_minimum" => ["en" => "A member has an invalid minimum share.", "gr" => "Ένα μέλος έχει μη έγκυρο ελάχιστο ποσοστό."],
    "eval_reason_minimum_sum" => ["en" => "The minimum shares of the 5 members add up to more than 100%.", "gr" => "Τα ελάχιστα ποσοστά των 5 μελών αθροίζουν πάνω από 100%."],
    "eval_reason_zero_total" => ["en" => "The total contribution value of the group is zero.", "gr" => "Η συνολική αξία συνεισφοράς της ομάδας είναι μηδέν."],
    "eval_reason_below_minimum" => ["en" => "At least one member would get a Fair Share below their minimum share.", "gr" => "Τουλάχιστον ένα μέλος θα έπαιρνε Fair Share κάτω από το ελάχιστο ποσοστό του."],
    "eval_reason_not_eligible" => ["en" => "One or more of the selected members are no longer available (they joined another community, left the platform or have no contributions).", "gr" => "Ένα ή περισσότερα από τα επιλεγμένα μέλη δεν είναι πλέον διαθέσιμα (μπήκαν σε άλλη κοινότητα, έφυγαν από την πλατφόρμα ή δεν έχουν συνεισφορές)."],
    "eval_reason_valid" => ["en" => "Valid community.", "gr" => "Η κοινότητα είναι έγκυρη."],
    "reuse_button" => ["en" => "Propose this group again", "gr" => "Πρότεινε ξανά αυτή την ομάδα"],
    "reuse_button_hint" => ["en" => "Checks whether the same members are still available and still pass Fair Share; you see a preview before anything is sent.", "gr" => "Ελέγχει αν τα ίδια μέλη είναι ακόμα διαθέσιμα και περνάνε ακόμα το Fair Share· βλέπεις προεπισκόπηση πριν σταλεί οτιδήποτε."],
    "reuse_preview_note" => ["en" => "This is a new proposal with the same group as an earlier one. All members will receive a new invitation and must accept again.", "gr" => "Αυτή είναι νέα πρόταση με την ίδια ομάδα μιας παλαιότερης. Όλα τα μέλη θα λάβουν νέα πρόσκληση και πρέπει να αποδεχτούν ξανά."],
    "reuse_members_unavailable" => ["en" => "This group cannot be proposed again as it was, because some members are no longer available (they joined another community or left the platform): {names}. The other members are already selected below; choose someone in their place.", "gr" => "Η ομάδα δεν μπορεί να προταθεί ξανά όπως ήταν, γιατί κάποια μέλη δεν είναι πλέον διαθέσιμα (μπήκαν σε άλλη κοινότητα ή έφυγαν από την πλατφόρμα): {names}. Τα υπόλοιπα μέλη είναι ήδη επιλεγμένα παρακάτω· διάλεξε κάποιον στη θέση τους."],
    "reuse_deleted_members" => ["en" => "{count} member(s) whose account was deleted", "gr" => "{count} μέλος/μέλη που διέγραψαν τον λογαριασμό τους"],
    "reuse_no_longer_valid" => ["en" => "The same group no longer passes the Fair Share rules; someone has probably changed their contributions or minimum share since then.", "gr" => "Η ίδια ομάδα δεν περνάει πια τους κανόνες Fair Share· μάλλον κάποιος άλλαξε στο μεταξύ τις συνεισφορές ή το ελάχιστο ποσοστό του."],
    "reuse_not_possible" => ["en" => "This proposal cannot be proposed again (only cancelled or declined proposals you were part of).", "gr" => "Αυτή η πρόταση δεν μπορεί να προταθεί ξανά (μόνο ακυρωμένες ή απορριφθείσες προτάσεις στις οποίες ήσουν μέλος)."],
    "price_source_pv_panels" => ["en" => "sun.store PV Index, September 2026 - TOPCon monofacial modules", "gr" => "sun.store PV Index, Σεπτέμβριος 2026 - πάνελ TOPCon μονής όψης"],
    "price_source_battery_storage" => ["en" => "sun.store Battery Index, August 2026 - residential high-voltage batteries", "gr" => "sun.store Battery Index, Αύγουστος 2026 - οικιακές μπαταρίες υψηλής τάσης"],
    "price_source_electrical_equipment" => ["en" => "sun.store PV Index, September 2026 - hybrid inverters 1-20 kW (EnergyMatch assumption: used for all electrical equipment)", "gr" => "sun.store PV Index, Σεπτέμβριος 2026 - υβριδικοί inverter 1-20 kW (παραδοχή EnergyMatch: ισχύει για όλο τον ηλεκτρολογικό εξοπλισμό)"],
    "phone_hint" => ["en" => "Exactly 10 digits, e.g. 6912345678.", "gr" => "Ακριβώς 10 ψηφία, π.χ. 6912345678."],
    "register_error_username_taken" => ["en" => "This username is already used by another account. Please choose another one.", "gr" => "Αυτό το όνομα χρήστη χρησιμοποιείται ήδη από άλλον λογαριασμό. Διάλεξε άλλο."],
    "register_error_email_taken" => ["en" => "This e-mail is already used by another account. If it is yours, sign in or use «Forgot password?».", "gr" => "Αυτό το email χρησιμοποιείται ήδη από άλλον λογαριασμό. Αν είναι δικό σου, συνδέσου ή πάτα «Ξέχασες τον κωδικό;»."],
    "auth_username_hint" => ["en" => "Latin letters, digits or _ , no spaces, up to 15 characters. It cannot be changed later.", "gr" => "Λατινικοί χαρακτήρες, αριθμοί ή _ , χωρίς κενά, έως 15 χαρακτήρες. Δεν αλλάζει αργότερα."],
    "username_locked_hint" => ["en" => "The username is chosen at registration and cannot be changed.", "gr" => "Το όνομα χρήστη ορίζεται στην εγγραφή και δεν μπορεί να αλλάξει."],
    "avail_checking" => ["en" => "Checking…", "gr" => "Έλεγχος…"],
    "avail_username_free" => ["en" => "This username is available.", "gr" => "Το όνομα χρήστη είναι διαθέσιμο."],
    "avail_email_free" => ["en" => "This e-mail is available.", "gr" => "Το email είναι διαθέσιμο."],
    "auth_password_hint" => ["en" => "At least 6 characters.", "gr" => "Τουλάχιστον 6 χαρακτήρες."],
    "reset_done_message" => ["en" => "Your password was changed. You can now sign in with the new password.", "gr" => "Ο κωδικός σου άλλαξε. Μπορείς να συνδεθείς με τον νέο κωδικό."],
    "forgot_title" => ["en" => "Forgot password", "gr" => "Ξέχασες τον κωδικό;"],
    "forgot_no_account" => ["en" => "No account was found with this e-mail.", "gr" => "Δεν βρέθηκε λογαριασμός με αυτό το email."],
    "forgot_continue" => ["en" => "Continue", "gr" => "Συνέχεια"],
    "forgot_lead" => ["en" => "Enter the e-mail of your account to choose a new password.", "gr" => "Γράψε το email του λογαριασμού σου για να ορίσεις νέο κωδικό."],
    "forgot_side_title" => ["en" => "It happens to everyone", "gr" => "Συμβαίνει σε όλους"],
    "forgot_side_text" => ["en" => "Give the e-mail of your account and choose a new password right away. Your community and profile stay as they are.", "gr" => "Δώσε το email του λογαριασμού σου και όρισε αμέσως νέο κωδικό. Η κοινότητα και το προφίλ σου μένουν όπως είναι."],
    "forgot_back_to_signin" => ["en" => "Back to sign in", "gr" => "Πίσω στη σύνδεση"],
    "forgot_invalid_email" => ["en" => "Please enter a valid e-mail address.", "gr" => "Γράψε ένα έγκυρο email."],
    "forgot_server_error" => ["en" => "Something went wrong. Please try again in a moment.", "gr" => "Κάτι πήγε στραβά. Δοκίμασε ξανά σε λίγο."],
    "reset_title" => ["en" => "Choose a new password", "gr" => "Νέος κωδικός"],
    "reset_lead" => ["en" => "New password for", "gr" => "Νέος κωδικός για τον λογαριασμό"],
    "reset_side_title" => ["en" => "Almost done", "gr" => "Σχεδόν έτοιμο"],
    "reset_side_text" => ["en" => "Choose a password you have not used before. After saving, sign in with the new one.", "gr" => "Διάλεξε έναν κωδικό που δεν έχεις ξαναχρησιμοποιήσει. Μετά την αποθήκευση συνδέσου με τον νέο."],
    "reset_new_password" => ["en" => "New password", "gr" => "Νέος κωδικός"],
    "reset_save" => ["en" => "Save new password", "gr" => "Αποθήκευση νέου κωδικού"],
    "reset_password_too_short" => ["en" => "The password must have at least 6 characters.", "gr" => "Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες."],
    "reset_invalid_link" => ["en" => "This page is not valid any more. It may have expired (1 hour) or already been used.", "gr" => "Αυτή η σελίδα δεν ισχύει πια. Μπορεί να έληξε (1 ώρα) ή να έχει ήδη χρησιμοποιηθεί."],
    "reset_request_new" => ["en" => "Start again", "gr" => "Ξεκίνα ξανά"],

    "summary_back_to_summary" => ["en" => "Back to profile summary", "gr" => "Πίσω στη σύνοψη του προφίλ"],
];

function t($key)

{

    global $translations;

    $language = $_SESSION["language"] ?? "en";

    if (isset($translations[$key][$language])) {

        return $translations[$key][$language];

    }

    return $key;

}

?>
