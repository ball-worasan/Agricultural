<?php

declare(strict_types=1);

// กำหนดคลาส Database
final class Database
{
  /** @var ?PDO */
  private static ?PDO $conn = null;

  /** @var bool */
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
    'DB_PERSISTENT',
    'APP_TIMEZONE',
  ];

  private static function envPath(): string
  {
    // กำหนดค่า path ของไฟล์ .env
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
  }


  // โหลดไฟล์ .env ครั้งเดียวแล้ว cache ไว้ใน static::$env
  private static function loadEnv(): void
  {
    if (self::$envLoaded) return;

    // 1) seed จาก environment ของระบบก่อน (ดีที่สุด)
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
      if ($line === '' || $line[0] === '#') continue;

      // รองรับ "export KEY=VAL"
      if (str_starts_with($line, 'export ')) {
        $line = trim(substr($line, 7));
      }

      $pos = strpos($line, '=');
      if ($pos === false) continue;

      $key = trim(substr($line, 0, $pos));
      $val = trim(substr($line, $pos + 1));

      if ($key === '') continue;

      // ตัด inline comment แบบ: KEY=val #comment (ถ้าไม่ได้อยู่ใน quote)
      if ($val !== '' && ($val[0] !== '"' && $val[0] !== "'")) {
        $hashPos = strpos($val, ' #');
        if ($hashPos !== false) $val = trim(substr($val, 0, $hashPos));
        $hashPos = strpos($val, "\t#");
        if ($hashPos !== false) $val = trim(substr($val, 0, $hashPos));
      }

      // strip quotes
      $len = strlen($val);
      if ($len >= 2) {
        $f = $val[0];
        $l = $val[$len - 1];
        if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) {
          $val = substr($val, 1, -1);
        }
      }

      // อย่า override env ของระบบ ถ้ามีอยู่แล้ว
      if (!array_key_exists($key, self::$env)) {
        self::$env[$key] = $val;
      }
    }

    self::$envLoaded = true;
  }

  // ดึงค่า env พร้อม fallback ไปที่ getenv() และ default
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

  // ดึง env แบบ boolean
  private static function envBool(string $key, bool $default = false): bool
  {
    $val = self::env($key);
    if ($val === null) {
      return $default;
    }

    $normalized = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $normalized ?? $default;
  }

  /**
   * ดึง config ฐานข้อมูลแบบ cache ไว้ครั้งเดียว
   *
   * @return array{
   *  driver:string,
   *  host:string,
   *  port:string,
   *  db:string,
   *  user:string,
   *  pass:string,
   *  charset:string,
   *  persistent:bool
   * }
   */
  public static function config(): array
  {
    if (self::$config !== null) {
      return self::$config;
    }

    $driver    = self::env('DB_CONNECTION', 'mysql') ?? 'mysql';
    $host      = self::env('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    $port      = self::env('DB_PORT', '3306') ?? '3306';
    $db        = self::env('DB_DATABASE', '') ?? '';
    $user      = self::env('DB_USERNAME', '') ?? '';
    $pass      = self::env('DB_PASSWORD', '') ?? '';
    $charset   = self::env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';
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

  /**
   * สร้าง / ดึง PDO connection (singleton)
   */
  public static function connection(): PDO
  {
    if (self::$conn instanceof PDO) {
      return self::$conn;
    }

    $cfg = self::config();
    $dsn = self::buildDsn($cfg);

    $options = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($cfg['persistent'] === true) {
      $options[PDO::ATTR_PERSISTENT] = true;
    }

    try {
      self::$conn = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
    } catch (PDOException $e) {
      $safeMessage = 'Database connection failed.';
      $debugEnv = self::env('APP_DEBUG', 'false') ?? 'false';
      $isDebug = in_array(strtolower($debugEnv), ['1', 'true', 'yes', 'on'], true);

      if ($isDebug) {
        $safeMessage .= ' ' . $e->getMessage();
      }

      throw new RuntimeException($safeMessage, (int) $e->getCode(), $e);
    }

    return self::$conn;
  }

  /**
   * ปิด connection (ให้ GC เก็บ)
   */
  public static function close(): void
  {
    self::$conn = null;
  }

  /**
   * Query ทั่วไปด้วย prepared statement
   *
   * @param array<int|string,mixed> $params
   */
  public static function query(string $sql, array $params = []): PDOStatement
  {
    $pdo = self::connection();
    $stmt = $pdo->prepare($sql);

    if ($stmt === false) {
      throw new RuntimeException('Failed to prepare SQL statement.');
    }

    $success = $stmt->execute($params);
    if ($success === false) {
      throw new RuntimeException('Failed to execute SQL statement.');
    }

    return $stmt;
  }

  /**
   * ดึงหลายแถว
   *
   * @param array<int|string,mixed> $params
   * @return array<int,array<string,mixed>>
   */
  public static function fetchAll(string $sql, array $params = []): array
  {
    return self::query($sql, $params)->fetchAll();
  }

  /**
   * ดึงแถวเดียว
   *
   * @param array<int|string,mixed> $params
   * @return array<string,mixed>|null
   */
  public static function fetchOne(string $sql, array $params = []): ?array
  {
    $row = self::query($sql, $params)->fetch();
    return $row === false ? null : $row;
  }

  /**
   * สำหรับ INSERT/UPDATE/DELETE
   *
   * @param array<int|string,mixed> $params
   */
  public static function execute(string $sql, array $params = []): int
  {
    return self::query($sql, $params)->rowCount();
  }

  /**
   * helper: last insert id
   */
  public static function lastInsertId(): string
  {
    return self::connection()->lastInsertId();
  }

  /**
   * รัน logic ภายใน transaction เดียว
   *
   * @template T
   * @param callable(PDO):T $fn
   * @return T
   */
  public static function transaction(callable $fn)
  {
    $pdo = self::connection();

    $inTx = $pdo->inTransaction();
    $sp = 'sp_' . bin2hex(random_bytes(3));

    try {
      if (!$inTx) {
        $pdo->beginTransaction();
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
      }

      // nested -> savepoint
      $pdo->exec("SAVEPOINT {$sp}");
      $result = $fn($pdo);
      $pdo->exec("RELEASE SAVEPOINT {$sp}");
      return $result;
    } catch (Throwable $e) {
      if (!$inTx && $pdo->inTransaction()) {
        $pdo->rollBack();
      } elseif ($inTx) {
        // rollback แค่ช่วง nested
        try {
          $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
        } catch (Throwable) {
        }
      }
      throw $e;
    }
  }

  /**
   * ตรวจสุขภาพฐานข้อมูล: ใช้กับ health check endpoint ได้
   *
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

      $status['server_version'] = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

      $start = microtime(true);
      $ping = $pdo->query('SELECT 1')->fetchColumn();
      $end = microtime(true);

      $status['ping'] = ($ping == 1);
      $status['ping_time_ms'] = ($end - $start) * 1000.0;
      $status['ok'] = $status['ping'] === true;
    } catch (Throwable $e) {
      $status['error'] = $e->getMessage();
    }

    return $status;
  }
}
