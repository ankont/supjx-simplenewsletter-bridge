# Simple Newsletter Frontend Bridge — com_newsletterbridge

Ανεξάρτητο Joomla component για διαχείριση newsletter από το frontend, χωρίς πρόσβαση στο administrator. Έκδοση **1.0.0**, creationDate **2026-10-08**. Δεν τροποποιεί το Simple Newsletter και δεν εξαρτάται από SmartBrowser, Catichisi ή συγκεκριμένο template.

## Εγκατάσταση και menu item

1. Εγκαταστήστε και ενεργοποιήστε το παρεχόμενο **Simple Newsletter 1.3.0**.
2. Εγκαταστήστε το [dist/com_newsletterbridge-1.0.0.zip](dist/com_newsletterbridge-1.0.0.zip) από **System → Install → Extensions**. Το manifest είναι στη ρίζα του ZIP.
3. Στο **Components → Simple Newsletter Frontend Bridge → Options**, ορίστε τον editor και τα δικαιώματα. Προεπιλογή είναι ο native **TinyMCE**, ανεξάρτητα από τον global editor. Άλλος editor πρέπει να επιτρέπει frontend authoring και να υποστηρίζει το Joomla editor API. Υπάρχει fallback στον editor None αν ο επιλεγμένος δεν είναι ενεργός.
4. Δημιουργήστε frontend στοιχείο μενού τύπου **Simple Newsletter Frontend Bridge → Newsletter management / Διαχείριση newsletter**. Περιορίστε το Access Level σε Registered ή σε κατάλληλο δικό σας View Level. Το menu access δεν αντικαθιστά το component ACL.
5. Χρησιμοποιήστε το frontend menu για λίστα, δημιουργία, επεξεργασία, προεπισκόπηση και αποστολές. Αποθηκεύστε πριν από προεπισκόπηση ή αποστολή.

Στο εξουσιοδοτημένο local Joomla **http://localhost:8089/** έχει εγκατασταθεί το bridge και έχει δημιουργηθεί native Registered menu item **Newsletter management**, Itemid **219**. Η διαδρομή του είναι **/newsletter-management.html**. Τα προσωρινά test users, ACL grants, newsletters και template override αφαιρέθηκαν μετά τις δοκιμές. Ορίστε τα πραγματικά ACL της ομάδας σας από τις Επιλογές· δεν δόθηκαν μόνιμα νέα δικαιώματα σε υπάρχουσες ομάδες.

Η απεγκατάσταση του bridge δεν διαγράφει newsletters, jobs ή subscribers του Simple Newsletter. Δεν υπάρχει δικό του schema ή SQL εγκατάστασης που μεταβάλλει εκείνους τους πίνακες.

## ACL

Όλες οι παρακάτω ενέργειες ελέγχονται στον server. Απαιτείται συνδεδεμένος χρήστης και **newsletterbridge.access** στο **com_newsletterbridge**.

| Δικαίωμα | Συμπεριφορά |
| --- | --- |
| newsletterbridge.access | Είσοδος στην περιοχή frontend. Από μόνο του δεν δίνει πρόσβαση σε περιεχόμενο άλλων χρηστών. |
| core.create | Δημιουργία νέων newsletter. Ο owner ορίζεται από τον server. |
| core.edit | Ανάγνωση και επεξεργασία όλων των newsletter. |
| core.edit.own | Ανάγνωση και επεξεργασία μόνο newsletter με created_by ίσο με τον τρέχοντα χρήστη. |
| core.edit.state | Αλλαγή published/unpublished, επιπλέον του δικαιώματος επεξεργασίας του συγκεκριμένου newsletter. |
| newsletterbridge.send | Test email, δημιουργία και διαχείριση jobs για newsletter στα οποία ο χρήστης έχει πρόσβαση. |
| simplenewsletter.send στο com_simplenewsletter | **Επιπλέον υποχρεωτικό** δικαίωμα για κάθε ενέργεια αποστολής και πρόσβαση σε jobs. |
| core.manage / core.options | Μόνο πρόσβαση στη σελίδα ρυθμίσεων του bridge στο administrator. Δεν απαιτούνται για frontend χρήση. |

Προτεινόμενη ομάδα author: access, create, edit.own. Προτεινόμενη ομάδα publisher: τα προηγούμενα και edit.state. Προτεινόμενη ομάδα sender: δικαιώματα πρόσβασης στο newsletter και τα **δύο** send grants. Το bridge δεν απαιτεί core.manage στο com_simplenewsletter.

