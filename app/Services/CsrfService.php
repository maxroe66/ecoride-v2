<?php

namespace App\Services;

class CsrfService
{
    private const SESSION_KEY = 'csrf_token';

    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $provided): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        return $provided !== null && $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
    }
}
