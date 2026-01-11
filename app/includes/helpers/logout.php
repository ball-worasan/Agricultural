<?php

declare(strict_types=1);

if (!function_exists('is_logout_request')) {
    function is_logout_request(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return false;
        return (($_POST['action'] ?? '') === 'logout');
    }
}

if (!function_exists('handle_logout')) {
    function handle_logout(): void
    {
        app_session_start();

        // CSRF required
        $token = (string)($_POST['_csrf'] ?? '');
        $ok = function_exists('csrf_verify') ? csrf_verify($token) : false;

        if (!$ok) {
            if (function_exists('app_log')) {
                app_log('logout_csrf_failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
            }
            if (function_exists('flash')) {
                flash('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
            }
            redirect('?page=home', 303);
        }

        // clear session
        $_SESSION = [];

        // delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            $cookie = [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ];

            // PHP 7.3+ array options
            setcookie(session_name(), '', $cookie);
        }

        session_destroy();

        if (function_exists('app_log')) {
            app_log('user_logout', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        }

        redirect('?page=home', 303);
    }
}
