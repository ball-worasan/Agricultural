<?php

declare(strict_types=1);

if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $json = json_encode($payload, $flags);

        echo ($json !== false) ? $json : '{"success":false,"message":"JSON encode failed"}';
        exit;
    }
}
