<?php

declare(strict_types=1);

if (!function_exists('redirect')) {
    function redirect(string $url, int $statusCode = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $statusCode);
            exit;
        }

        $safeUrl = e($url);
        $jsUrl = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        echo '<!doctype html><html><head>';
        echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
        echo '<script>window.location.replace(' . $jsUrl . ');</script>';
        echo '</head><body>';
        echo '<p>กำลังเปลี่ยนหน้าไปยัง <a href="' . $safeUrl . '">' . $safeUrl . '</a> ...</p>';
        echo '</body></html>';
        exit;
    }
}
