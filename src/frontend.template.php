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
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $emails ? "(" . count($emails) . ") " : ""; echo $user->address; ?></title>

    <!-- Bootstrap CSS und Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
          integrity="sha384-T8BvL2pDN59Kgod7e7p4kesUb+oyQPt3tFt8S+sIa0jUenn1byQ97GBKHUN8ZPk0"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css"
          integrity="sha384-PeCD/lV7xE25gKYPf8+k88QGX43BoAVlEVaWbRzKBTS+WGt2FOpM2ofQ11rlYiei"
          crossorigin="anonymous">
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
            background: #ffffff;
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
            border-radius: 5px;
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
                if (r.responseText > 0) {
                    document.getElementById("new-content-available").style.display = 'block';
                    if (mailCount === 0) {
                        location.reload();
                    }
                }
            };
            r.send();
        }, 15000); // Intervall von 15 Sekunden
    </script>
</head>
<body>

<!-- Benachrichtigung für neue E-Mails -->
<div id="new-content-available" class="alert alert-info alert-fixed" role="alert" style="display: none;">
    <strong>Neue E-Mail</strong> empfangen.
    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
        <i class="fas fa-sync"></i> Neu laden!
    </button>
</div>

<div class="container">
    <header class="header-section">
        <h1>Deine Einweg-Mailbox</h1>
        <p>Erstelle schnell und einfach eine temporäre E-Mail-Adresse!</p>
    </header>

    <!-- Adresse anzeigen und Kopieren -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-6 text-center">
            <h4 id="my-address"><?php echo $user->address; ?></h4>
            <button class="btn btn-primary copy-button" data-clipboard-target="#my-address">
                <i class="fas fa-copy"></i> Kopieren
            </button>
        </div>
    </div>

    <!-- Formular zur Adressauswahl und zufälligen Adresse -->
    <div class="form-container text-center">
        <a href="?action=random" class="btn btn-dark mb-3">
            <i class="fa fa-random"></i> Zufällige E-Mail Adresse
        </a>
        <p>Oder erstelle deine eigene Adresse und bestätige mit Enter:</p>
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
                    <button type="submit" class="btn btn-primary">Mailbox öffnen</button>
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
                        <h6><strong>Von:</strong> <?php echo htmlspecialchars($email->fromName); ?> <span class="text-muted"><?php echo htmlspecialchars($email->fromAddress); ?></span></h6>
                        <p><strong>Betreff:</strong> <?php echo htmlspecialchars($email->subject); ?></p>
                        <small><strong>Datum:</strong> <?php echo niceDate($email->date); ?></small>
                        <div class="mt-3">
                            <?php printMessageBody($email, $purifier); ?>
                        </div>
                    </div>
                <?php endforeach;
            } else { ?>
                <div id="empty-mailbox" class="text-center p-4">
                    <p>Die Mailbox ist leer. Es wird automatisch auf neue E-Mails geprüft.</p>
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
            <h3>Details</h3>
            <p>Diese Einweg-Mailbox hält deine Haupt-Mailbox frei von Spam.</p>
            <p>Wähle eine Adresse und verwende sie auf Webseiten, denen du nicht voll vertrauen kannst oder wo du deine Haupt-E-Mail-Adresse nicht verwenden möchtest. Wenn du fertig bist, kannst du diese Mailbox einfach vergessen. Der ganze Spam bleibt hier, und deine Haupt-Mailbox bleibt sauber.</p>
            <p><strong>Hinweis:</strong> Alle E-Mails sind öffentlich zugänglich, wenn die Adresse bekannt ist. Verwende diese Adresse nicht für sensible Daten.</p>
        </div>

        <div class="privacy">
            <h3>Datenschutz</h3>
            <h5>Beschreibung und Umfang der Datenverarbeitung</h5>
            <p>Folgende Daten werden im Rahmen der Erbringung des Einweg-E-Mail-Dienstes gespeichert:</p>
            <ul>
                <li>Nutzdaten: E-Mails inklusive Inhalt und Anhang</li>
                <li>Verkehrsdaten: Absender, Empfänger, Nachrichten-ID, Größe der versandten oder empfangenen E-Mail</li>
            </ul>
            <h5>Rechtsgrundlage für die Datenverarbeitung</h5>
            <p>Die Verarbeitung der personenbezogenen Daten dient der Bereitstellung und Funktionalität des Einweg-E-Mail-Dienstes gemäß Art. 6 Abs. 1 lit. b DSGVO.</p>
            <h5>Dauer der Speicherung</h5>
            <p>Nutzungsdaten werden nach 1 Tag gelöscht. Verkehrsdaten werden nach Ablauf der gesetzlichen Aufbewahrungsfrist gelöscht.</p>
            <h5>Widerspruchs- und Beseitigungsmöglichkeit</h5>
            <p>Der Nutzer kann die E-Mails jederzeit löschen. Ein Widerspruch ist nicht möglich, da der Dienst sonst nicht erbracht werden kann.</p>
        </div>

        <p class="text-center mt-4">
            <small>
                yourdevice.ch |
                Quellcode: <a href="https://github.com/abyssox/disposable-mailbox" target="_blank"><strong>abyssox/disposable-mailbox</strong></a>
            </small>
        </p>
    </footer>
</div>

<!-- jQuery und Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script src="assets/clipboard.js/clipboard.min.js"></script>
<script>
    clipboard = new ClipboardJS('[data-clipboard-target]');
</script>

</body>
</html>
