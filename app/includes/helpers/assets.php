<?php

declare(strict_types=1);

if (!function_exists('build_asset_paths')) {
    function build_asset_paths(string $base, array $list): array
    {
        $out = [];
        foreach ($list as $file) {
            if (!is_string($file) || $file === '') continue;
            $out[] = $base . ltrim($file, '/');
        }
        return $out;
    }
}

if (!function_exists('build_page_css')) {
    function build_page_css(array $route): array
    {
        $cssList = $route['css'] ?? [];
        return build_asset_paths('/css/', is_array($cssList) ? $cssList : []);
    }
}

if (!function_exists('build_page_js')) {
    function build_page_js(array $route): array
    {
        $jsList = $route['js'] ?? [];
        return build_asset_paths('/js/', is_array($jsList) ? $jsList : []);
    }
}
