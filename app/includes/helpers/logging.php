<?php

declare(strict_types=1);

if (!function_exists('app_log')) {
    function app_log(string $event, array $context = []): void
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE);
        if ($json === false) $json = '{}';

        $app = defined('APP_NAME') ? (string)APP_NAME : 'app';
        error_log('[' . $app . '][' . $event . '] ' . $json);
    }
}