Χωρίς edit.own ή edit, ένας δημιουργός μπορεί να δημιουργήσει αλλά δεν μπορεί να ξανανοίξει τα αποθηκευμένα του newsletter. Τα αρχικά upstream templates με created_by=0 είναι προσβάσιμα μόνο με edit όλων. Δεν υπάρχει χωριστό read-all: η ανάγνωση ακολουθεί το scope επεξεργασίας.

Τα jobs ελέγχονται ως προς το newsletter **και** τον δημιουργό του job. Ένας χρήστης με edit.own διαχειρίζεται μόνο δικά του jobs πάνω σε δικά του newsletters. Ένας χρήστης με core.edit και τα δύο send grants μπορεί να διαχειριστεί jobs άλλων δημιουργών. Η πολιτική επανελέγχεται για status, process, resume και cancel, ακόμη και αν τα δικαιώματα άλλαξαν μετά την έναρξη.

Δεν διατίθενται endpoints subscribers, tokens, recipient details, queue errors ή SMTP credentials. Τα status payloads περιέχουν μόνο IDs, κατάσταση και αριθμητικά totals. Τα σφάλματα προς τον author είναι γενικά μηνύματα, χωρίς raw database ή mailer exceptions.

## Editor, presets και προεπισκόπηση

Τα newsletters αντιστοιχούν στον πίνακα templates του Simple Newsletter. Οι έξι έτοιμες διατάξεις διαβάζονται από το εγκατεστημένο PresetHelper, με ακριβή allowlist ID. Η εφαρμογή preset αντικαθιστά το περιεχόμενο και το θέμα της φόρμας μετά από επιβεβαίωση· δεν αποθηκεύει μόνη της.

Το body φιλτράρεται από τα Joomla Text Filters για τις ομάδες του χρήστη. Οι editors μπορεί επίσης να αφαιρέσουν inline styles, πίνακες ή ολόκληρο HTML document. Δεν παρακάμπτονται τα φίλτρα για διατήρηση ενός layout. Ελέγξτε το αποτέλεσμα της αποθήκευσης και το test email πριν από κανονική αποστολή. Τα editor extension buttons είναι απενεργοποιημένα, ώστε το bridge να μην απαιτεί διαχειριστές media ή άλλα extensions.

Η προεπισκόπηση χρησιμοποιεί **αποθηκευμένο** περιεχόμενο και γενικά sample data. Εμφανίζεται σε frontend srcdoc iframe με άδειο sandbox και CSP, χωρίς scripts, forms ή navigation. Δεν φορτώνει administrator page. Οι εξωτερικές εικόνες μπορούν να φορτωθούν· η εμφάνιση σε πραγματικούς mail clients ενδέχεται να διαφέρει.

Το δοκιμαστικό email απαιτεί και τα δύο send grants. Χρησιμοποιεί πραγματικές ρυθμίσεις αποστολέα, αλλά μόνο sample name/email και κενό token. Δεν καλεί το upstream sendTest, το οποίο διαβάζει πραγματικό όνομα και token όταν η διεύθυνση είναι εγγεγραμμένη. Αποδέκτης του test είναι μόνο η διεύθυνση που συμπληρώθηκε στη φόρμα, χωρίς lookup της λίστας συνδρομητών.

Τα νέα newsletters ξεκινούν αδημοσίευτα. Απαιτείται published=1 για κανονική αποστολή. Το test και η προεπισκόπηση επιτρέπονται και σε αδημοσίευτα, εντός των αντίστοιχων ACL.

## Jobs, batching και όρια αξιοπιστίας

Η δημιουργία job απαιτεί ρητή επιβεβαίωση αποστολής σε όλους τους confirmed subscribers. Το upstream createJob κάνει snapshot subject/body και δημιουργεί queue. Μπορεί επίσης να συμπληρώσει κενά subscriber tokens: αυτό γίνεται μόνο κατά την εξουσιοδοτημένη έναρξη κανονικής αποστολής, ποτέ κατά την επιθεώρηση ή το άνοιγμα σελίδων.

Η δημιουργία job εκτελείται σε InnoDB transaction. Ενεργό job για το ίδιο newsletter εμποδίζει νέα έναρξη. Connection-scoped MySQL/MariaDB named lock συντονίζει start/process/resume/cancel ανάμεσα σε bridge sessions και workers. Η δημιουργία και επεξεργασία χρησιμοποιούν row locks και το πραγματικό checkout/checkin του upstream TemplateModel.

