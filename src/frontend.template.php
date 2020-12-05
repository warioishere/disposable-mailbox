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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/bootstrap/4.3.1/bootstrap.min.css"
          integrity="sha384-T8BvL2pDN59Kgod7e7p4kesUb+oyQPt3tFt8S+sIa0jUenn1byQ97GBKHUN8ZPk0"
          crossorigin="anonymous">
    <link rel="stylesheet" href="assets/fontawesome/5.15.1/all.min.css"
          integrity="sha384-PeCD/lV7xE25gKYPf8+k88QGX43BoAVlEVaWbRzKBTS+WGt2FOpM2ofQ11rlYiei"
          crossorigin="anonymous">
    <title><?php
        echo $emails ? "(" . count($emails) . ") " : "";
        echo $user->address ?></title>
    <link rel="stylesheet" href="assets/spinner.css">
    <link rel="stylesheet" href="assets/custom.css">

    <script>
        var mailCount = <?php echo count($emails)?>;
        setInterval(function () {
            var r = new XMLHttpRequest();
            r.open("GET", "?action=has_new_messages&address=<?php echo $user->address?>&email_ids=<?php echo $mailIdsJoinedString?>", true);
            r.onreadystatechange = function () {
                if (r.readyState != 4 || r.status != 200) return;
                if (r.responseText > 0) {
                    console.log("There are", r.responseText, "new mails.");
                    document.getElementById("new-content-avalable").style.display = 'block';

                    // If there are no emails displayed, we can reload the page without losing any state.
                    if (mailCount === 0) {
                        location.reload();
                    }
                }
            };
            r.send();

        }, 15000);

    </script>

</head>
<body>


<div id="new-content-avalable">
    <div class="alert alert-info alert-fixed" role="alert">
        <strong>Neue E-Mail</strong> empfangen.

        <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
            <i class="fas fa-sync"></i>
            Neu laden!
        </button>

    </div>
    <div style="height: 3rem">&nbsp;</div>
</div>

<header>
    <div class="container">
        <p class="lead ">
            Ihre Einweg-Mailbox ist eingerichtet!
        </p>
        <div class="row" id="address-box-normal">

            <div class="col my-address-block">
                <span id="my-address"><?php echo $user->address ?></span>&nbsp;<button class="copy-button" data-clipboard-target="#my-address">kopieren</button>
            </div>


            <div class="col get-new-address-col">
                <button type="button" class="btn btn-outline-dark"
                        data-toggle="collapse" title="w&auml;hlen Sie Ihre eigene Adresse"
                        data-target=".change-address-toggle"
                        aria-controls="address-box-normal address-box-edit" aria-expanded="false">
                    <i class="fas fa-magic"></i> Adresse &auml;ndern
                </button>
            </div>
        </div>


        <form class="collapse change-address-toggle" id="address-box-edit" action="?action=redirect" method="post">
            <div class="card">
                <div class="card-body">
                    <p>
                        <a href="?action=random" role="button" class="btn btn-dark">
                            <i class="fa fa-random"></i>
                            zuf&auml;llige E-Mail Adresse
                        </a>
                    </p>


                    oder erstellen Sie Ihre eigene Adresse:
                    <div class="form-row align-items-center">
                        <div class="col-sm">
                            <label class="sr-only" for="inlineFormInputName">username</label>
                            <input name="username" type="text" class="form-control" id="inlineFormInputName"
                                   placeholder="username"
                                   value="<?php echo $user->username ?>">
                        </div>
                        <div class="col-sm-auto my-1">
                            <label class="sr-only" for="inlineFormInputGroupUsername">Domain</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">@</div>
                                </div>

                                <select class="custom-select" id="inlineFormInputGroupUsername" name="domain">
                                    <?php
                                    foreach ($config['domains'] as $aDomain) {
                                        $selected = $aDomain === $user->domain ? ' selected ' : '';
                                        print "<option value='$aDomain' $selected>$aDomain</option>";
                                    }
                                    ?>
                                </select>


                            </div>
                        </div>
                        <div class="col-auto my-1">
                            <button type="submit" class="btn btn-primary">Mailbox &ouml;ffnen</button>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </div>
</header>

