<?php

declare(strict_types=1);

class User {
    public string $address;
    public string $username;
    public string $domain;

    public static function get_random_address(array $domains): string {
        try {
            $wordLength = random_int(3, 8);
            $nr = random_int(51, 91);
        } catch (\Exception $e) {
            $wordLength = mt_rand(3, 8);
            $nr = mt_rand(51, 91);
        }
        $container = new PronounceableWord_DependencyInjectionContainer();
        $generator = $container->getGenerator();
        $word = $generator->generateWordOfGivenLength($wordLength);
        $name = $word . $nr;

        $domain = $domains[array_rand($domains)];
        return "$name@$domain";
    }

    public function isInvalid(array $config_domains): bool {
        return empty($this->username) || empty($this->domain) || !in_array($this->domain, $config_domains, true);
    }

    public static function parseDomain(string $address, array $config_blocked_usernames): User {
        $clean_address = self::_clean_address($address);
        $user = new self();
        $user->username = self::_clean_username($clean_address, $config_blocked_usernames);
        $user->domain = self::_clean_domain($clean_address);
        $user->address = $user->username . '@' . $user->domain;
        return $user;
    }

    public static function parseUsernameAndDomain(string $username, string $domain, array $config_blocked_usernames): User {
        $user = new self();
        $user->username = self::_clean_username($username, $config_blocked_usernames);
        $user->domain = self::_clean_domain($domain);
        $user->address = $user->username . '@' . $user->domain;
        return $user;
    }

    private static function _clean_address(string $address): string {
        return strtolower(filter_var($address, FILTER_SANITIZE_EMAIL));
    }

    private static function _clean_username(string $address, array $config_blocked_usernames): string {
        $username = strtolower($address);
        $username = preg_replace('/@.*$/', '', $username);  // Entfernt den Teil nach @
        $username = preg_replace('/[^A-Za-z0-9_.+-]/', '', $username);  // Entfernt unerwünschte Zeichen

        if (in_array($username, $config_blocked_usernames, true)) {
            // Verbotener Benutzername
            return '';
        }

        return $username;
    }

    private static function _clean_domain(string $address): string {
        $domain = strtolower($address);
        $domain = preg_replace('/^.*@/', '', $domain);  // Entfernt den Teil vor @
        return preg_replace('/[^A-Za-z0-9_.+-]/', '', $domain);  // Entfernt unerwünschte Zeichen
    }
}