Ο browser εκτελεί τις παρτίδες σειριακά. Οι παρτίδες περιορίζονται σε έως 25 recipients και λαμβάνεται υπόψη το upstream throttle. Το κουμπί Pause ή το κλείσιμο της σελίδας διακόπτει μετά την τρέχουσα παρτίδα. Το Resume συνεχίζει μόνο pending queue rows. Το Cancel είναι τελικό και δεν ανακαλεί ήδη σταλμένα μηνύματα. Finished/cancelled jobs δεν υποβάλλονται ξανά σε processBatch. Δεν γίνονται αυτόματα retries σε HTTP failures ή αποτυχημένους recipients.

Τα καταγεγραμμένα aggregate queue states χρησιμοποιούνται για ανάκτηση sent/failed counters μετά από διακοπή worker, όταν η ουρά παραμένει πλήρης. Το bridge καταστέλλει την αυτόματη **global** εκκαθάριση παλαιών queues που κάνει το upstream finish(): αλλάζει μόνο το in-memory queue_retention κατά το process call και το επαναφέρει σε finally. Δεν αλλάζει αποθηκευμένες ρυθμίσεις ή ξένα παλαιά jobs.

Υπάρχουν περιορισμοί του αμετάβλητου upstream sender:

- Δεν υπάρχει εγγύηση exactly-once. Διακοπή μετά την παράδοση email αλλά πριν από καταγραφή queue state μπορεί να οδηγήσει σε διπλό μήνυμα κατά το resume. Ελέγξτε πριν συνεχίσετε μετά από σοβαρό crash.
- Μην επεξεργάζεστε το ίδιο job ταυτόχρονα από το administrator του Simple Newsletter και το bridge. Τα upstream administrator endpoints δεν συμμετέχουν στο bridge lock.
- Το recipient snapshot δεν επανελέγχει unsubscribe/state αλλαγές μεταξύ παρτίδων. Η πολιτική αυτή ανήκει στο Simple Newsletter 1.3.0.
- Η επιστροφή του upstream mailer καταγράφεται ως sent όταν δεν υπάρξει exception· αυτό δεν εγγυάται τελική παράδοση ή αποδοχή από το inbox.
- Η διαγραφή ή εκκαθάριση ουρών από administrator επηρεάζει τη δυνατότητα resume. Το bridge δεν προσφέρει αλλαγή παραληπτών, επανάληψη failed rows ή background scheduler.

## Συμβατότητα

Στόχος API: Joomla **5.4.x ή 6.x**, PHP υποστηριζόμενη από την αντίστοιχη Joomla έκδοση, MySQL/MariaDB με **InnoDB** και GET_LOCK/RELEASE_LOCK. **Λειτουργικά ελέγχθηκε στο Joomla 6.1.3 του local Docker περιβάλλοντος.** Το Joomla 5.4 δεν δοκιμάστηκε λειτουργικά σε αυτή τη συνεδρία.

Υποστηρίζεται αποκλειστικά το παρεχόμενο **Simple Newsletter 1.3.0**, με την αναμενόμενη δομή των τεσσάρων πινάκων. Το compatibility.json περιέχει SHA-256 των κρίσιμων models/table/helpers, με κανονικοποίηση CRLF. Διαφορετική έκδοση ή αλλαγμένα κρίσιμα αρχεία απορρίπτονται με ευανάγνωστο μήνυμα. Πριν επιτραπεί άλλη έκδοση, χρειάζονται νέο audit, ενημέρωση contract και integration tests. Δεν αρκεί αλλαγή της δηλωμένης έκδοσης.

Τα models δημιουργούνται μέσω του bootComponent και του upstream MVCFactory με ρητό Administrator prefix και ignore_request=true, εντός **site application**. Δεν γίνεται dispatch upstream administrator controller. Το AdminModel::save δεν χρησιμοποιείται, επειδή εφαρμόζει upstream ACL για την κατάσταση και administrator model events. Γίνεται ελεγχόμενη αποθήκευση με το upstream MVCFactory table, allowlist fields και bridge ACL. Τα TemplateModel checkout/checkin, SendModel createJob/processBatch/cancelJob και τα helpers επαναχρησιμοποιούνται όπου ελέγχθηκαν ασφαλή.

## Native template overrides

Αντιγράψτε τα αντίστοιχα αρχεία στα παρακάτω paths του template σας:

- templates/YOUR_TEMPLATE/html/com_newsletterbridge/newsletters/default.php
- templates/YOUR_TEMPLATE/html/com_newsletterbridge/newsletter/default.php
- templates/YOUR_TEMPLATE/html/com_newsletterbridge/preview/default.php
- templates/YOUR_TEMPLATE/html/com_newsletterbridge/send/default.php

