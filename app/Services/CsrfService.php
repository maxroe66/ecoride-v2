<?php

namespace App\Services;

class CsrfService
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Assure que la session PHP est active (évite duplication)
     */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function getToken(): string
    {
        self::ensureSession();
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $provided): bool
    {
        self::ensureSession();
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        error_log('[CsrfService::validate] Provided token: ' . ($provided ? substr($provided, 0, 20) : 'NULL'));
        error_log('[CsrfService::validate] Expected token: ' . ($expected ? substr($expected, 0, 20) : 'EMPTY'));
        error_log('[CsrfService::validate] Session ID: ' . session_id());
        $isValid = $provided !== null && $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
        error_log('[CsrfService::validate] Result: ' . ($isValid ? 'VALID' : 'INVALID'));
        return $isValid;
    }
}