<main>
    <div class="container">

        <div id="email-list" class="list-group">

            <?php
            foreach ($emails as $email) {
                $safe_email_id = filter_var($email->id, FILTER_VALIDATE_INT); ?>

                <a class="list-group-item list-group-item-action email-list-item" data-toggle="collapse"
                   href="#mail-box-<?php echo $email->id ?>"
                   role="button"
                   aria-expanded="false" aria-controls="mail-box-<?php echo $email->id ?>">

                    <div class="media">
                        <button class="btn btn-white open-collapse-button">
                            <i class="fas fa-caret-right expand-button-closed"></i>
                            <i class="fas fa-caret-down expand-button-opened"></i>
                        </button>


                        <div class="media-body">
                            <h6 class="list-group-item-heading"><?php echo filter_var($email->fromName, FILTER_SANITIZE_SPECIAL_CHARS) ?>
                                <span class="text-muted"><?php echo filter_var($email->fromAddress, FILTER_SANITIZE_SPECIAL_CHARS) ?></span>
                                <small class="float-right"
                                       title="<?php echo $email->date ?>"><?php echo niceDate($email->date) ?></small>
                            </h6>
                            <p class="list-group-item-text text-truncate" style="width: 75%">
                                <?php echo filter_var($email->subject, FILTER_SANITIZE_SPECIAL_CHARS); ?>
                            </p>
                        </div>
                    </div>
                </a>


                <div id="mail-box-<?php echo $email->id ?>" role="tabpanel" aria-labelledby="headingCollapse1"
                     class="card-collapse collapse"
                     aria-expanded="true">
                    <div class="card-body">
                        <div class="card-block email-body">
                            <div class="float-right primary">
                                <a class="btn btn-outline-primary btn-sm" download="true"
                                   role="button"
                                   href="<?php echo "?action=download_email&email_id=$safe_email_id&address=$user->address" ?>">
                                    Herunterladen
                                </a>

                                <a class="btn btn-outline-danger btn-sm"
                                   role="button"
                                   href="<?php echo "?action=delete_email&email_id=$safe_email_id&address=$user->address" ?>">
                                    L&ouml;schen
                                </a>
                            </div>
                             <?php printMessageBody($email, $purifier); ?>

                        </div>
                    </div>
                </div>
            <?php
            } ?>

            <?php
            if (empty($emails)) {
                ?>
                <div id="empty-mailbox">
                    <p>Die Mailbox ist leer. Es wird automatisch auf neue E-Mails geprüft. </p>
                    <div class="spinner">
                        <div class="rect1"></div>
                        <div class="rect2"></div>
                        <div class="rect3"></div>
                        <div class="rect4"></div>
                        <div class="rect5"></div>
                    </div>
                </div>
            <?php
            } ?>
        </div>
    </div>
</main>

<footer>
    <div class="container">


