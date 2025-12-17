<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/Database.php';

/**
 * ตรวจว่า script รันจาก CLI หรือไม่
 */
function isCli(): bool
{
    return PHP_SAPI === 'cli';
}

/**
 * พิมพ์ข้อความแบบ CLI-friendly (และใช้ได้ถ้าถูกเรียกผ่านเว็บ)
 */
function out(string $message = ''): void
{
    if (isCli()) {
        echo $message . PHP_EOL;
        return;
    }

    static $initialized = false;
    if (!$initialized) {
        header('Content-Type: text/plain; charset=UTF-8');
        $initialized = true;
    }

    echo $message . PHP_EOL;
}

/**
 * อ่าน schema.sql และแปลงเป็น array ของ SQL statements
 *
 * @throws RuntimeException
 * @return string[]
 */
function loadSqlStatements(string $schemaPath): array
{
    if (!is_file($schemaPath)) {
        throw new RuntimeException("ไม่พบไฟล์ schema: {$schemaPath}");
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException("ไม่สามารถอ่านไฟล์ schema: {$schemaPath}");
    }

    // ลบ BOM เผื่อมี
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;

    // ลบ block comments แบบ /* ... */
    $sql = preg_replace('~/\*.*?\*/~s', '', $sql) ?? $sql;

    $lines = explode("\n", $sql);
    $currentStatement = '';
    $statements = [];

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);

        // ข้าม comment แบบ -- และ # ที่ขึ้นต้นบรรทัด
        if ($line === '' || str_starts_with($line, '--') || str_starts_with($line, '#')) {
            continue;
        }

        $currentStatement .= $line . ' ';

        // ถ้าจบด้วย ; ให้ตัดเป็น statement หนึ่ง
        $trimmedLine = rtrim($line);
        if ($trimmedLine !== '' && substr($trimmedLine, -1) === ';') {
            $stmt = trim($currentStatement);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $currentStatement = '';
        }
    }

    // ถ้ามี statement ค้าง แต่ไม่มี ; ปิดท้าย
    $currentStatement = trim($currentStatement);
    if ($currentStatement !== '') {
        $statements[] = $currentStatement;
    }

    return $statements;
}

/**
 * พยายามดึงชื่อ table จาก CREATE TABLE หรือ INSERT INTO เพื่อล็อกให้สวย
 */
function extractTableName(string $statement): ?string
{
    if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', $statement, $m)) {
        return $m[1];
    }
    if (preg_match('/CREATE\s+TABLE\s+`?(\w+)`?/i', $statement, $m)) {
        return $m[1];
    }
    if (preg_match('/INSERT\s+INTO\s+`?(\w+)`?/i', $statement, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * เช็กว่า error ของ MySQL เป็นพวกที่ "ข้ามได้" เช่น
 * - 1050: Table already exists
 * - 1062: Duplicate entry
 */
function isIgnorablePdoError(PDOException $e): bool
{
    $info = $e->errorInfo;
    $driverCode = isset($info[1]) ? (int) $info[1] : null;

    return in_array((int) $driverCode, [1050, 1062], true);
}

/**
 * ดึงรายการตาราง + estimated rows จาก information_schema
 *
 * @return array<int,array{table_name:string, table_rows:int}>
 */
function getTableSummary(PDO $pdo, string $databaseName): array
{
    $sql = "
        SELECT TABLE_NAME AS table_name,
               TABLE_ROWS AS table_rows
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db
        ORDER BY TABLE_NAME ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':db' => $databaseName]);

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [
            'table_name' => isset($row['table_name']) ? (string) $row['table_name'] : '',
            'table_rows' => isset($row['table_rows']) ? (int) $row['table_rows'] : 0,
        ];
    }

    return $rows;
}

/**
 * exit code แบบอ่านง่าย
 */
const EXIT_OK      = 0;
const EXIT_DB_FAIL = 1;
const EXIT_PARTIAL = 2;

