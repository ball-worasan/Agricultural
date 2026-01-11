<?php

declare(strict_types=1);

if (!function_exists('buddhist_date')) {
    function buddhist_date(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) return '';
        $year = (int)date('Y', $ts) + 543;
        return date('d/m/', $ts) . $year;
    }
}

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
        $monthName = $months[$monthZeroBased] ?? '';
        return $day . ' ' . $monthName . ' ' . ($yearAD + 543);
    }
}

if (!function_exists('now')) {
    function now(?string $timezone = 'Asia/Bangkok'): DateTimeImmutable
    {
        $tzName = ($timezone !== null && $timezone !== '') ? $timezone : 'UTC';
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Exception $e) {
            $tz = new DateTimeZone('UTC');
        }
        return new DateTimeImmutable('now', $tz);
    }
}
