<?php

declare(strict_types=1);

if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): ?string
    {
        app_session_start();
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }

        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return is_string($value) ? $value : null;
    }
}

if (!function_exists('store_old_input')) {
    function store_old_input(array $input): void
    {
        app_session_start();
        $_SESSION['_old_input'] = $input;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        app_session_start();
        $data = (isset($_SESSION['_old_input']) && is_array($_SESSION['_old_input']))
            ? $_SESSION['_old_input']
            : [];

        $val = $data[$key] ?? $default;
        return e((string)$val);
    }
}

if (!function_exists('render_flash_popup')) {
    function render_flash_popup(): void
    {
        static $renderedOnce = false;
        if ($renderedOnce) return;
        $renderedOnce = true;

        $success = flash('success');
        $error   = flash('error');

        $type = null;
        $message = null;

        if (is_string($success) && $success !== '') {
            $type = 'success';
            $message = $success;
        } elseif (is_string($error) && $error !== '') {
            $type = 'error';
            $message = $error;
        }

        if ($type === null || $message === null) return;

        echo '<div class="flash-popup" role="alert" aria-live="polite" data-type="' . e($type) . '">
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
        echo '</svg>
        <div class="flash-popup__msg">' . e($message) . '</div>
      </div>
      <div class="flash-popup__actions">
        <button type="button" class="flash-popup__close" aria-label="ปิด">ปิด</button>
        <span class="flash-popup__count" aria-hidden="true"></span>
      </div>
    </div>';
    }
}
