<?php

declare(strict_types=1);

final class Database
{
  private static ?PDO $conn = null;

  private static bool $envLoaded = false;

  /** @var array<string,string> */
  private static array $env = [];

  /** @var array<string,mixed>|null */
  private static ?array $config = null;

  private const SUPPORTED_DRIVERS = ['mysql'];

  private const ENV_KEYS = [
    'APP_ENV',
    'APP_DEBUG',
    'DB_CONNECTION',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_CHARSET',
    'DB_COLLATION',
    'DB_PERSISTENT',
  ];

  private static function envPath(): string
  {
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
  }

  // ---------------------------------------------------------------------------
  // ENV
  // ---------------------------------------------------------------------------
  private static function loadEnv(): void
  {
    if (self::$envLoaded) return;

    // 1) OS env wins
    foreach (self::ENV_KEYS as $k) {
      $v = getenv($k);
      if ($v !== false && $v !== '') self::$env[$k] = (string)$v;
    }

    $path = self::envPath();
    if (!is_file($path) || !is_readable($path)) {
      self::$envLoaded = true;
      return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
      self::$envLoaded = true;
      return;
    }

    foreach ($lines as $raw) {
      $line = trim((string)$raw);
      if ($line === '' || str_starts_with($line, '#')) continue;

      // allow: export KEY=VAL
      if (str_starts_with($line, 'export ')) {
        $line = trim(substr($line, 7));
      }

      $pos = strpos($line, '=');
      if ($pos === false) continue;

      $key = trim(substr($line, 0, $pos));
      $val = trim(substr($line, $pos + 1));
      if ($key === '') continue;

      // strip inline comments when not quoted: KEY=val #comment
      $val = self::stripInlineComment($val);

      // strip surrounding quotes
      $val = self::stripQuotes($val);

      // do not override OS env
      if (!array_key_exists($key, self::$env)) {
        self::$env[$key] = $val;
      }
    }

    self::$envLoaded = true;
  }

  private static function stripInlineComment(string $val): string
  {
    if ($val === '') return $val;

    $first = $val[0];
    if ($first === '"' || $first === "'") {
      // quoted: keep as-is (do not strip # inside quotes)
      return $val;
    }

    // split at first unescaped # preceded by whitespace
    // simplest safe approach: find " #" or "\t#"
    foreach ([' #', "\t#"] as $needle) {
      $p = strpos($val, $needle);
      if ($p !== false) {
        $val = trim(substr($val, 0, $p));
        break;
      }
    }
    return $val;
  }