<!--                <select id="language-selection" class="custom-select" title="Language">-->
<!--                    <option selected>English</option>-->
<!--                    <option value="1">Deutsch</option>-->
<!--                    <option value="2">Two</option>-->
<!--                    <option value="3">Three</option>-->
<!--                </select>-->
<!--                <br>-->

        <small class="text-justify quick-summary">
            Dies ist ein Einweg-Mailbox-Dienst. Wer Ihren Benutzernamen kennt, kann Ihre E-Mails lesen!
            Die E-Mails werden nach 24 Stunden gel&ouml;scht.
        </small>
        <div class="card card-body collapse" id="about" style="max-width: 40rem">
            <h3>Details</h3>

            <p class="text-justify">Diese Einweg-Mailbox h&auml;lt Ihre Haupt-Mailbox frei von Spam.</p>

            <p class="text-justify">W&auml;hlen Sie einfach eine Adresse aus und verwenden Sie sie auf Webseiten, denen Sie nicht vertrauen oder auf denen Sie ihre Haupt E-Mail Adresse nicht verwenden m&ouml;chten.
                Wenn Sie fertig sind, k&ouml;nnen Sie diese Mailbox einfach vergessen. Der ganze Spam bleibt hier und ihre Haupt-Mailbox bleibt sauber.
            </p>

            <p class="text-justify">
                Sie wählen die Adresse aus, die Sie verwenden möchten, und empfangene E-Mails werden automatisch angezeigt.
                Es ist keine Registrierung notwendig und es gibt keine Passwörter. Jeder, der die E-Mail Adresse kennt hat Zugriff darauf!
                <strong>Grunds&auml;tzlich sind also ALLE E-Mails &ouml;ffentlich. Verwenden Sie diese E-Mail Adresse daher nicht für sensible Daten.</strong>


            </p>
        </div>
        <div class="card card-body collapse" id="privacy" style="max-width: 40rem;">
            <h3>Datenschutz</h3>

            <h5>Beschreibung und Umfang der Datenverarbeitung</h5>

            Folgende Daten werden im Rahmen der Erbringung des Einweg E-Mail Dienstes gespeichert:
            <br /><br />
            Nutzdaten - Informationen, die bei der Verwendung eines Dienstes entstehen:
            <ul>
                <li>E-Mails inklusive Inhalt und Anhang</li>
            </ul>
            Verkehrsdaten - Informationen, die bei der Nutzung des E-Mail-Dienstes anfallen:
            <ul>
                <li>SMTP: Absender, Empf&auml;nger, Nachrichten ID, Gr&ouml;&szlig;e der versandten oder empfangenen E-Mail</li>
            </ul>
            <h5>Rechtsgrundlage f&uuml;r die Datenverarbeitung</h5>
            Rechtsgrundlage f&uuml;r die Verarbeitung der Daten ist Art. 6 Abs. 1 lit. b DSGVO. Dar&uuml;ber hinaus verarbeiten wir Ihre personenbezogenen Daten zur Wahrung unserer berechtigten Interessen, sofern nicht Ihre Interessen oder Grundrechte und Grundfreiheiten &uuml;berwiegen. Die Rechtsgrundlage hierf&uuml;r ist Art. 6 Abs. 1 (f) DSGVO.
            Zweck der Datenverarbeitung
            Die Verarbeitung der personenbezogenen Daten dient der Bereitstellung und Funktionalit&auml;t des Einweg E-Mail Dienstes.
            <br /><br />
            <h5>Dauer der Speicherung</h5>
            Nutzungsdaten werden nach 1 Tag gel&ouml;scht.
            Verkehrsdaten werden nach Ablauf der gesetzlichen Aufbewahrungsfrist gel&ouml;scht. Im Falle der Speicherung von Daten in Logfiles ist dies nach sp&auml;testens sieben Tagen der Fall. Eine dar&uuml;berhinausgehende Speicherung ist m&ouml;glich. In diesem Fall werden die IP-Adressen der Nutzer gel&ouml;scht oder verfremdet, sodass eine Zuordnung des aufrufenden Clients nicht mehr m&ouml;glich ist.
            <br /><br />
            <h5>Widerspruchs- und Beseitigungsm&ouml;glichkeit</h5>
            Der Nutzer kann die Inhaltsdaten (Email) jederzeit &uuml;ber die L&ouml;schfunktion selbstt&auml;tig l&ouml;schen. Ein Widerspruch ist nicht m&ouml;glich, da der angebotene Dienst sonst nicht erbracht werden kann.
        </div>
        <p>
            <small>warth-hofer.de |
                <a data-toggle="collapse" href="#about"
                   aria-expanded="false"
                   aria-controls="about">
                    Details
                </a> |
                <a data-toggle="collapse" href="#privacy"
                   aria-expanded="false"
                   aria-controls="about">
                    Datenschutz
                </a> |
                Quellcode:
                <a href="https://github.com/abyssox/disposable-mailbox" target="_blank"><strong>abyssox/disposable-mailbox</strong></a>
                <br />
                Original Quellcode: <a href="https://github.com/synox/disposable-mailbox" target="_blank">synox/disposable-mailbox</a>
            </small>
        </p>
    </div>
</footer>


<!-- jQuery first, then Popper.js, then Bootstrap JS -->
<script src="assets/jquery/jquery-3.5.1.slim.min.js"
        integrity="sha384-l/z+BgBEQ5vmodKu1ZyIBOCaxL+WKCpcWNYF+bSZtiAhNZG/WxArS01uMuEZgAJu"
        crossorigin="anonymous"></script>
<script src="assets/popper.js/1.16.1/umd/popper.min.js"
        integrity="sha384-kNOowQZDWMTHoqbD1uAT2huc4WUwEVBEUgpnoVt+rhKXwjxfy4qzAdhdacyS6RWG"
        crossorigin="anonymous"></script>
<script src="assets/bootstrap/4.3.1/bootstrap.min.js"
        integrity="sha384-lXqCKSCzpmJ9kRbg4c4nQHayMEuwgwGO+SmKQlcp/OuWw054bPqQcHRK9yyRbKpS"
        crossorigin="anonymous"></script>
<script src="assets/clipboard.js/clipboard.min.js"
        integrity="sha384-sNv7CSqlr8wZWpa63xuHtth1Vuo9BzfmdUz8c2Kjyl4n4PYB3MDaDdzjiXeiFxHP"
        crossorigin="anonymous"></script>

<script>
    clipboard = new ClipboardJS('[data-clipboard-target]');
    $(function () {
        $('[data-tooltip="tooltip"]').tooltip()
    });

    /** from https://github.com/twbs/bootstrap/blob/c11132351e3e434f6d4ed72e5a418eb692c6a319/assets/js/src/application.js */
    clipboard.on('success', function (e) {
        $(e.trigger)
            .attr('title', 'Kopiert!')
            .tooltip('_fixTitle')
            .tooltip('show')
            .tooltip('_fixTitle');
        e.clearSelection();
    });

</script>

</body>
</html>
