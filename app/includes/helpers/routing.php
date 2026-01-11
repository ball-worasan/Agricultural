<?php

declare(strict_types=1);

if (!function_exists('resolve_page')) {
    function resolve_page($pageParam, array $routes): string
    {
        $raw = is_string($pageParam) ? $pageParam : 'home';
        $raw = strtolower($raw);

        if (!preg_match('/^[a-z0-9_]+$/', $raw)) {
            return 'home';
        }
        return array_key_exists($raw, $routes) ? $raw : 'home';
    }
}

if (!function_exists('guard_route')) {
    function guard_route(array $route): void
    {
        if (!empty($route['auth']) && !is_authenticated()) {
            flash('error', 'กรุณาเข้าสู่ระบบก่อนเข้าหน้านี้');
            store_old_input($_GET);
            redirect('?page=signin');
        }

        if (!empty($route['guest_only']) && is_authenticated()) {
            redirect('?page=home');
        }

        if (!empty($route['admin']) && !is_admin()) {
            flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
            redirect('?page=home');
        }
    }
}