try {
    $schemaPath = __DIR__ . '/schema.sql';

    $appEnv   = Database::env('APP_ENV', 'local');
    $appDebug = Database::env('APP_DEBUG', 'false');
    $isDebug  = in_array(strtolower((string) $appDebug), ['1', 'true', 'yes', 'on'], true);

    out('==============================================');
    out('  ตั้งค่าฐานข้อมูลสำหรับสิริณัฐ · พื้นที่เกษตรให้เช่า');
    out('==============================================');
    out('Environment: ' . ($appEnv !== null ? $appEnv : 'local'));
    out('');

    // เช็กสุขภาพฐานข้อมูลคร่าว ๆ ก่อน
    $health = Database::health();
    if (empty($health['ok'])) {
        $errorMsg = isset($health['error']) ? $health['error'] : 'ไม่ทราบสาเหตุ';
        out('❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . $errorMsg);
        exit(EXIT_DB_FAIL);
    }

    $driver   = isset($health['driver']) ? $health['driver'] : '-';
    $hostName = isset($health['host']) ? $health['host'] : '-';
    $dbName   = isset($health['database']) ? $health['database'] : '-';

    out(sprintf(
        "เชื่อมต่อฐานข้อมูลสำเร็จ: driver=%s host=%s db=%s",
        $driver,
        $hostName,
        $dbName
    ));

    if (isset($health['ping_time_ms'])) {
        out(sprintf("latency ฐานข้อมูล ~ %.2f ms", (float) $health['ping_time_ms']));
    }
    out('');

    out('📄 กำลังอ่านไฟล์ schema...');
    $statements = loadSqlStatements($schemaPath);
    $total = count($statements);
    out("พบ {$total} SQL statements สำหรับดำเนินการ");
    out('');

    if ($total === 0) {
        out('⚠️ ไม่พบ SQL statement ใน schema.sql');
        exit(EXIT_OK);
    }

    $pdo = Database::connection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $success = 0;
    $skipped = 0;
    $errors  = [];

    $startTime = microtime(true);

    foreach ($statements as $i => $statement) {
        $index = $i + 1;
        $trimmedStmt = ltrim($statement);
        $shortStmt   = strtoupper(substr($trimmedStmt, 0, 30));
        $tableName   = extractTableName($statement);

        try {
            $pdo->exec($statement);
            $success++;

            if ($tableName !== null && str_starts_with($shortStmt, 'CREATE TABLE')) {
                out("  ✓ [#{$index}] สร้างตาราง {$tableName}");
            } elseif ($tableName !== null && str_starts_with($shortStmt, 'INSERT INTO')) {
                out("  ✓ [#{$index}] เพิ่มข้อมูลใน {$tableName}");
            } else {
                out("  ✓ [#{$index}] ดำเนินการ SQL สำเร็จ");
            }
        } catch (PDOException $e) {
            if (isIgnorablePdoError($e)) {
                $skipped++;
                if ($tableName !== null && str_starts_with($shortStmt, 'CREATE TABLE')) {
                    out("  ⊙ [#{$index}] ข้าม: ตาราง {$tableName} มีอยู่แล้ว");
                } elseif ($tableName !== null && str_starts_with($shortStmt, 'INSERT INTO')) {
                    out("  ⊙ [#{$index}] ข้าม: ข้อมูลซ้ำใน {$tableName}");
                } else {
                    $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
                    out("  ⊙ [#{$index}] ข้าม error ที่อนุโลมได้ (code={$driverCode})");
                }
                continue;
            }

            $errors[] = sprintf(
                'Statement #%d (เริ่มต้นด้วย "%s..."): %s',
                $index,
                substr($shortStmt, 0, 30),
                $e->getMessage()
            );

            out("  ✗ [#{$index}] เกิดข้อผิดพลาด: " . $e->getMessage());
        } catch (Throwable $e) {
            $errors[] = sprintf(
                'Statement #%d (เริ่มต้นด้วย "%s..."): %s',
                $index,
                substr($shortStmt, 0, 30),
                $e->getMessage()
            );
            out("  ✗ [#{$index}] เกิดข้อผิดพลาด (ทั่วไป): " . $e->getMessage());
        }
    }

    $duration = microtime(true) - $startTime;

    out('');
    out('==============================================');
    out('สรุปผลการตั้งค่าฐานข้อมูล');
    out('==============================================');
    out("  ✓ สำเร็จ:   {$success} statements");
    out("  ⊙ ข้ามไป:   {$skipped} statements (ตารางซ้ำ/ข้อมูลซ้ำ)");
    out("  ✗ ผิดพลาด: " . count($errors) . ' statements');
    out(sprintf("  ⏱ ใช้เวลา:  %.2f วินาที", $duration));
    out('==============================================');

    if (!empty($errors)) {
        out('');
        out('⚠️ รายละเอียดข้อผิดพลาดที่พบ:');
        foreach ($errors as $error) {
            out('  - ' . $error);
        }
    }

    // แสดงตารางในฐานข้อมูลปัจจุบัน + estimated rows
    out('');
    out('📊 ตารางในฐานข้อมูลปัจจุบัน:');
    try {
        $databaseForTables = $dbName !== '-' ? $dbName : '';
        if ($databaseForTables !== '') {
            $tables = getTableSummary($pdo, $databaseForTables);

            if (empty($tables)) {
                out('  (ยังไม่มีตารางในฐานข้อมูลนี้)');
            } else {
                foreach ($tables as $table) {
                    $tName = $table['table_name'];
                    $count = $table['table_rows'];
                    out(sprintf('  • %s (~%d แถวโดยประมาณ)', $tName, $count));
                }
            }
        } else {
            out('⚠️ ไม่ทราบชื่อฐานข้อมูลจาก health check จึงไม่สามารถดึงรายการตารางได้');
        }
    } catch (Throwable $e) {
        out('⚠️ ไม่สามารถอ่านรายการตารางได้: ' . $e->getMessage());
    }

    out('');
    if (empty($errors)) {
        out('ตั้งค่าฐานข้อมูลเสร็จสมบูรณ์!');
        exit(EXIT_OK);
    }

    out('⚠️ ตั้งค่าฐานข้อมูลเสร็จ แต่มีบางส่วนผิดพลาด โปรดตรวจสอบ');
    if ($isDebug) {
        out('ℹ️ APP_DEBUG=true: คุณอาจเปิด log เพิ่มเติมฝั่ง MySQL/PHP เพื่อดูรายละเอียด');
    }
    exit(EXIT_PARTIAL);
} catch (Throwable $e) {
    out('❌ เกิดข้อผิดพลาดรุนแรง: ' . $e->getMessage());

    $appDebug = Database::env('APP_DEBUG', 'false');
    $isDebug  = in_array(strtolower((string) $appDebug), ['1', 'true', 'yes', 'on'], true);

    if ($isDebug) {
        out('--- DEBUG TRACE ---');
        out($e->getFile() . ':' . $e->getLine());
        out($e->getTraceAsString());
    }

    exit(EXIT_DB_FAIL);
}
