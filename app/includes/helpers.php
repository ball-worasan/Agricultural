<?php

declare(strict_types=1);

if (!defined('JSON_UNESCAPED_UNICODE')) {
    // ค่านี้ของ JSON_UNESCAPED_UNICODE คือ 256 (ตาม PHP)
    define('JSON_UNESCAPED_UNICODE', 256);
}

/**
 * Start session ถ้ายังไม่ได้เริ่ม
 */
if (!function_exists('app_session_start')) {
    function app_session_start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // ถ้า header ถูกส่งไปแล้ว การ start session จะเตือน/พัง
        if (headers_sent()) {
            // ยังไงก็พยายามไม่พังแอป แต่ log ทิ้งไว้ให้รู้ปัญหา
            if (function_exists('app_log')) {
                app_log('session.headers_already_sent');
            }
            return;
        }

        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        );

        // ตั้งค่า cookie ให้ปลอดภัยขึ้น
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        // PHP 7.3+ รองรับ array
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($cookieParams);
        } else {
            // fallback signature เก่า
            session_set_cookie_params(
                $cookieParams['lifetime'],
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
            @ini_set('session.cookie_samesite', 'Lax');
        }

        session_start();
    }
}

/**
 * Escape HTML แบบปลอดภัย
 */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * แปลงวันที่เป็น พ.ศ. รูปแบบ d/m/(Y+543)
 * input: string ที่ strtotime อ่านได้ เช่น '2025-12-03'
 */
if (!function_exists('buddhist_date')) {
    function buddhist_date(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return '';
        }

        $year = (int) date('Y', $ts) + 543;
        return date('d/m/', $ts) . $year;
    }
}

/**
 * แสดงวันที่แบบเต็ม: 1 มกราคม 2568
 * monthZeroBased: 0 = มกราคม, 11 = ธันวาคม
 */
if (!function_exists('thai_full_date')) {
    function thai_full_date(int $day, int $monthZeroBased, int $yearAD): string
    {
        $months = [
            'มกราคม',
            'กุมภาพันธ์',
            'มีนาคม',
            'เมษายน',
            'พฤษภาคม',
            'มิถุนายน',
            'กรกฎาคม',
            'สิงหาคม',
            'กันยายน',
            'ตุลาคม',
            'พฤศจิกายน',
            'ธันวาคม',
        ];

        $monthName = isset($months[$monthZeroBased]) ? $months[$monthZeroBased] : '';
        $buddhistYear = $yearAD + 543;

        return $day . ' ' . $monthName . ' ' . $buddhistYear;
    }
}

/**
 * ดึงเวลาปัจจุบันเป็น DateTimeImmutable (ใช้ใน service ต่าง ๆ)
 */
if (!function_exists('now')) {
    function now(?string $timezone = 'Asia/Bangkok'): DateTimeImmutable
    {
        $tzName = $timezone !== null && $timezone !== '' ? $timezone : 'UTC';

        try {
            $tz = new DateTimeZone($tzName);
        } catch (Exception $e) {
            // fallback กัน timezone พิมพ์ผิด
            $tz = new DateTimeZone('UTC');
        }

        return new DateTimeImmutable('now', $tz);
    }
}

/**
 * ดึง user ปัจจุบันจาก session (ถ้ามี)
 */
if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        app_session_start();

        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return null;
        }

        return $_SESSION['user'];
    }
}

/**
 * เช็คว่ามีผู้ใช้ล็อกอินอยู่ไหม
 */
if (!function_exists('is_authenticated')) {
    function is_authenticated(): bool
    {
        return current_user() !== null;
    }
}

/**
 * เช็คว่า user ปัจจุบันเป็น admin หรือไม่
 */
if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $user = current_user();
        if ($user === null) {
            return false;
        }

        return isset($user['role']) && $user['role'] === 'admin';
    }
}

/**
 * Redirect แบบง่าย ๆ
 */
