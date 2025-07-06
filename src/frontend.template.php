<?php
/*
input:

User $user - User object
array $config - config array
array $emails - array of emails
*/

require_once './autolink.php';

// Load HTML Purifier
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('HTML.Nofollow', true);
$purifier_config->set('HTML.ForbiddenElements', array("img"));
$purifier_config->set('Cache.SerializerPath', sys_get_temp_dir());
$purifier = new HTMLPurifier($purifier_config);

\Moment\Moment::setLocale($config['locale']);

$mailIds = array_map(function ($mail) {
    return $mail->id;
}, $emails);
$mailIdsJoinedString = filter_var(join('|', $mailIds), FILTER_SANITIZE_SPECIAL_CHARS);

// define bigger renderings here to keep the php sections within the html short.
function niceDate($date) {
    $m = new \Moment\Moment($date, date_default_timezone_get());
    return $m->calendar();
}

function printMessageBody($email, $purifier) {
    global $config;

    // To avoid showing empty mails, first purify the html and plaintext
    // before checking if they are empty.
    $safeHtml = $purifier->purify($email->textHtml);

    $safeText = htmlspecialchars($email->textPlain);
    $safeText = nl2br($safeText);
    $safeText = \AutoLinkExtension::auto_link_text($safeText);

    $hasHtml = strlen(trim($safeHtml)) > 0;
    $hasText = strlen(trim($safeText)) > 0;

    if ($config['prefer_plaintext']) {
        if ($hasText) {
            echo $safeText;
        } else {
            echo $safeHtml;
        }
    } else {
        if ($hasHtml) {
            echo $safeHtml;
        } else {
            echo $safeText;
        }
    }
}

?>

