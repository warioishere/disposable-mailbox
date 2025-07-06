<?php

declare(strict_types=1);

require_once './imap_client.php';

function render_error(int $status, string $msg): void {
    if ($status < 100 || $status > 599) {
        $status = 500; // Fallback auf gültigen HTTP-Statuscode
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['result' => 'error', 'error' => $msg], JSON_THROW_ON_ERROR));
}

class DisplayEmailsController {
    public static function matches(): bool {
        return !isset($_GET['action']) && !empty($_SERVER['QUERY_STRING'] ?? '');
    }

    public static function invoke(ImapClient $imapClient, array $config): void {
        $address = trim(filter_var($_SERVER['QUERY_STRING'] ?? '', FILTER_UNSAFE_RAW));
        $user = User::parseDomain($address, $config['blocked_usernames']);

        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config);
            return;
        }

        $emails = $imapClient->get_emails($user);
        self::render($emails, $config, $user);
    }

    private static function render(array $emails, array $config, User $user): void {
        // Variablen für das Template definieren
        require "frontend.template.php";
    }
}

class RedirectToAddressController {
    public static function matches(): bool {
        return ($_GET['action'] ?? null) === "redirect"
            && isset($_POST['username'], $_POST['domain']);
    }

    public static function invoke(ImapClient $imapClient, array $config): void {
        $username = trim(filter_var($_POST['username'], FILTER_UNSAFE_RAW));
        $domain = trim(filter_var($_POST['domain'], FILTER_UNSAFE_RAW));
        $user = User::parseUsernameAndDomain($username, $domain, $config['blocked_usernames']);

        self::render($user->username . '@' . $user->domain);
    }

    public static function render(string $address): void {
        header("Location: ?$address");
        exit;
    }
}

class RedirectToRandomAddressController {
    public static function matches(): bool {
        return ($_GET['action'] ?? null) === 'random';
    }

    public static function invoke(ImapClient $imapClient, array $config): void {
        $address = User::get_random_address($config['domains']);
        RedirectToAddressController::render($address);
    }
}

class HasNewMessagesControllerJson {
    public static function matches(): bool {
        return ($_GET['action'] ?? null) === "has_new_messages"
            && isset($_GET['email_ids'], $_GET['address']);
    }

    public static function invoke(ImapClient $imapClient, array $config): void {
        $email_ids = explode('|', trim(filter_var($_GET['email_ids'], FILTER_UNSAFE_RAW)));
        $address = trim(filter_var($_GET['address'], FILTER_UNSAFE_RAW));

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            render_error(400, "Invalid email address");
        }

        $emails = $imapClient->get_emails($user);
        $newMailIds = array_map(fn($mail) => $mail->id, $emails);
        $onlyNewMailIds = array_diff($newMailIds, $email_ids);

        self::render(count($onlyNewMailIds));
    }

    private static function render(int $counter): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['new_messages' => $counter], JSON_THROW_ON_ERROR);
    }
}

class DownloadEmailController {
    public static function matches(): bool {
        return ($_GET['action'] ?? null) === "download_email"
            && isset($_GET['email_id'], $_GET['address']);
    }

    public static function invoke(ImapClient $imapClient, array $config): void {
        $email_id = filter_var($_GET['email_id'], FILTER_SANITIZE_NUMBER_INT);
        $address = trim(filter_var($_GET['address'], FILTER_UNSAFE_RAW));

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config);
            return;
        }

        $full_email = $imapClient->load_one_email_fully($email_id, $user);
        if ($full_email !== null) {
            $filename = $user->address . "-" . $email_id . ".eml";
            self::renderDownloadEmailAsRfc822($full_email, $filename);
        } else {
            render_error(404, 'Download error: invalid username/email_id combination');
        }
    }

    private static function renderDownloadEmailAsRfc822(string $full_email, string $filename): void {
        header("Content-Type: message/rfc822; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        echo $full_email;
    }
}

class DeleteEmailController {
    public static function matches(): bool {
        return ($_GET['action'] ?? null) === "delete_email"
            && isset($_GET['email_id'], $_GET['address']);
    }

    public static function invoke(ImapClient $imapClient, array $config): void {
        $email_id = filter_var($_GET['email_id'], FILTER_SANITIZE_NUMBER_INT);
        $address = trim(filter_var($_GET['address'], FILTER_UNSAFE_RAW));

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config);
            return;
        }

        if ($imapClient->delete_email($email_id, $user)) {
            RedirectToAddressController::render($address);
        } else {
            render_error(404, 'Delete error: invalid username/email_id combination');
        }
    }
}
