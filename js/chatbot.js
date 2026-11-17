/* =========================================================
   ENERGYMATCH ASSISTANT (chatbot.php)
   The FAQ explains the platform in general; the assistant
   answers about the signed-in user's own situation:
     1. "Where am I?"            next step, with a button
     2. "Why don't I find a group?"  checks the user's data
     3. "Explain my numbers"     value in EUR and possible Fair Share
     4. "How do I ...?"          short steps + "Take me there"
     5. Glossary                 one line per term
     6. Not understood           FAQ + message to the administrator
     7. Greek and English        (EN/GR switch of the platform)
   The suggestion buttons change with the user's state
   (e.g. a proposal waiting -> "What do I do with the proposal?").
   Data: window.EM_CHATBOT (chatbot.php, chatbot_state.php).
========================================================= */

document.addEventListener("DOMContentLoaded", function ()
{
    "use strict";

    const chatbotToggle = document.getElementById("chatbotToggle");
    const chatbotBox = document.getElementById("chatbotBox");
    const chatbotClose = document.getElementById("chatbotClose");
    const chatbotForm = document.getElementById("chatbotForm");
    const chatbotInput = document.getElementById("chatbotInput");
    const chatbotMessages = document.getElementById("chatbotMessages");

    if (!chatbotToggle || !chatbotBox || !chatbotClose || !chatbotForm || !chatbotInput || !chatbotMessages) {
        return;
    }

    const config = window.EM_CHATBOT || { lang: "gr", state: null };
    const GR = config.lang === "gr";
    const S = config.state;   /* null if the data could not be read */

    function L(gr, en) {
        return GR ? gr : en;
    }

    function money(value) {
        return Number(value || 0).toLocaleString(GR ? "el-GR" : "en-GB", { maximumFractionDigits: 0 }) + " €";
    }

    function pct(value) {
        return Number(value || 0).toLocaleString(GR ? "el-GR" : "en-GB", { maximumFractionDigits: 1 }) + "%";
    }

    const TYPE_NAMES = {
        capital: L("Κεφάλαιο", "Capital"),
        land: L("Γη", "Land"),
        manual_labor: L("Χειρωνακτική εργασία", "Manual labour"),
        skilled_labor: L("Εξειδικευμένη εργασία", "Skilled labour"),
        equipment: L("Εξοπλισμός", "Equipment")
    };

    /* ---------- the questions of the buttons (each one matches an answer below) ---------- */

    const Q = {
        status: L("Πού βρίσκομαι;", "Where am I?"),
        proposal: L("Τι κάνω με την πρόταση;", "What do I do with the proposal?"),
        activation: L("Πότε ενεργοποιείται η κοινότητά μου;", "When does my community become active?"),
        diagnose: L("Γιατί δεν βρίσκω ομάδα;", "Why don't I find a group?"),
        numbers: L("Εξήγησέ μου τα νούμερά μου", "Explain my numbers"),
        howto: L("Πώς κάνω…;", "How do I…?"),
        glossary: L("Λεξικό όρων", "Glossary"),
        contribution: L("Τι να δηλώσω ως συνεισφορά;", "What should I declare as a contribution?"),
        chat: L("Πώς μιλάω με την ομάδα μου;", "How do I talk to my group?"),
        leave: L("Πώς αποχωρώ;", "How do I leave?"),
        warning: L("Τι σημαίνει η προειδοποίηση;", "What does the warning mean?"),
        report: L("Πώς κάνω αναφορά;", "How do I report a member?"),
        password: L("Πώς αλλάζω κωδικό;", "How do I change my password?"),
        deleteAccount: L("Πώς διαγράφω τον λογαριασμό;", "How do I delete my account?"),
        create: L("Πώς φτιάχνω δική μου ομάδα;", "How do I create my own group?"),
        termFair: L("Τι είναι το Fair Share;", "What is Fair Share?"),
        termMin: L("Τι είναι το ελάχιστο ποσοστό;", "What is the minimum share?"),
        termValid: L("Πότε μια ομάδα είναι έγκυρη;", "When is a group valid?"),
        termMaximin: L("Τι είναι το maximin;", "What is maximin?"),
        termPvgis: L("Τι είναι το PVGIS;", "What is PVGIS?")
    };

    /* ---------- links ---------- */

    const A = {
        profile: { label: L("Πήγαινε στο Προφίλ", "Go to Profile"), href: "profile.php" },
        proposals: { label: L("Οι προτάσεις μου", "My proposals"), href: "accepted_invitations.php" },
        matches: { label: L("Αντιστοιχίσεις", "Matches"), href: "matches.php" },
        create: { label: L("Χειροκίνητη δημιουργία", "Create manually"), href: "create_community.php" },
        notifications: { label: L("Ειδοποιήσεις", "Notifications"), href: "notifications.php" },
        settings: { label: L("Ρυθμίσεις", "Settings"), href: "settings.php" },
        conduct: { label: L("Κώδικας συμπεριφοράς", "Code of conduct"), href: "conduct.php" },
        faq: { label: L("Συχνές ερωτήσεις", "FAQ"), href: "FAQ.php" },
        contact: { label: L("Μήνυμα στον διαχειριστή", "Message the administrator"), href: "contacts.php" }
    };

    function communityLink(community, label) {
        return { label: label || L("Άνοιγμα κοινότητας", "Open community"), href: "community.php?id=" + community.id };
    }

    function hasContribution() {
        return S && S.hasProfile && S.totalValue > 0;
    }

    function plural(count, one, many) {
        return count === 1 ? one : many;
    }

    /* =====================================================
       ANSWERS: { text: [paragraphs], actions: [links], replies: [questions] }
       **bold** is allowed inside the text.
    ===================================================== */

    function answerNoData() {
        return {
            text: [L("Δεν μπόρεσα να διαβάσω τα στοιχεία σου αυτή τη στιγμή. Δοκίμασε ξανά σε λίγο ή δες τις Συχνές ερωτήσεις.",
                     "I could not read your data right now. Please try again shortly or see the FAQ.")],
            actions: [A.faq]
        };
    }

    function answerNoContribution() {
        return {
            text: [
                L("Δεν έχεις δηλώσει ακόμα **συνεισφορά**, γι' αυτό δεν μπαίνεις σε προτάσεις.",
                  "You have not declared a **contribution** yet, so you are not part of any proposal."),
                L("Στο Προφίλ δήλωσε τι προσφέρεις (κεφάλαιο, γη, εργασία ή εξοπλισμό) και το ελάχιστο ποσοστό που δέχεσαι.",
                  "In your Profile, declare what you offer (capital, land, labour or equipment) and the minimum share you accept.")
            ],
            actions: [A.profile],
            replies: [Q.contribution, Q.termMin]
        };
    }

    /* 1. Where am I? */
    function answerStatus() {
        if (!S) { return answerNoData(); }
        if (!hasContribution()) { return answerNoContribution(); }

        const text = [];
        const actions = [];
        const replies = [];

        if (S.active) {
            text.push(L("Είσαι μέλος της ενεργής κοινότητας **" + S.active.name + "**. Μπορείς να μιλήσεις με την ομάδα στη συνομιλία της.",
                        "You are a member of the active community **" + S.active.name + "**. You can talk to the group in its chat."));
            actions.push(communityLink(S.active));
            replies.push(Q.chat, Q.leave);
        } else if (S.pendingInvites > 0) {
            text.push(L("Έχεις **" + S.pendingInvites + "** " + plural(S.pendingInvites, "πρόταση που περιμένει", "προτάσεις που περιμένουν") + " την απάντησή σου.",
                        "You have **" + S.pendingInvites + "** " + plural(S.pendingInvites, "proposal", "proposals") + " waiting for your answer."));
            actions.push(A.proposals);
            replies.push(Q.proposal);
        }

        if (!S.active && S.waiting) {
            text.push(L("Αποδέχτηκες την πρόταση **" + S.waiting.name + "** και έχουν αποδεχτεί " + S.waiting.accepted + "/5. Η κοινότητα ενεργοποιείται όταν αποδεχτούν και οι 5.",
                        "You accepted **" + S.waiting.name + "** and " + S.waiting.accepted + "/5 have accepted. It becomes active when all 5 accept."));
            actions.push(communityLink(S.waiting));
            replies.push(Q.activation);
        }

        if (!S.active && !S.waiting && S.pendingInvites === 0) {
            text.push(L("Δεν είσαι ακόμα σε κοινότητα. Δες τις πεντάδες που σου προτείνει το σύστημα ή φτιάξε δική σου.",
                        "You are not in a community yet. See the groups of 5 the system suggests, or create your own."));
            actions.push(A.matches, A.create);
            replies.push(Q.diagnose);
        }

        if (S.unread > 0) {
            text.push(L("Έχεις επίσης " + S.unread + " " + plural(S.unread, "αδιάβαστη ειδοποίηση", "αδιάβαστες ειδοποιήσεις") + ".",
                        "You also have " + S.unread + " unread " + plural(S.unread, "notification", "notifications") + "."));
            actions.push(A.notifications);
        }

        if (S.warnings > 0) {
            text.push(L("Προσοχή: έχεις **" + S.warnings + "/5 προειδοποιήσεις** από εγκεκριμένες αναφορές.",
                        "Note: you have **" + S.warnings + "/5 warnings** from approved reports."));
            replies.push(Q.warning);
        }

        replies.push(Q.numbers);
        return { text: text, actions: actions, replies: replies };
    }

    /* proposals waiting for an answer */
    function answerProposal() {
        if (!S) { return answerNoData(); }

        if (S.pendingInvites > 0) {
            return {
                text: [
                    L("Άνοιξε **Οι προτάσεις μου**. Για κάθε πρόταση βλέπεις τα 5 μέλη, τι προσφέρει το καθένα και το Fair Share όλων.",
                      "Open **My proposals**. For each proposal you see the 5 members, what each offers and everyone's Fair Share."),
                    L("Πάτα «Αποδοχή» ή «Απόρριψη». Όταν αποδεχτούν και οι 5, η κοινότητα ενεργοποιείται και ανοίγει η συνομιλία. Αν κάποιος απορρίψει, η πρόταση ακυρώνεται.",
                      "Press \"Accept\" or \"Reject\". When all 5 accept, the community becomes active and its chat opens. If someone rejects, the proposal is cancelled."),
                    L("Μπορείς να αποδεχτείς περισσότερες από μία· όποια ενεργοποιηθεί πρώτη κρατιέται και οι άλλες ακυρώνονται.",
                      "You may accept more than one; the first that becomes active is kept and the others are cancelled.")
                ],
                actions: [A.proposals],
                replies: [Q.termFair, Q.status]
            };
        }

        if (S.waiting) {
            return answerActivation();
        }

        return {
            text: [L("Δεν έχεις πρόταση που περιμένει απάντηση. Νέες προτάσεις βρίσκεις στις Αντιστοιχίσεις.",
                     "You have no proposal waiting for an answer. New proposals are in Matches.")],
            actions: [A.matches, A.proposals],
            replies: [Q.diagnose]
        };
    }

    /* when does my community become active? */
    function answerActivation() {
        if (!S) { return answerNoData(); }

        if (S.active) {
            return {
                text: [L("Η κοινότητά σου **" + S.active.name + "** είναι ήδη ενεργή.",
                         "Your community **" + S.active.name + "** is already active.")],
                actions: [communityLink(S.active)],
                replies: [Q.chat]
            };
        }

        if (S.waiting) {
            const missing = 5 - S.waiting.accepted;
            return {
                text: [
                    L("Στην **" + S.waiting.name + "** έχουν αποδεχτεί " + S.waiting.accepted + "/5. Λείπουν " + missing + " " + plural(missing, "αποδοχή", "αποδοχές") + ".",
                      "In **" + S.waiting.name + "** " + S.waiting.accepted + "/5 have accepted. " + missing + " " + plural(missing, "acceptance is", "acceptances are") + " missing."),
                    L("Μόλις αποδεχτούν όλοι, ενεργοποιείται μόνη της και θα πάρεις ειδοποίηση.",
                      "As soon as everyone accepts, it becomes active by itself and you will get a notification.")
                ],
                actions: [communityLink(S.waiting)],
                replies: [Q.status]
            };
        }

        return answerProposal();
    }

    /* 2. Why don't I find a group? */
    function answerDiagnose() {
        if (!S) { return answerNoData(); }
        if (!hasContribution()) { return answerNoContribution(); }

        if (S.active) {
            return {
                text: [L("Είσαι ήδη στην ενεργή κοινότητα **" + S.active.name + "**, γι' αυτό δεν σου προτείνονται νέες. Κάθε μέλος ανήκει σε μία κοινότητα.",
                         "You are already in the active community **" + S.active.name + "**, so no new ones are suggested. Each member belongs to one community.")],
                actions: [communityLink(S.active)],
                replies: [Q.leave]
            };
        }

        const text = [];
        const actions = [];

        if (S.freeMembers < 4) {
            text.push(L("Μια κοινότητα χρειάζεται εσένα και **4 ακόμα** διαθέσιμα μέλη. Αυτή τη στιγμή διαθέσιμα είναι μόνο **" + S.freeMembers + "**. Μόλις γραφτούν περισσότερα, θα εμφανιστούν προτάσεις.",
                        "A community needs you and **4 more** available members. Right now only **" + S.freeMembers + "** are available. Proposals will appear as more members join."));
            return { text: text, actions: [A.matches], replies: [Q.status] };
        }

        const range = S.shareRange;
        const myMin = S.minShare;

        if (range && range.othersTypical < range.othersMinMedian) {
            /* my contribution is so large that the others fall below their minimum */
            text.push(L("Η συνεισφορά σου (" + money(S.totalValue) + ") είναι πολύ μεγαλύτερη από των άλλων. Σε μια πεντάδα θα έπαιρνες περίπου **" + pct(range.typical) + "**, αλλά κάθε άλλο μέλος μόνο **" + pct(range.othersTypical) + "**, ενώ τα περισσότερα ζητούν τουλάχιστον **" + pct(range.othersMinMedian) + "**.",
                        "Your contribution (" + money(S.totalValue) + ") is much larger than the others'. In a group of 5 you would get about **" + pct(range.typical) + "**, but each other member only **" + pct(range.othersTypical) + "**, while most of them ask for at least **" + pct(range.othersMinMedian) + "**."));
            text.push(L("Γι' αυτό καμία ομάδα δεν είναι έγκυρη για όλους. Αν δηλώσεις μικρότερο μέρος της συνεισφοράς σου, θα χωρέσεις σε ομάδα και θα κρατήσεις μεγάλο ποσοστό.",
                        "That is why no group is valid for everyone. If you declare a smaller part of your contribution, you will fit in a group and still keep a large share."));
            actions.push(A.profile);
        } else if (range && myMin > range.high) {
            text.push(L("Ζητάς ελάχιστο ποσοστό **" + pct(myMin) + "**, αλλά με τη συνεισφορά σου (" + money(S.totalValue) + ") το μεγαλύτερο Fair Share που μπορείς να πάρεις σε πεντάδα είναι περίπου **" + pct(range.high) + "**, ακόμα και με τα 4 μέλη της μικρότερης συνεισφοράς.",
                        "You ask for a minimum share of **" + pct(myMin) + "**, but with your contribution (" + money(S.totalValue) + ") the highest Fair Share you can get in a group of 5 is about **" + pct(range.high) + "**, even with the 4 smallest contributors."));
            text.push(L("Γι' αυτό καμία ομάδα δεν είναι έγκυρη. Χαμήλωσε το ελάχιστο ποσοστό ή πρόσθεσε συνεισφορά.",
                        "That is why no group is valid. Lower your minimum share or add a contribution."));
            actions.push(A.profile);
        } else if (range && myMin > range.typical) {
            text.push(L("Το ελάχιστο που ζητάς (**" + pct(myMin) + "**) είναι πάνω από το συνηθισμένο Fair Share σου (περίπου **" + pct(range.typical) + "**). Έγκυρες ομάδες βγαίνουν μόνο με μέλη μικρότερης συνεισφοράς, άρα οι επιλογές είναι λίγες.",
                        "Your minimum (**" + pct(myMin) + "**) is above your usual Fair Share (about **" + pct(range.typical) + "**). Valid groups only appear with smaller contributors, so there are few options."));
            text.push(L("Αν χαμηλώσεις λίγο το ελάχιστο, θα δεις περισσότερες προτάσεις.",
                        "Lowering your minimum a little will show more proposals."));
            actions.push(A.profile, A.matches);
        } else {
            text.push(L("Από τη δική σου πλευρά όλα είναι εντάξει: ζητάς **" + pct(myMin) + "** και σε μια πεντάδα παίρνεις συνήθως γύρω στο **" + pct(range ? range.typical : 0) + "**.",
                        "Everything is fine on your side: you ask for **" + pct(myMin) + "** and in a group of 5 you usually get about **" + pct(range ? range.typical : 0) + "**."));
            text.push(L("Αν δεν βλέπεις προτάσεις, μάλλον κάποια από τα άλλα μέλη ζητούν υψηλό ελάχιστο. Δοκίμασε τη χειροκίνητη δημιουργία, όπου το σύστημα σου λέει αμέσως αν η ομάδα που διαλέγεις είναι έγκυρη.",
                        "If you see no proposals, some other members probably ask for a high minimum. Try creating a group manually: the system tells you at once if your choice is valid."));
            actions.push(A.matches, A.create);
        }

        if (S.pendingInvites > 0) {
            text.push(L("Σημείωση: έχεις ήδη " + S.pendingInvites + " " + plural(S.pendingInvites, "πρόταση", "προτάσεις") + " σε αναμονή.",
                        "Note: you already have " + S.pendingInvites + " " + plural(S.pendingInvites, "proposal", "proposals") + " waiting."));
            actions.push(A.proposals);
        }

        return { text: text, actions: actions, replies: [Q.numbers, Q.termMin] };
    }

    /* 3. Explain my numbers */
    function answerNumbers() {
        if (!S) { return answerNoData(); }
        if (!hasContribution()) { return answerNoContribution(); }

        const parts = Object.keys(S.contributions)
            .filter(function (type) { return S.contributions[type] > 0; })
            .map(function (type) { return (TYPE_NAMES[type] || type) + ": " + money(S.contributions[type]); });

        const text = [
            L("Η συνεισφορά σου αποτιμάται σε **" + money(S.totalValue) + "** (" + parts.join(", ") + ").",
              "Your contribution is valued at **" + money(S.totalValue) + "** (" + parts.join(", ") + ")."),
            L("Fair Share = η αξία σου / συνολική αξία της πεντάδας × 100.",
              "Fair Share = your value / total value of the group of 5 × 100.")
        ];

        const range = S.shareRange;
        if (range) {
            text.push(L("Με τα σημερινά διαθέσιμα μέλη, σε μια πεντάδα θα έπαιρνες από **" + pct(range.low) + "** έως **" + pct(range.high) + "**. Με 4 μέλη των ~" + money(range.median) + " το καθένα: " + money(S.totalValue) + " / (" + money(S.totalValue) + " + 4 × " + money(range.median) + ") ≈ **" + pct(range.typical) + "**.",
                        "With the members available today, in a group of 5 you would get from **" + pct(range.low) + "** to **" + pct(range.high) + "**. With 4 members of ~" + money(range.median) + " each: " + money(S.totalValue) + " / (" + money(S.totalValue) + " + 4 × " + money(range.median) + ") ≈ **" + pct(range.typical) + "**."));
        }

        text.push(L("Το ελάχιστο που ζήτησες είναι **" + pct(S.minShare) + "**: μια ομάδα είναι έγκυρη για σένα μόνο αν το Fair Share σου είναι τουλάχιστον τόσο.",
                    "Your minimum share is **" + pct(S.minShare) + "**: a group is valid for you only if your Fair Share is at least that."));

        return { text: text, actions: [A.profile, A.matches], replies: [Q.diagnose, Q.termValid] };
    }

    /* 4. How do I ...? */
    function answerHowtoMenu() {
        return {
            text: [L("Τι θέλεις να κάνεις;", "What would you like to do?")],
            replies: [Q.contribution, Q.create, Q.chat, Q.leave, Q.report, Q.password, Q.deleteAccount]
        };
    }

    function answerContribution() {
        return {
            text: [
                L("Στο **Προφίλ** δηλώνεις ό,τι προσφέρεις: κεφάλαιο (ποσό), γη (έκταση και σημείο στον χάρτη), χειρωνακτική ή εξειδικευμένη εργασία (ώρες) και εξοπλισμό.",
                  "In your **Profile** you declare what you offer: capital (amount), land (area and point on the map), manual or skilled labour (hours) and equipment."),
                L("Το σύστημα τα αποτιμά σε € με πραγματικά δεδομένα. Για τη γη, το PVGIS ελέγχει και πόσο ήλιο παίρνει το σημείο.",
                  "The system values them in € with real data. For land, PVGIS also checks how much sun the spot gets.")
            ],
            actions: [A.profile],
            replies: [Q.numbers, Q.termPvgis]
        };
    }

    function answerCreate() {
        return {
            text: [L("Στη **Χειροκίνητη δημιουργία** διαλέγεις 4 μέλη (με φίλτρα για όνομα/username, περιοχή και τύπο συνεισφοράς). Το σύστημα δείχνει αμέσως αν η ομάδα είναι έγκυρη και το Fair Share του καθενός· μετά στέλνεται πρόταση και στους 5.",
                     "In **Create manually** you pick 4 members (filters for name/username, region and contribution type). The system shows at once if the group is valid and everyone's Fair Share; then a proposal goes to all 5.")],
            actions: [A.create],
            replies: [Q.termValid]
        };
    }

    function answerChat() {
        if (S && S.active) {
            return {
                text: [L("Η συνομιλία της ομάδας είναι στη σελίδα της κοινότητας **" + S.active.name + "**. Γράφεις και πατάς Enter (Shift+Enter για νέα γραμμή).",
                         "The group chat is on the page of **" + S.active.name + "**. Type and press Enter (Shift+Enter for a new line).")],
                actions: [communityLink(S.active, L("Άνοιγμα συνομιλίας", "Open chat"))]
            };
        }
        return {
            text: [L("Η συνομιλία ανοίγει μόνο όταν η κοινότητα γίνει ενεργή, δηλαδή όταν αποδεχτούν και τα 5 μέλη." + (S && S.waiting ? " Στην **" + S.waiting.name + "** έχουν αποδεχτεί " + S.waiting.accepted + "/5." : ""),
                     "The chat opens only when the community is active, i.e. when all 5 members have accepted." + (S && S.waiting ? " In **" + S.waiting.name + "** " + S.waiting.accepted + "/5 have accepted." : ""))],
            actions: S && S.waiting ? [communityLink(S.waiting)] : [A.proposals],
            replies: [Q.activation]
        };
    }

    function answerLeave() {
        if (S && S.active) {
            return {
                text: [
                    L("Στη σελίδα της κοινότητας πάτα «Αποχώρηση» και διάλεξε τον λόγο. Τα άλλα μέλη ενημερώνονται.",
                      "On the community page press \"Leave\" and choose a reason. The other members are informed."),
                    L("Επειδή οι κοινότητες είναι σταθερές πεντάδες, η **" + S.active.name + "** θα ακυρωθεί και όλοι, κι εσύ, μπορείτε να ψάξετε νέα.",
                      "Because communities are fixed groups of 5, **" + S.active.name + "** will be cancelled and everyone, you included, can look for a new one.")
                ],
                actions: [{ label: L("Φόρμα αποχώρησης", "Leave form"), href: "leave_community.php?community_id=" + S.active.id }]
            };
        }
        return {
            text: [L("Αποχώρηση γίνεται από ενεργή κοινότητα, και δεν είσαι σε κάποια τώρα. Μια πρόταση σε αναμονή απλώς την απορρίπτεις από «Οι προτάσεις μου».",
                     "You leave from an active community, and you are not in one now. A waiting proposal is simply rejected from \"My proposals\".")],
            actions: [A.proposals]
        };
    }

    function answerReport() {
        return {
            text: [
                L("Αναφέρεις μέλος της δικής σου κοινότητας: στη σελίδα της κοινότητας, δίπλα στο μέλος, πάτα «Αναφορά» και διάλεξε λόγο.",
                  "You report a member of your own community: on the community page, next to the member, press \"Report\" and choose a reason."),
                L("Ο διαχειριστής την εξετάζει και παίρνεις ειδοποίηση με την απόφαση. Το μέλος δεν μαθαίνει ποιος το ανέφερε.",
                  "The administrator reviews it and you get a notification with the decision. The member does not learn who reported them.")
            ],
            actions: (S && (S.active || S.waiting)) ? [communityLink(S.active || S.waiting), A.conduct] : [A.conduct]
        };
    }

    function answerPassword() {
        return {
            text: [L("Στις **Ρυθμίσεις**, στην ενότητα του κωδικού, γράφεις τον τωρινό και τον νέο (τουλάχιστον 6 χαρακτήρες). Η αλλαγή σε αποσυνδέει από τους άλλους browsers όπου είχες «Να με θυμάσαι».",
                     "In **Settings**, in the password section, type the current and the new one (at least 6 characters). Changing it signs you out of other browsers where \"Remember me\" was on.")],
            actions: [A.settings]
        };
    }

    function answerDeleteAccount() {
        return {
            text: [
                L("Στις **Ρυθμίσεις**, στο τέλος, πληκτρολόγησε DELETE και επιβεβαίωσε.",
                  "In **Settings**, at the bottom, type DELETE and confirm."),
                L("Είναι οριστικό: σβήνονται προφίλ, συνεισφορές και μηνύματα, και η κοινότητά σου ακυρώνεται με ενημέρωση των μελών.",
                  "It is permanent: profile, contributions and messages are deleted, and your community is cancelled with the members informed.")
            ],
            actions: [A.settings]
        };
    }

    function answerWarning() {
        const count = S ? S.warnings : 0;
        return {
            text: [
                (count > 0
                    ? L("Έχεις **" + count + "/5** προειδοποιήσεις. ", "You have **" + count + "/5** warnings. ")
                    : L("Δεν έχεις καμία προειδοποίηση. ", "You have no warnings. "))
                + L("Κάθε αναφορά εις βάρος σου που εγκρίνει ο διαχειριστής είναι μία προειδοποίηση· πολλές από το ίδιο μέλος μετράνε μία.",
                    "Each report against you that the administrator approves is one warning; several from the same member count once."),
                L("Στις **5** ο λογαριασμός διαγράφεται. Δες τον Κώδικα συμπεριφοράς για το τι επιτρέπεται.",
                  "At **5** the account is deleted. See the Code of conduct for what is allowed.")
            ],
            actions: [A.conduct, A.notifications]
        };
    }

    /* 5. Glossary */
    const TERMS = {
        fair: [L("**Fair Share**: το ποσοστό σου στην κοινότητα = η αξία της συνεισφοράς σου / η συνολική αξία των 5 × 100.",
                 "**Fair Share**: your share of the community = the value of your contribution / the total value of the 5 × 100."), [Q.numbers]],
        min: [L("**Ελάχιστο ποσοστό (Minimum Share)**: το μικρότερο Fair Share που δέχεσαι. Το ορίζεις στο Προφίλ.",
                "**Minimum share**: the smallest Fair Share you accept. You set it in your Profile."), [Q.diagnose]],
        valid: [L("**Έγκυρη ομάδα**: πεντάδα όπου κάθε μέλος παίρνει Fair Share τουλάχιστον ίσο με το ελάχιστό του.",
                  "**Valid group**: a group of 5 where every member gets a Fair Share at least equal to their minimum."), [Q.termMaximin]],
        maximin: [L("**Maximin**: ο τρόπος κατάταξης. Πρώτη μπαίνει η ομάδα όπου και το μέλος με το μικρότερο περιθώριο (Fair Share μείον ελάχιστο) είναι όσο γίνεται πιο πάνω.",
                    "**Maximin**: how groups are ranked. First comes the group where even the member with the smallest margin (Fair Share minus minimum) is as high as possible."), [Q.termValid]],
        pvgis: [L("**PVGIS**: υπηρεσία της Ευρωπαϊκής Επιτροπής που δίνει την ηλιακή ακτινοβολία ενός σημείου. Τη χρησιμοποιούμε για τη βαθμολογία καταλληλότητας της γης.",
                  "**PVGIS**: a European Commission service that gives the solar radiation of a location. We use it for the suitability score of land."), [Q.contribution]]
    };

    function answerTerm(key) {
        return { text: [TERMS[key][0]], replies: TERMS[key][1].concat([Q.glossary]) };
    }

    function answerGlossary() {
        return {
            text: [L("Ποιον όρο θέλεις να σου εξηγήσω;", "Which term should I explain?")],
            replies: [Q.termFair, Q.termMin, Q.termValid, Q.termMaximin, Q.termPvgis]
        };
    }

    /* 6. Not understood */
    function answerFallback() {
        return {
            text: [L("Δεν είμαι σίγουρος ότι κατάλαβα. Μπορώ να σου πω πού βρίσκεσαι, γιατί δεν βρίσκεις ομάδα, τι σημαίνουν τα νούμερά σου ή πώς γίνεται κάτι. Για κάτι άλλο, δες τις Συχνές ερωτήσεις ή στείλε μήνυμα στον διαχειριστή.",
                     "I am not sure I understood. I can tell you where you stand, why you don't find a group, what your numbers mean or how to do something. For anything else, see the FAQ or message the administrator.")],
            actions: [A.faq, A.contact],
            replies: [Q.status, Q.diagnose, Q.numbers, Q.howto]
        };
    }

    /* =====================================================
       WHICH ANSWER? (accents and case ignored; order matters)
    ===================================================== */

    function normalizeText(text) {
        return String(text || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[̀-ͯ]/g, "")
            .replace(/[.,!?;:…«»"()]/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    function has(message, words) {
        return words.some(function (word) { return message.includes(normalizeText(word)); });
    }

    const RULES = [
        [function (m) { return m.length < 25 && has(m, ["γεια", "καλημερα", "καλησπερα", "hello", "hi ", "hey"]) || m === "hi"; }, function () {
            return { text: [L("Γεια σου! Τι θέλεις να μάθεις;", "Hi! What would you like to know?")], replies: suggestions() };
        }],
        [function (m) { return has(m, ["ευχαριστ", "thank", "thanks"]); }, function () {
            return { text: [L("Παρακαλώ! Είμαι εδώ αν χρειαστείς κάτι άλλο.", "You're welcome! I'm here if you need anything else.")] };
        }],
        [function (m) { return has(m, ["προειδοποιησ", "warning"]); }, answerWarning],
        [function (m) { return has(m, ["δεν βρισκω", "γιατι δεν", "καμια ομαδα", "δεν εχω ομαδα", "δεν μου βγαζει", "δεν βλεπω προτασ", "no group", "can't find", "cant find", "why don't", "why dont", "no proposal"]); }, answerDiagnose],
        [function (m) { return has(m, ["νουμερα", "αξια μου", "fair share μου", "ποσοστο μου", "μεριδιο μου", "ποσο αξιζ", "my numbers", "my value", "my share", "my fair share"]); }, answerNumbers],
        [function (m) { return has(m, ["maximin"]); }, function () { return answerTerm("maximin"); }],
        [function (m) { return has(m, ["pvgis", "ηλιακ", "solar"]); }, function () { return answerTerm("pvgis"); }],
        [function (m) { return has(m, ["ελαχιστο ποσοστο", "minimum share", "minimum", "ελαχιστο"]); }, function () { return answerTerm("min"); }],
        [function (m) { return has(m, ["εγκυρ", "valid"]); }, function () { return answerTerm("valid"); }],
        [function (m) { return has(m, ["fair share", "δικαιο μεριδιο"]); }, function () { return answerTerm("fair"); }],
        [function (m) { return has(m, ["λεξικο", "ορων", "ορος", "glossary", "term"]); }, answerGlossary],
        [function (m) { return has(m, ["αποχωρ", "να φυγω", "leave"]); }, answerLeave],
        [function (m) { return has(m, ["αναφορ", "report"]); }, answerReport],
        [function (m) { return has(m, ["κωδικ", "password"]); }, answerPassword],
        [function (m) { return has(m, ["διαγραφ", "delete"]); }, answerDeleteAccount],
        [function (m) { return has(m, ["συνομιλ", "chat", "μιλαω", "μιλησω", "μηνυμα στην ομαδα", "talk"]); }, answerChat],
        [function (m) { return has(m, ["δημιουργ", "χειροκινητ", "δικη μου ομαδα", "create", "my own group"]); }, answerCreate],
        [function (m) { return has(m, ["συνεισφορ", "δηλωσ", "προφιλ", "contribution", "declare", "profile"]); }, answerContribution],
        [function (m) { return has(m, ["πως κανω", "πως μπορω", "how do i", "how to", "how can i"]); }, answerHowtoMenu],
        [function (m) { return has(m, ["ενεργοποι", "activ", "ποτε"]); }, answerActivation],
        [function (m) { return has(m, ["προτασ", "προσκλησ", "αποδοχ", "αποδεχ", "απορρι", "proposal", "invitation", "accept", "reject"]); }, answerProposal],
        [function (m) { return has(m, ["που βρισκομαι", "τι κανω τωρα", "τι να κανω", "επομενο βημα", "κατασταση", "where am i", "next step", "status", "what now"]); }, answerStatus]
    ];

    function getAnswer(message) {
        const m = normalizeText(message);
        for (const rule of RULES) {
            if (rule[0](m)) {
                return rule[1]();
            }
        }
        return answerFallback();
    }

    /* =====================================================
       SUGGESTIONS THAT FOLLOW THE USER'S STATE (max 4)
    ===================================================== */

    function suggestions() {
        const list = [];

        if (!S) {
            list.push(Q.glossary, Q.howto);
        } else if (!hasContribution()) {
            list.push(Q.contribution, Q.status, Q.termFair, Q.howto);
        } else {
            if (S.pendingInvites > 0) { list.push(Q.proposal); }
            if (!S.active && S.waiting) { list.push(Q.activation); }
            if (S.active) { list.push(Q.chat, Q.leave); }
            if (S.warnings > 0) { list.push(Q.warning); }
            if (!S.active && !S.waiting && S.pendingInvites === 0) { list.push(Q.diagnose); }
            list.push(Q.status, Q.numbers, Q.howto, Q.glossary);
        }

        return list.filter(function (item, index) { return list.indexOf(item) === index; }).slice(0, 4);
    }

    function greeting() {
        const name = S && S.name ? ", " + S.name : "";
        const text = [L("Γεια σου" + name + "! Μπορώ να σου πω πού βρίσκεσαι, γιατί δεν βρίσκεις ομάδα ή τι σημαίνουν τα νούμερά σου.",
                        "Hi" + name + "! I can tell you where you stand, why you don't find a group or what your numbers mean.")];

        if (S && S.pendingInvites > 0) {
            text.push(L("Έχεις **" + S.pendingInvites + "** " + plural(S.pendingInvites, "πρόταση που περιμένει", "προτάσεις που περιμένουν") + " απάντηση.",
                        "You have **" + S.pendingInvites + "** " + plural(S.pendingInvites, "proposal", "proposals") + " waiting for an answer."));
        } else if (S && !hasContribution()) {
            text.push(L("Πρώτο βήμα: δήλωσε τη συνεισφορά σου στο Προφίλ.", "First step: declare your contribution in your Profile."));
        }

        return { text: text, replies: suggestions() };
    }

    /* =====================================================
       DRAWING THE MESSAGES
    ===================================================== */

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    /* text is escaped first; only **bold** becomes <strong> */
    function formatText(text) {
        return escapeHtml(text).replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
    }

    function getCurrentTime() {
        return new Date().toLocaleTimeString(GR ? "el-GR" : "en-GB", { hour: "2-digit", minute: "2-digit" });
    }

    function scrollToBottom() {
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function addUserMessage(message) {
        const wrapper = document.createElement("div");
        wrapper.className = "chatbot-message user-message";
        const content = document.createElement("div");
        content.className = "message-content";
        const paragraph = document.createElement("p");
        paragraph.textContent = message;
        const time = document.createElement("span");
        time.className = "message-time";
        time.textContent = getCurrentTime();
        content.append(paragraph, time);
        wrapper.appendChild(content);
        chatbotMessages.appendChild(wrapper);
    }

    function addBotMessage(answer) {
        const wrapper = document.createElement("div");
        wrapper.className = "chatbot-message bot-message";

        const avatar = document.createElement("div");
        avatar.className = "message-avatar";
        avatar.innerHTML = '<i data-lucide="sun"></i>';

        const content = document.createElement("div");
        content.className = "message-content";

        (answer.text || []).forEach(function (line) {
            const paragraph = document.createElement("p");
            paragraph.innerHTML = formatText(line);
            content.appendChild(paragraph);
        });

        if (answer.actions && answer.actions.length) {
            const actions = document.createElement("div");
            actions.className = "chatbot-actions";
            answer.actions.forEach(function (action) {
                const link = document.createElement("a");
                link.className = "chatbot-action";
                link.href = action.href;
                link.innerHTML = escapeHtml(action.label) + ' <i data-lucide="arrow-right"></i>';
                actions.appendChild(link);
            });
            content.appendChild(actions);
        }

        const time = document.createElement("span");
        time.className = "message-time";
        time.textContent = getCurrentTime();
        content.appendChild(time);

        wrapper.append(avatar, content);
        chatbotMessages.appendChild(wrapper);

        if (answer.replies && answer.replies.length) {
            const replies = document.createElement("div");
            replies.className = "chatbot-suggestions";
            answer.replies.forEach(function (question) {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "suggestion-button";
                button.textContent = question;
                button.addEventListener("click", function () { sendMessage(question); });
                replies.appendChild(button);
            });
            chatbotMessages.appendChild(replies);
        }

        if (window.emRefreshIcons) {
            window.emRefreshIcons();
        }
    }

    function showTypingIndicator() {
        const wrapper = document.createElement("div");
        wrapper.className = "chatbot-message bot-message";
        wrapper.innerHTML = '<div class="message-avatar"><i data-lucide="sun"></i></div>'
            + '<div class="chatbot-typing"><span></span><span></span><span></span></div>';
        chatbotMessages.appendChild(wrapper);
        if (window.emRefreshIcons) {
            window.emRefreshIcons();
        }
        scrollToBottom();
        return wrapper;
    }

    function sendMessage(message) {
        /* older suggestion buttons are removed, so only the current ones can be clicked */
        chatbotMessages.querySelectorAll(".chatbot-suggestions").forEach(function (box) { box.remove(); });

        addUserMessage(message);
        scrollToBottom();

        const typing = showTypingIndicator();
        window.setTimeout(function () {
            typing.remove();
            addBotMessage(getAnswer(message));
            scrollToBottom();
        }, 500);
    }

    /* ---------- open / close / send ---------- */

    let greeted = false;

    chatbotToggle.addEventListener("click", function () {
        const isOpen = chatbotBox.classList.toggle("chatbot-open");
        chatbotBox.setAttribute("aria-hidden", isOpen ? "false" : "true");

        if (isOpen) {
            if (!greeted) {
                addBotMessage(greeting());
                greeted = true;
            }
            chatbotInput.focus();
            scrollToBottom();
        }
    });

    chatbotClose.addEventListener("click", function () {
        chatbotBox.classList.remove("chatbot-open");
        chatbotBox.setAttribute("aria-hidden", "true");
    });

    chatbotForm.addEventListener("submit", function (event) {
        event.preventDefault();
        const message = chatbotInput.value.trim();
        if (message === "") {
            return;
        }
        sendMessage(message);
        chatbotInput.value = "";
    });
});