<!doctype html>
<html class="dark-mode" lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $emails ? "(" . count($emails) . ") " : ""; echo $user->address; ?></title>

    <!-- Bootstrap CSS und Font Awesome -->
    <link rel="stylesheet" href="assets/bootstrap/4.3.1/bootstrap.min.css">
    <link rel="stylesheet" href="assets/fontawesome/5.15.1/all.min.css">
    <link rel="stylesheet" href="assets/spinner.css">
    <link rel="stylesheet" href="assets/custom.css">
    <style>
        /* Verbesserte Abstände und zentriertes Layout */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            padding-top: 30px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .alert-fixed {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 9999;
            border-radius: 0;
            text-align: center;
            padding: 1rem;
        }
        .email-list-item {
            background: #f0f8ff;
            border-radius: 5px;
            margin-bottom: 10px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .email-list-item:hover {
            background: #f0f8ff;
        }
        .card {
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 15px;
        }
        .email-content {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .header-section {
            padding: 20px;
            text-align: center;
            border: 1px solid #ccc;
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header-section h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .header-section p {
            font-size: 1.1rem;
        }
        .details, .privacy {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        footer p {
            margin-top: 20px;
        }
        /* Abstände zwischen Buttons */
        .btn, .copy-button, select, input[type="text"] {
            margin-bottom: 15px;
            margin-right: 10px; /* Extra Abstand rechts für eine saubere Trennung */
        }
    </style>

    <script>
        var mailCount = <?php echo count($emails); ?>;
        setInterval(function () {
            var r = new XMLHttpRequest();
            r.open("GET", "?action=has_new_messages&address=<?php echo $user->address; ?>&email_ids=<?php echo $mailIdsJoinedString; ?>", true);
            r.onreadystatechange = function () {
                if (r.readyState != 4 || r.status != 200) return;
                try {
                    var data = JSON.parse(r.responseText);
                    if (data.new_messages > 0) {
                        document.getElementById("new-content-available").style.display = 'block';
                        if (mailCount === 0) {
                            location.reload();
                        }
                    }
                } catch (e) {
                    console.error('Failed to parse new mail counter:', e);
                }
            };
            r.send();
        }, 15000); // Intervall von 15 Sekunden
    </script>
</head>
<body>

<!-- Benachrichtigung für neue E-Mails -->
<div id="new-content-available" class="alert alert-info alert-fixed" role="alert" style="display: none;">
    <strong id="t-new-email">Neue E-Mail</strong> <span id="t-received">empfangen.</span>
    <button id="reload-btn" type="button" class="btn btn-outline-secondary" onclick="location.reload()">
        <i class="fas fa-sync"></i> <span id="t-reload">Neu laden!</span>
    </button>
</div>

<div class="container">
    <header class="header-section">
        <h1 id="header-title">Deine Einweg-Mailbox</h1>
        <p id="header-subtitle">Erstelle schnell und einfach eine temporäre E-Mail-Adresse!</p>
        <button id="theme-toggle" type="button" class="btn btn-secondary">Light Mode</button>
        <button id="language-toggle" type="button" class="btn btn-secondary mt-2">English</button>
    </header>

    <!-- Adresse anzeigen und Kopieren -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-6 text-center">
            <h4 id="my-address"><?php echo $user->address; ?></h4>
            <button class="btn btn-primary copy-button" data-clipboard-target="#my-address">
                <i class="fas fa-copy"></i> <span id="t-copy">Kopieren</span>
            </button>
        </div>
    </div>

    <!-- Formular zur Adressauswahl und zufälligen Adresse -->
    <div class="form-container text-center">
        <a href="?action=random" class="btn btn-dark mb-3">
            <i class="fa fa-random"></i> <span id="t-random">Zufällige E-Mail Adresse</span>
        </a>
        <p id="t-custom">Oder erstelle deine eigene Adresse und bestätige mit Enter:</p>
        <form action="?action=redirect" method="post">
            <div class="form-row justify-content-center">
                <div class="col-auto">
                    <input name="username" type="text" class="form-control" placeholder="username"
                           value="<?php echo $user->username; ?>">
                </div>
                <div class="col-auto">
                    <select class="custom-select" name="domain">
                        <?php foreach ($config['domains'] as $aDomain) {
                            $selected = $aDomain === $user->domain ? ' selected ' : '';
                            echo "<option value='$aDomain' $selected>$aDomain</option>";
                        } ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary" id="t-open">Mailbox öffnen</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Hauptinhalt mit E-Mails in separaten Boxen -->
    <main class="mt-5">
        <div id="email-list">
            <?php if (!empty($emails)) {
                foreach ($emails as $email): ?>
                    <div class="email-content">
                        <h6><strong class="t-from">Von:</strong> <?php echo htmlspecialchars($email->fromName); ?> <span class="text-muted"><?php echo htmlspecialchars($email->fromAddress); ?></span></h6>
                        <p><strong class="t-subject">Betreff:</strong> <?php echo htmlspecialchars($email->subject); ?></p>
                        <small><strong class="t-date">Datum:</strong> <?php echo niceDate($email->date); ?></small>
                        <div class="mt-3">
                            <?php printMessageBody($email, $purifier); ?>
                        </div>
                        <div class="email-actions text-right">
                            <a href="?action=download_email&email_id=<?php echo $email->id; ?>&address=<?php echo $user->address; ?>"
                               class="btn btn-outline-primary btn-sm" title="Download">
                                <i class="fas fa-download"></i> <span class="t-download">Herunterladen</span>
                            </a>
                            <a href="?action=delete_email&email_id=<?php echo $email->id; ?>&address=<?php echo $user->address; ?>"
                               class="btn btn-outline-danger btn-sm" title="Delete">
                                <i class="fas fa-trash"></i> <span class="t-delete">Löschen</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach;
            } else { ?>
                <div id="empty-mailbox" class="text-center p-4">
                    <p id="t-empty">Die Mailbox ist leer. Es wird automatisch auf neue E-Mails geprüft.</p>
                    <div class="spinner">
                        <div class="rect1"></div>
                        <div class="rect2"></div>
                        <div class="rect3"></div>
                        <div class="rect4"></div>
                        <div class="rect5"></div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>

    <!-- Footer mit Datenschutz und Details -->
    <footer class="mt-5">
        <div class="details">
            <h3 id="t-details-title">Details</h3>
            <p id="t-details-p1">Diese Einweg-Mailbox hält deine Haupt-Mailbox frei von Spam.</p>
            <p id="t-details-p2">Wähle eine Adresse und verwende sie auf Webseiten, denen du nicht voll vertrauen kannst oder wo du deine Haupt-E-Mail-Adresse nicht verwenden möchtest. Wenn du fertig bist, kannst du diese Mailbox einfach vergessen. Der ganze Spam bleibt hier, und deine Haupt-Mailbox bleibt sauber.</p>
            <p><strong id="t-note-label">Hinweis:</strong> <span id="t-note-p">Alle E-Mails sind öffentlich zugänglich, wenn die Adresse bekannt ist. Verwende diese Adresse nicht für sensible Daten.</span></p>
        </div>

        <div class="privacy">
            <h3 id="t-privacy-title">Datenschutz</h3>
            <h5 id="t-privacy-desc">Beschreibung und Umfang der Datenverarbeitung</h5>
            <p id="t-privacy-p1">Folgende Daten werden im Rahmen der Erbringung des Einweg-E-Mail-Dienstes gespeichert:</p>
            <ul>
                <li id="t-usage-data">Nutzdaten: E-Mails inklusive Inhalt und Anhang</li>
                <li id="t-traffic-data">Verkehrsdaten: Absender, Empfänger, Nachrichten-ID, Größe der versandten oder empfangenen E-Mail</li>
            </ul>
            <h5 id="t-basis-title">Grundlage für die Datenverarbeitung</h5>
            <p id="t-basis-p">Die Verarbeitung der Daten dient der Bereitstellung und Funktionalität des Einweg-E-Mail-Dienstes.</p>
            <h5 id="t-retention-title">Dauer der Speicherung</h5>
            <p id="t-retention-p">Nutzungsdaten werden nach 1 Tag gelöscht. Verkehrsdaten werden nach Ablauf der gesetzlichen Aufbewahrungsfrist gelöscht.</p>
            <h5 id="t-deletion-title">Beseitigungsmöglichkeit der Mails</h5>
            <p id="t-deletion-p">Der Nutzer kann die E-Mails jederzeit über ein integriertes Forumular löschen. Die Mails werden dann direkt vom Mailserver gelöscht. </p>
        </div>

        <p class="text-center mt-4">
            <small>
                yourdevice.ch |
                <span id="t-source">Quellcode:</span> <a href="https://github.com/warioishere/disposable-mailbox.git" target="_blank"><strong>warioishere/disposable-mailbox</strong></a>
            </small>
        </p>
    </footer>
</div>

<!-- jQuery und Bootstrap JS -->
<script src="assets/jquery/jquery-3.5.1.slim.min.js"></script>
<script src="assets/popper.js/1.16.1/umd/popper.min.js"></script>
<script src="assets/bootstrap/4.3.1/bootstrap.min.js"></script>
<script src="assets/clipboard.js/clipboard.min.js"></script>
<script>
    clipboard = new ClipboardJS('[data-clipboard-target]');
</script>
<script>
    const form = document.querySelector('form[action="?action=redirect"]');
    if (form) {
        const username = form.querySelector('input[name="username"]');
        const domain = form.querySelector('select[name="domain"]');
        [username, domain].forEach(function (el) {
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    form.submit();
                }
            });
        });
    }
</script>
<script>
    var htmlEl = document.documentElement;
    var toggle = document.getElementById('theme-toggle');
    var langToggle = document.getElementById('language-toggle');

    var translations = {
        de: {
            newEmail: 'Neue E-Mail',
            newEmailReceived: 'empfangen.',
            reload: 'Neu laden!',
            headerTitle: 'Deine Einweg-Mailbox',
            headerSubtitle: 'Erstelle schnell und einfach eine temporäre E-Mail-Adresse!',
            copy: 'Kopieren',
            randomAddress: 'Zufällige E-Mail Adresse',
            customAddress: 'Oder erstelle deine eigene Adresse und bestätige mit Enter:',
            openMailbox: 'Mailbox öffnen',
            from: 'Von:',
            subject: 'Betreff:',
            date: 'Datum:',
            emptyBox: 'Die Mailbox ist leer. Es wird automatisch auf neue E-Mails geprüft.',
            details: 'Details',
            detailsP1: 'Diese Einweg-Mailbox hält deine Haupt-Mailbox frei von Spam.',
            detailsP2: 'Wähle eine Adresse und verwende sie auf Webseiten, denen du nicht voll vertrauen kannst oder wo du deine Haupt-E-Mail-Adresse nicht verwenden möchtest. Wenn du fertig bist, kannst du diese Mailbox einfach vergessen. Der ganze Spam bleibt hier, und deine Haupt-Mailbox bleibt sauber.',
            note: 'Hinweis:',
            noteP: 'Alle E-Mails sind öffentlich zugänglich, wenn die Adresse bekannt ist. Verwende diese Adresse nicht für sensible Daten.',
            privacy: 'Datenschutz',
            privacyDesc: 'Beschreibung und Umfang der Datenverarbeitung',
            privacyP1: 'Folgende Daten werden im Rahmen der Erbringung des Einweg-E-Mail-Dienstes gespeichert:',
            usageData: 'Nutzdaten: E-Mails inklusive Inhalt und Anhang',
            trafficData: 'Verkehrsdaten: Absender, Empfänger, Nachrichten-ID, Größe der versandten oder empfangenen E-Mail',
            basis: 'Grundlage für die Datenverarbeitung',
            basisP: 'Die Verarbeitung der Daten dient der Bereitstellung und Funktionalität des Einweg-E-Mail-Dienstes.',
            retention: 'Dauer der Speicherung',
            retentionP: 'Nutzungsdaten werden nach 1 Tag gelöscht. Verkehrsdaten werden nach Ablauf der gesetzlichen Aufbewahrungsfrist gelöscht.',
            deletion: 'Beseitigungsmöglichkeit der Mails',
            deletionP: 'Der Nutzer kann die E-Mails jederzeit über ein integriertes Forumular löschen. Die Mails werden dann direkt vom Mailserver gelöscht.',
            download: 'Herunterladen',
            delete: 'Löschen',
            sourceCode: 'Quellcode:'
        },
        en: {
            newEmail: 'New Email',
            newEmailReceived: 'received.',
            reload: 'Reload!',
            headerTitle: 'Your Disposable Mailbox',
            headerSubtitle: 'Create a temporary email address quickly and easily!',
            copy: 'Copy',
            randomAddress: 'Random email address',
            customAddress: 'Or create your own address and confirm with Enter:',
            openMailbox: 'Open mailbox',
            from: 'From:',
            subject: 'Subject:',
            date: 'Date:',
            emptyBox: 'The mailbox is empty. Checking for new emails automatically.',
            details: 'Details',
            detailsP1: 'This disposable mailbox keeps your main mailbox free of spam.',
            detailsP2: 'Choose an address and use it on websites you don\'t fully trust or where you don\'t want to use your main email address. When you are done, you can simply forget this mailbox. All the spam stays here and your main mailbox remains clean.',
            note: 'Note:',
            noteP: 'All emails are publicly accessible if the address is known. Do not use this address for sensitive data.',
            privacy: 'Privacy',
            privacyDesc: 'Description and scope of data processing',
            privacyP1: 'The following data is stored as part of providing the disposable email service:',
            usageData: 'Usage data: emails including content and attachments',
            trafficData: 'Traffic data: sender, recipient, message ID, size of sent or received email',
            basis: 'Basis for data processing',
            basisP: 'The data is processed to provide and operate the disposable email service.',
            retention: 'Retention period',
            retentionP: 'Usage data is deleted after 1 day. Traffic data is deleted after the statutory retention period.',
            deletion: 'How to delete mails',
            deletionP: 'The user can delete emails at any time using an integrated form. The emails are then deleted directly from the mail server.',
            download: 'Download',
            delete: 'Delete',
            sourceCode: 'Source code:'
        }
    };

    var currentLang = localStorage.getItem('lang') || 'de';

    function updateThemeToggleText() {
        var isDark = htmlEl.classList.contains('dark-mode');
        if (currentLang === 'de') {
            toggle.textContent = isDark ? 'Heller Modus' : 'Dunkler Modus';
        } else {
            toggle.textContent = isDark ? 'Light Mode' : 'Dark Mode';
        }
    }

    function applyTranslations(lang) {
        document.getElementById('t-new-email').textContent = translations[lang].newEmail;
        document.getElementById('t-received').textContent = translations[lang].newEmailReceived;
        document.getElementById('t-reload').textContent = translations[lang].reload;
        document.getElementById('header-title').textContent = translations[lang].headerTitle;
        document.getElementById('header-subtitle').textContent = translations[lang].headerSubtitle;
        document.getElementById('t-copy').textContent = translations[lang].copy;
        document.getElementById('t-random').textContent = translations[lang].randomAddress;
        document.getElementById('t-custom').textContent = translations[lang].customAddress;
        document.getElementById('t-open').textContent = translations[lang].openMailbox;
        document.querySelectorAll('.t-from').forEach(function(el){ el.textContent = translations[lang].from; });
        document.querySelectorAll('.t-subject').forEach(function(el){ el.textContent = translations[lang].subject; });
        document.querySelectorAll('.t-date').forEach(function(el){ el.textContent = translations[lang].date; });
        document.querySelectorAll('.t-download').forEach(function(el){ el.textContent = translations[lang].download; });
        document.querySelectorAll('.t-delete').forEach(function(el){ el.textContent = translations[lang].delete; });
        var empty = document.getElementById('t-empty');
        if (empty) empty.textContent = translations[lang].emptyBox;
        document.getElementById('t-details-title').textContent = translations[lang].details;
        document.getElementById('t-details-p1').textContent = translations[lang].detailsP1;
        document.getElementById('t-details-p2').textContent = translations[lang].detailsP2;
        document.getElementById('t-note-label').textContent = translations[lang].note;
        document.getElementById('t-note-p').textContent = translations[lang].noteP;
        document.getElementById('t-privacy-title').textContent = translations[lang].privacy;
        document.getElementById('t-privacy-desc').textContent = translations[lang].privacyDesc;
        document.getElementById('t-privacy-p1').textContent = translations[lang].privacyP1;
        document.getElementById('t-usage-data').textContent = translations[lang].usageData;
        document.getElementById('t-traffic-data').textContent = translations[lang].trafficData;
        document.getElementById('t-basis-title').textContent = translations[lang].basis;
        document.getElementById('t-basis-p').textContent = translations[lang].basisP;
        document.getElementById('t-retention-title').textContent = translations[lang].retention;
        document.getElementById('t-retention-p').textContent = translations[lang].retentionP;
        document.getElementById('t-deletion-title').textContent = translations[lang].deletion;
        document.getElementById('t-deletion-p').textContent = translations[lang].deletionP;
        document.getElementById('t-source').textContent = translations[lang].sourceCode;
        langToggle.textContent = lang === 'de' ? 'English' : 'Deutsch';
        document.documentElement.lang = lang;
    }

    var saved = localStorage.getItem('theme');
    if (saved === 'light') {
        htmlEl.classList.remove('dark-mode');
    } else {
        htmlEl.classList.add('dark-mode');
    }

    applyTranslations(currentLang);
    updateThemeToggleText();

    toggle.addEventListener('click', function () {
        htmlEl.classList.toggle('dark-mode');
        localStorage.setItem('theme', htmlEl.classList.contains('dark-mode') ? 'dark' : 'light');
        updateThemeToggleText();
    });

    langToggle.addEventListener('click', function () {
        currentLang = currentLang === 'de' ? 'en' : 'de';
        localStorage.setItem('lang', currentLang);
        applyTranslations(currentLang);
        updateThemeToggleText();
    });
</script>

</body>
</html>
