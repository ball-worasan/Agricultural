<?php

declare(strict_types=1);

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        app_session_start();
        $u = $_SESSION['user'] ?? null;
        return is_array($u) ? $u : null;
    }
}

if (!function_exists('is_authenticated')) {
    function is_authenticated(): bool
    {
        return current_user() !== null;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $user = current_user();
        return is_array($user) && (($user['role'] ?? null) === ROLE_ADMIN);
    }
}

if (!function_exists('role_key')) {
    function role_key(int $id): string
    {
        return ROLE_ID_TO_KEY[$id] ?? ROLE_KEY_GUEST;
    }
}

if (!function_exists('role_id')) {
    function role_id(string $key): int
    {
        $normalized = strtolower(trim($key));
        return ROLE_KEY_TO_ID[$normalized] ?? ROLE_GUEST;
    }
}

if (!function_exists('role_label_th')) {
    function role_label_th(int $id): string
    {
        return ROLE_ID_TO_LABEL_TH[$id] ?? ROLE_ID_TO_LABEL_TH[ROLE_GUEST];
    }
}
