<?php

declare(strict_types=1);

if (!function_exists('app_env')) {
    function app_env(string $key, ?string $default = null): ?string
    {
        if (class_exists('Database') && method_exists('Database', 'env')) {
            return Database::env($key, $default);
        }
        $v = getenv($key);
        return ($v !== false) ? (string)$v : $default;
    }
}

if (!function_exists('app_env_string')) {
    function app_env_string(string $key, string $default = ''): string
    {
        $v = app_env($key, $default);
        return $v !== null ? (string)$v : $default;
    }
}

if (!function_exists('app_env_bool')) {
    function app_env_bool(string $key, bool $default = false): bool
    {
        $raw = app_env($key, $default ? 'true' : 'false');
        $val = is_string($raw) ? strtolower($raw) : ($default ? 'true' : 'false');
        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('app_is_production')) {
    function app_is_production(): bool
    {
        $env = strtolower((string)(app_env('APP_ENV', 'local') ?? 'local'));
        return in_array($env, ['prod', 'production'], true);
    }
}

if (!function_exists('app_debug_enabled')) {
    function app_debug_enabled(): bool
    {
        return app_env_bool('APP_DEBUG', false) && !app_is_production();
    }
}