if (!function_exists('redirect')) {
    function redirect(string $url, int $statusCode = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $statusCode);
            exit;
        }

        // Fallback เมื่อ header ถูกส่งไปแล้ว: ใช้ meta refresh + JS
        $safeUrl = e($url);

        echo '<!doctype html><html><head>';
        echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
        echo '<script>window.location.replace("' . $safeUrl . '");</script>';
        echo '</head><body>';
        echo '<p>กำลังเปลี่ยนหน้าไปยัง <a href="' . $safeUrl . '">' . $safeUrl . '</a> ...</p>';
        echo '</body></html>';
        exit;
    }
}

/**
 * Flash message:
 * - flash('success', 'บันทึกสำเร็จ'); // set
 * - $msg = flash('success');          // get + ลบ
 */
if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): ?string
    {
        app_session_start();

        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }

        // set
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        // get & clear
        $value = isset($_SESSION['_flash'][$key]) ? $_SESSION['_flash'][$key] : null;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }
}

/**
 * แสดง Flash แบบ Popup พร้อมปุ่มปิด และรอคูลดาวน์ 5 วินาที
 * ใช้งาน: เรียก render_flash_popup() ใน layout หรือท้ายหน้าเพจ
 */
if (!function_exists('render_flash_popup')) {
    function render_flash_popup(): void
    {
        $success = flash('success');
        $error   = flash('error');

        $message = null;
        $type    = null;

        if ($success !== null) {
            $message = $success;
            $type = 'success';
        } elseif ($error !== null) {
            $message = $error;
            $type = 'error';
        }

        if ($message === null || $type === null) {
            return; // ไม่มีข้อความ ไม่ต้องแสดงอะไร
        }

        $msgEsc  = e($message);
        $typeEsc = e($type);

        // กันโหลด CSS/JS ซ้ำ ถ้ามีคนเผลอเรียกหลายครั้ง
        static $assetsRendered = false;

        if (!$assetsRendered) {
            echo '<link rel="stylesheet" href="/css/flash-popup.css">';
            echo '<script src="/js/flash-popup.js"></script>';
            $assetsRendered = true;
        }

        echo '<div class="flash-popup" role="alert" aria-live="polite" data-type="' . $typeEsc . '">
            <div class="flash-popup__bar"></div>
            <div class="flash-popup__content">
                <svg class="flash-popup__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';

        if ($type === 'success') {
            echo '<path d="M20 6L9 17l-5-5" />';
        } else {
            echo '<circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12" y2="16" />';
        }

        echo '  </svg>
                <div class="flash-popup__msg">' . $msgEsc . '</div>
            </div>
            <div class="flash-popup__actions">
                <button type="button" class="flash-popup__close" aria-label="ปิด">ปิด</button>
                <span class="flash-popup__count" aria-hidden="true"></span>
            </div>
        </div>';
    }
}

/**
 * เก็บค่า input เดิมหลังจาก redirect (เช่น เวลาฟอร์ม validate ไม่ผ่าน)
 */
if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        app_session_start();

        $data = isset($_SESSION['_old_input']) && is_array($_SESSION['_old_input'])
            ? $_SESSION['_old_input']
            : [];

        if (isset($data[$key])) {
            return e((string) $data[$key]);
        }

        return e($default);
    }
}

/**
 * ตั้งค่า old input ก่อน redirect (ใช้ใน controller)
 * ตัวอย่าง: store_old_input($_POST);
 */
if (!function_exists('store_old_input')) {
    function store_old_input(array $input): void
    {
        app_session_start();
        $_SESSION['_old_input'] = $input;
    }
}

/**
 * Logger เบา ๆ สำหรับ debug (เขียนลง error_log)
 */
if (!function_exists('app_log')) {
    function app_log(string $event, array $context = []): void
    {
        $flags = 0;
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $flags |= (int) constant('JSON_UNESCAPED_UNICODE');
        }

        $json = json_encode($context, $flags);
        if ($json === false) {
            $json = '{}';
        }

        $line = '[sirinat][' . $event . '] ' . $json;
        error_log($line);
    }
}

/**
 * ส่ง response เป็น JSON พร้อมตั้ง HTTP status code
 */
if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }
}