Η απόδοση γίνεται μέσω Joomla HtmlView και parent::display. Τα data attributes χρησιμοποιούνται από το bridge.js· διατηρήστε τα στα overrides αν θέλετε presets και batch controls. Τα ACL δεν εξαρτώνται από τα layouts. Το component παρέχει scoped CSS χωρίς υπόθεση συγκεκριμένου template ή framework.

## Tests και build

Το workspace διαθέτει PHP CLI. Εκτελέστε:

~~~text
php tests/unit.php
php scripts/build.php
php tests/package.php
~~~

Το build γράφει ZIP με manifest στη ρίζα και αποκλειστικά Linux-style separators (/). Διατηρεί μόνο το τελευταίο com_newsletterbridge-*.zip μέσα στο dist, χωρίς να επηρεάζει το reference ZIP.

Οι opt-in Joomla integration runners στο tests χρειάζονται εγκατεστημένο bridge και Simple Newsletter, site bootstrap, πρόσβαση σε MySQL/MariaDB και δικαίωμα δημιουργίας/διαγραφής προσωρινών test tables. Τρέχουν μόνο σε εξουσιοδοτημένο περιβάλλον δοκιμών. Το joomla-integration.php δημιουργεί ξεχωριστό random prefix, **άδειους** πίνακες με την πραγματική δομή, τεχνητούς subscribers μόνο εκεί, και child DI container με υποχρεωτικό mock mailer. Δεν αντιγράφει πραγματικούς συνδρομητές. Αφαιρεί τους προσωρινούς πίνακες σε finally.

Τα e2e-fixtures.php / browser-data.php χρησιμοποιούν το bootstrap joomla-inspect.php στο /tmp. Δημιουργούν προσωρινούς users/groups και αποθηκεύουν state για cleanup και επαναφορά ACL. Δημιουργούν επίσης το κανονικό Registered menu item του bridge, το οποίο παραμένει μετά το cleanup. Μην εκτελείτε τα fixtures σε production. Οι controllers runners απαιτούν αυτά τα fixtures και ένα προσωρινό author newsletter. Κάθε case εκτελείται σε ξεχωριστό SiteApplication με υποχρεωτικό mock mailer.

Παραδείγματα για το συγκεκριμένο local Docker περιβάλλον:

~~~text
docker cp tests/joomla-inspect.php catichisi-local-web-1:/tmp/newsletterbridge-inspect.php
docker cp tests/joomla-integration.php catichisi-local-web-1:/tmp/newsletterbridge-integration.php
docker exec catichisi-local-web-1 php /tmp/newsletterbridge-integration.php /var/www/html
~~~

Η στατική ανάλυση και τα mocks δεν υποκαθιστούν λειτουργικό Joomla test. Σε νέο περιβάλλον ελέγξτε εγκατάσταση/αναβάθμιση, menu routing, πραγματικό editor, override, ACL ανά χρήστη, CSRF, checkout δύο sessions, batch resume/cancel και email rendering με mock ή απομονωμένο mail catcher, μετά από έγκριση για αποστολές.

## Έλεγχοι που πραγματοποιήθηκαν — 2026-10-08

- PHP syntax checks για όλα τα PHP αρχεία του component.
- Standalone security tests: **3.671 assertions** για ACL matrix, ownership, dual send grants, job binding, inputs/UTF-8, ασφαλή return URLs, checkout, lock, sample-only tests, counter recovery και suppression global purge.
- Έλεγχοι XML, manifest references, language keys και ZIP structure/paths.
- Επιτυχής εγκατάσταση και αναβάθμιση μέσω του πραγματικού Joomla installer στο local Docker Joomla **6.1.3**.
- **34** integration assertions σε πραγματικά Joomla models/MySQL, με **6 mock emails στη μνήμη** και μηδενικές πραγματικές αποστολές.
- **19** πραγματικά controller dispatch cases για CSRF, HTTP method, task allowlist και author denial, με υποχρεωτικό mock transport.
- Browser: author login χωρίς administrator access, create/save/edit, native TinyMCE, preset, unpublished default, sandboxed preview, pagination 20+4, native override, sender publish/send screen, και HTTP 403 για anonymous/foreign/send-screen πρόσβαση.

Οι web POST δοκιμές αποστολής δεν εκτελέστηκαν: ο αυτόματος έλεγχος έγκρισης τις απέρριψε λόγω του κινδύνου χρήσης πραγματικού mailer στη web συνεδρία. Οι αντίστοιχοι έλεγχοι έγιναν στο mock-protected Joomla harness. Δεν δοκιμάστηκε πραγματική SMTP παράδοση.

## License

GPL version 2 or later. Το Simple Newsletter παραμένει ξεχωριστό εγκατεστημένο extension και δεν συμπεριλαμβάνεται στο bridge ZIP.
