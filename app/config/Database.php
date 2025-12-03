<?php

declare(strict_types=1);

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

    private static function envPath(): string
    {
        // สมมติไฟล์นี้อยู่ที่ app/config/Database.php -> .env อยู่ที่ root
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    }

    /**
     * โหลดไฟล์ .env ครั้งเดียวแล้ว cache ไว้ใน static::$env
     */
    private static function loadEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }

        $path = self::envPath();
        if (!is_file($path)) {
            self::$envLoaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$envLoaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // ข้ามคอมเมนต์หรือบรรทัดว่าง
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            // ตัด quote ออกถ้าครอบด้วย "" หรือ ''
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last  = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$env[$key] = $value;
        }

        self::$envLoaded = true;
    }

    /**
     * ดึงค่า env พร้อม fallback ไปที่ getenv() และ default
     */
    public static function env(string $key, ?string $default = null): ?string
    {
        self::loadEnv();

        if (array_key_exists($key, self::$env)) {
            return self::$env[$key];
        }

        $val = getenv($key);
        if ($val !== false) {
            $val = (string) $val;
            self::$env[$key] = $val; // cache ลดการเรียก getenv ซ้ำ ๆ
            return $val;
        }

        return $default;
    }

    /**
     * ดึง env แบบ boolean
     */
    private static function envBool(string $key, bool $default = false): bool
    {
        $val = self::env($key);
        if ($val === null) {
            return $default;
        }

        return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
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
            $debugEnv = self::env('APP_DEBUG', 'false');
            if (strtolower((string) $debugEnv) === 'true') {
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

        // ป้องกันปัญหา nested transaction:
        // เริ่ม/commit/rollback เฉพาะกรณีที่เราสร้าง transaction นี้เอง
        $startedHere = !$pdo->inTransaction();

        if ($startedHere) {
            $pdo->beginTransaction();
        }

        try {
            /** @var T $result */
            $result = $fn($pdo);

            if ($startedHere && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->rollBack();
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
