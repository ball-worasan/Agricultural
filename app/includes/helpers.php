<?php

declare(strict_types=1);

/**
 * Aggregator: require helper modules
 * NOTE: ลำดับสำคัญ — บางโมดูลพึ่งกัน (env -> logging -> session -> security -> http -> auth -> flash -> routing)
 */

$require = static function (string $path): void {
  if (!is_file($path)) {
    throw new RuntimeException('Helper module not found: ' . $path);
  }
  require_once $path;
};

$base = __DIR__;

// polyfills / env
$require($base . '/helpers/polyfills.php');
$require($base . '/helpers/env.php');

// foundational
$require($base . '/helpers/logging.php');
$require($base . '/helpers/session.php');
$require($base . '/helpers/security_headers.php');
$require($base . '/helpers/html.php');
$require($base . '/helpers/datetime.php');

// http + response
$require($base . '/helpers/http.php');
$require($base . '/helpers/response.php');

// auth + ui
$require($base . '/helpers/auth.php');
$require($base . '/helpers/flash.php');

// routing + assets + logout
$require($base . '/helpers/assets.php');
$require($base . '/helpers/routing.php');
$require($base . '/helpers/logout.php');
