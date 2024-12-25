<?php

declare(strict_types=1);

use PhpImap\Mailbox;
use PhpImap\IncomingMail;

class ImapClient {

    private Mailbox $mailbox;

    public function __construct(string $imapPath, string $login, string $password) {
        $this->mailbox = new Mailbox($imapPath, $login, $password);
    }

    /**
     * Returns all mails for the given $user.
     */
    public function get_emails(User $user): array {
        $imapStream = $this->mailbox->getImapStream();
        $mailIdsTo = imap_sort($imapStream, SORTARRIVAL, true, SE_UID, 'TO "' . $user->address . '"') ?: [];
        $mailIdsCc = imap_sort($imapStream, SORTARRIVAL, true, SE_UID, 'CC "' . $user->address . '"') ?: [];

        $mailIds = array_unique(array_merge($mailIdsTo, $mailIdsCc));
        return $this->_load_emails($mailIds, $user);
    }

    /**
     * Deletes an email by ID if it matches the user's address.
     */
    public function delete_email(int $mailid, User $user): bool {
        $email = $this->load_one_email($mailid, $user);
        if ($email !== null) {
            $this->mailbox->deleteMail($mailid);
            $this->mailbox->expungeDeletedMails();
            return true;
        }
        return false;
    }

    /**
     * Loads one email if the recipient matches the user's address.
     */
    public function load_one_email(int $mailid, User $user): ?IncomingMail {
        $emails = $this->_load_emails([$mailid], $user);
        return count($emails) === 1 ? $emails[0] : null;
    }

    /**
     * Loads one email fully (headers and body).
     */
    public function load_one_email_fully(int $mailid, User $user): ?string {
        $email = $this->load_one_email($mailid, $user);
        if ($email !== null) {
            $imapStream = $this->mailbox->getImapStream();
            $headers = imap_fetchheader($imapStream, $mailid, FT_UID) ?: '';
            $body = imap_body($imapStream, $mailid, FT_UID) ?: '';
            return $headers . "\n" . $body;
        }
        return null;
    }

    /**
     * Deletes messages older than the specified date.
     */
    public function delete_old_messages(string $deleteMessagesOlderThan): void {
        $date = date('d-M-Y', strtotime($deleteMessagesOlderThan));
        $ids = $this->mailbox->searchMailbox('BEFORE ' . $date) ?: [];
        foreach ($ids as $id) {
            $this->mailbox->deleteMail($id);
        }
        $this->mailbox->expungeDeletedMails();
    }

    /**
     * Loads emails using the given IDs and filters them by the user's address.
     */
    private function _load_emails(array $mailIds, User $user): array {
        return array_filter(
            array_map(
                fn($id) => $this->mailbox->getMail($id),
                $mailIds
            ),
            fn($mail) => isset($mail->to[$user->address]) || isset($mail->cc[$user->address])
        );
    }
}
