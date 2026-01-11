<?php

declare(strict_types=1);

if (!function_exists('send_security_headers')) {
    function send_security_headers(): void
    {
        if (headers_sent()) return;

        // Basic hardening
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // (Optional) Permissions Policy: ปิดของที่ไม่ได้ใช้ก่อน
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        $nonce = csp_nonce();

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",

            // Assets
            "img-src 'self' data: blob: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'",

            // Scripts: ใช้ nonce สำหรับ inline script (external script ยังอนุญาตจาก self)
            "script-src 'self' 'nonce-{$nonce}'",

            // API / fetch / websocket (ถ้ายังไม่ใช้ ws ให้คงไว้แค่นี้ก่อน)
            "connect-src 'self'",

            // ถ้าคุณไม่มี embed frame เลย ก็ไม่ต้องมี frame-src
            // "frame-src 'self'",
        ];

        // Production: บังคับอัปเกรด http -> https (ใน local ไม่ต้อง เดี๋ยวพัง)
        if (function_exists('app_is_production') && app_is_production()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        header('Content-Security-Policy: ' . implode('; ', $directives) . ';');
    }
}
