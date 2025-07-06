<?php
// Basic error logging so that internal server errors are easier to debug
ini_set('log_errors', '1');
ini_set('error_log', sys_get_temp_dir() . '/disposable-mailbox.log');
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    error_log("$message in $file on line $line");
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
        error_log("Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}");
    }
});

// check for common errors
if (version_compare(phpversion(), '8.3', '<')) {
    die("ERROR! The php version isn't high enough, you need at least 8.3 to run this application! But you have: " . phpversion());
}
extension_loaded("imap") || die('ERROR: IMAP extension not loaded. Please see the installation instructions in the README.md');


# load php dependencies:
require_once './vendor/autoload.php';
require_once './config_helper.php';
require_once './User.php';
require_once './imap_client.php';
require_once './controller.php';

load_config();

$imapClient = new ImapClient($config['imap']['url'], $config['imap']['username'], $config['imap']['password']);

if (DisplayEmailsController::matches()) {
    DisplayEmailsController::invoke($imapClient, $config);
} elseif (RedirectToAddressController::matches()) {
    RedirectToAddressController::invoke($imapClient, $config);
} elseif (RedirectToRandomAddressController::matches()) {
    RedirectToRandomAddressController::invoke($imapClient, $config);
} elseif (DownloadEmailController::matches()) {
    DownloadEmailController::invoke($imapClient, $config);
} elseif (DeleteEmailController::matches()) {
    DeleteEmailController::invoke($imapClient, $config);
} elseif (HasNewMessagesControllerJson::matches()) {
    HasNewMessagesControllerJson::invoke($imapClient, $config);
} else {
    // If requesting the main site, just redirect to a new random mailbox.
    RedirectToRandomAddressController::invoke($imapClient, $config);
}


// delete after each request
$imapClient->delete_old_messages($config['delete_messages_older_than']);