  private static function stripQuotes(string $val): string
  {
    $len = strlen($val);
    if ($len < 2) return $val;

    $f = $val[0];
    $l = $val[$len - 1];

    if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) {
      return substr($val, 1, -1);
    }
    return $val;
  }

  public static function env(string $key, ?string $default = null): ?string
  {
    if (!self::$envLoaded) self::loadEnv();

    if (array_key_exists($key, self::$env)) return self::$env[$key];

    $val = getenv($key);
    if ($val !== false) {
      self::$env[$key] = (string)$val;
      return self::$env[$key];
    }

    return $default;
  }

  private static function envBool(string $key, bool $default = false): bool
  {
    $val = self::env($key);
    if ($val === null) return $default;

    $b = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $b ?? $default;
  }

  // ---------------------------------------------------------------------------
  // CONFIG
  // ---------------------------------------------------------------------------
  /**
   * @return array{
   *  driver:string,
   *  host:string,
   *  port:string,
   *  db:string,
   *  user:string,
   *  pass:string,
   *  charset:string,
   *  collation:string,
   *  persistent:bool
   * }
   */
  public static function config(): array
  {
    if (self::$config !== null) return self::$config;

    $driver     = self::env('DB_CONNECTION', 'mysql') ?? 'mysql';
    $host       = self::env('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    $port       = self::env('DB_PORT', '3306') ?? '3306';
    $db         = self::env('DB_DATABASE', '') ?? '';
    $user       = self::env('DB_USERNAME', '') ?? '';
    $pass       = self::env('DB_PASSWORD', '') ?? '';
    $charset    = self::env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';
    $collation  = self::env('DB_COLLATION', 'utf8mb4_unicode_ci') ?? 'utf8mb4_unicode_ci';
    $persistent = self::envBool('DB_PERSISTENT', false);

    if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
      throw new RuntimeException('Unsupported DB driver: ' . $driver);
    }

    if ($db === '' || $user === '') {
      throw new RuntimeException('Database configuration incomplete. Check DB_DATABASE and DB_USERNAME');
    }

    self::$config = [
      'driver'     => $driver,
      'host'       => $host,
      'port'       => $port,
      'db'         => $db,
      'user'       => $user,
      'pass'       => $pass,
      'charset'    => $charset,
      'collation'  => $collation,
      'persistent' => $persistent,
    ];

    return self::$config;
  }

  private static function buildDsn(array $cfg): string
  {
    return sprintf(
      '%s:host=%s;port=%s;dbname=%s;charset=%s',
      $cfg['driver'],
      $cfg['host'],
      $cfg['port'],
      $cfg['db'],
      $cfg['charset']
    );
  }

  // ---------------------------------------------------------------------------
  // CONNECTION
  // ---------------------------------------------------------------------------
  public static function connection(): PDO
  {
    if (self::$conn instanceof PDO) return self::$conn;

    $cfg = self::config();
    $dsn = self::buildDsn($cfg);

    $options = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($cfg['persistent']) {
      $options[PDO::ATTR_PERSISTENT] = true;
    }

    // Ensure collation at connection time (mysql)
    if ($cfg['driver'] === 'mysql') {
      $init = "SET NAMES {$cfg['charset']} COLLATE {$cfg['collation']}";
      $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $init;
    }

    try {
      self::$conn = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
    } catch (PDOException $e) {
      // Avoid leaking DSN/user/pass
      $msg = 'Database connection failed.';
      $debug =
        (function_exists('app_debug_enabled') && app_debug_enabled())
        || self::envBool('APP_DEBUG', false);

      if ($debug) {
        $msg .= ' ' . $e->getMessage();
      }

      throw new RuntimeException($msg, (int)$e->getCode(), $e);
    }

    return self::$conn;
  }

  public static function close(): void
  {
    self::$conn = null;
  }

  // ---------------------------------------------------------------------------
  // QUERY HELPERS
  // ---------------------------------------------------------------------------
  /** @param array<int|string,mixed> $params */
  public static function query(string $sql, array $params = []): PDOStatement
  {
    $pdo = self::connection();
    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
      throw new RuntimeException('Failed to prepare SQL statement.');
    }

    if ($stmt->execute($params) === false) {
      throw new RuntimeException('Failed to execute SQL statement.');
    }

    return $stmt;
  }

  /** @param array<int|string,mixed> $params @return array<int,array<string,mixed>> */
  public static function fetchAll(string $sql, array $params = []): array
  {
    return self::query($sql, $params)->fetchAll();
  }

  /** @param array<int|string,mixed> $params @return array<string,mixed>|null */
  public static function fetchOne(string $sql, array $params = []): ?array
  {
    $row = self::query($sql, $params)->fetch();
    return $row === false ? null : $row;
  }

  /** @param array<int|string,mixed> $params */
  public static function execute(string $sql, array $params = []): int
  {
    return self::query($sql, $params)->rowCount();
  }

  public static function lastInsertId(): string
  {
    return self::connection()->lastInsertId();
  }

  // ---------------------------------------------------------------------------
  // TRANSACTION (supports nested via savepoints)
  // ---------------------------------------------------------------------------
  /**
   * @template T
   * @param callable(PDO):T $fn
   * @return T
   */
  public static function transaction(callable $fn)
  {
    $pdo = self::connection();
    $inTx = $pdo->inTransaction();

    // savepoint name must be alnum/underscore
    $sp = 'sp_' . bin2hex(random_bytes(4));

    try {
      if (!$inTx) {
        $pdo->beginTransaction();
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
      }

      $pdo->exec("SAVEPOINT {$sp}");
      $result = $fn($pdo);
      $pdo->exec("RELEASE SAVEPOINT {$sp}");
      return $result;
    } catch (Throwable $e) {
      try {
        if (!$inTx && $pdo->inTransaction()) {
          $pdo->rollBack();
        } elseif ($inTx) {
          $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
          $pdo->exec("RELEASE SAVEPOINT {$sp}");
        }
      } catch (Throwable) {
        // swallow rollback failures
      }
      throw $e;
    }
  }

  // ---------------------------------------------------------------------------
  // HEALTH
  // ---------------------------------------------------------------------------
  /**
   * @return array{
   *  ok:bool,
   *  driver:string,
   *  host:string|null,
   *  database:string|null,
   *  server_version:string|null,
   *  ping:bool,
   *  ping_time_ms:float|null,
   *  error:string|null
   * }
   */
  public static function health(): array
  {
    try {
      $cfg = self::config();
    } catch (Throwable $e) {
      return [
        'ok'             => false,
        'driver'         => 'unknown',
        'host'           => null,
        'database'       => null,
        'server_version' => null,
        'ping'           => false,
        'ping_time_ms'   => null,
        'error'          => $e->getMessage(),
      ];
    }

    $status = [
      'ok'             => false,
      'driver'         => $cfg['driver'],
      'host'           => $cfg['host'],
      'database'       => $cfg['db'],
      'server_version' => null,
      'ping'           => false,
      'ping_time_ms'   => null,
      'error'          => null,
    ];

    try {
      $pdo = self::connection();
      $status['server_version'] = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

      $t0 = microtime(true);
      $ping = $pdo->query('SELECT 1')->fetchColumn();
      $t1 = microtime(true);

      $status['ping'] = ((string)$ping === '1');
      $status['ping_time_ms'] = ($t1 - $t0) * 1000.0;
      $status['ok'] = $status['ping'] === true;
    } catch (Throwable $e) {
      $status['error'] = $e->getMessage();
    }

    return $status;
  }
}
