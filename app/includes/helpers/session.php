<?php

declare(strict_types=1);

/**
 * Session + Request helpers
 */

if (!function_exists('request_is_https')) {
    function request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        if (((string)($_SERVER['SERVER_PORT'] ?? '')) === '443') return true;

        // reverse proxy support
        $xfp = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($xfp !== '') {
            // sometimes "https,http"
            $first = trim(explode(',', $xfp)[0] ?? '');
            if ($first === 'https') return true;
        }

        $xssl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($xssl === 'on' || $xssl === '1') return true;

        return false;
    }
}

if (!function_exists('app_session_start')) {
    function app_session_start(): void
    {
        static $started = false;

        if ($started || session_status() === PHP_SESSION_ACTIVE) {
            $started = true;
            return;
        }

        if (headers_sent()) {
            if (function_exists('app_log')) app_log('session.headers_already_sent');
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        // fallback สำหรับบาง env (กัน array options ไม่ครบ)
        @ini_set('session.cookie_samesite', 'Lax');

        $cookie = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        // PHP 7.3+ รองรับ array
        session_set_cookie_params($cookie);

        session_start();
        $started = true;
    }
}

if (!function_exists('session_regenerate_safe')) {
    function session_regenerate_safe(bool $deleteOld = true): void
    {
        app_session_start();
        if (session_status() !== PHP_SESSION_ACTIVE) return;

        @session_regenerate_id($deleteOld);
    }
}

if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        static $nonce = null;
        if (is_string($nonce) && $nonce !== '') return $nonce;

        $nonce = base64_encode(random_bytes(16));
        return $nonce;
    }
}

if (!function_exists('is_json_request')) {
    function is_json_request(): bool
    {
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        return stripos($accept, 'application/json') !== false;
    }
}

if (!function_exists('is_ajax_request')) {
    function is_ajax_request(): bool
    {
        $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xrw === 'xmlhttprequest';
    }
}

// -----------------------------------------------------------------------------
// CSRF
// -----------------------------------------------------------------------------
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        app_session_start();

        $tok = $_SESSION['_csrf'] ?? null;
        if (!is_string($tok) || $tok === '') {
            $tok = bin2hex(random_bytes(32));
            $_SESSION['_csrf'] = $tok;
        }

        return $tok;
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(string $token): bool
    {
        app_session_start();

        $sessionToken = $_SESSION['_csrf'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') return false;
        if ($token === '') return false;

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('csrf_rotate')) {
    function csrf_rotate(): void
    {
        app_session_start();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
