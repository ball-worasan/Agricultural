<?php

declare(strict_types=1);

/**
 * setup_database.php (UPDATE/UPSERT mode)
 * - รันซ้ำได้ (idempotent)
 * - ไม่ลบตาราง/ไม่ลบข้อมูล: ข้าม statement อันตราย (DROP/TRUNCATE/DELETE no-where)
 * - CREATE TABLE ที่มีอยู่แล้ว: ข้ามได้ (1050)
 * - INSERT ซ้ำ: ข้ามได้ (1062)
 * - seed จังหวัด/อำเภอ: UPSERT (เพิ่ม/อัปเดต) ไม่ TRUNCATE
 */

require_once dirname(__DIR__) . '/config/database.php';

function envString(string $key, string $default = ''): string
{
  $val = Database::env($key, $default);
  return $val !== null ? (string)$val : $default;
}

function envBool(string $key, bool $default = false): bool
{
  $val = Database::env($key, $default ? 'true' : 'false');
  $normalized = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
  return $normalized ?? $default;
}

function isProdEnv(string $env): bool
{
  return in_array(strtolower($env), ['prod', 'production'], true);
}

function isCli(): bool
{
  return PHP_SAPI === 'cli';
}

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

    if (stripos($line, 'DELIMITER ') === 0) {
      continue;
    }

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
 * กัน statement ที่ทำลายข้อมูล/ตาราง (สำหรับ rerun แบบ update-only)
 */
function isDestructiveStatement(string $statement): bool
{
  $s = strtoupper(trim($statement));

  if (str_starts_with($s, 'DROP ')) return true;
  if (str_starts_with($s, 'TRUNCATE ')) return true;

  // DELETE FROM table; แบบไม่มี WHERE (เสี่ยงล้างทั้งตาราง)
  if (preg_match('/^DELETE\s+FROM\s+[`"\w]+\s*;?$/i', $statement)) return true;

  return false;
}

/**
 * เช็กว่า error ของ MySQL เป็นพวกที่ "ข้ามได้" เช่น
 * - 1050: Table already exists
 * - 1062: Duplicate entry
 */
function isIgnorablePdoError(PDOException $e): bool
{
  $info = $e->errorInfo;
  $driverCode = isset($info[1]) ? (int)$info[1] : null;

  return in_array((int)$driverCode, [1050, 1062], true);
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
      'table_name' => isset($row['table_name']) ? (string)$row['table_name'] : '',
      'table_rows' => isset($row['table_rows']) ? (int)$row['table_rows'] : 0,
    ];
  }

  return $rows;
}

/**
 * ดึง JSON จากไฟล์ภายในหรือ URL (พร้อม timeout)
 */
function fetchJson(string $pathOrUrl): array
{
  $data = null;
  if (is_file($pathOrUrl)) {
    $data = file_get_contents($pathOrUrl);
  } else {
    $context = stream_context_create([
      'http' => ['timeout' => 10],
      'https' => ['timeout' => 10],
    ]);
    $data = @file_get_contents($pathOrUrl, false, $context);
  }

  if ($data === false || $data === null) {
    throw new RuntimeException('ไม่สามารถโหลด JSON จาก ' . $pathOrUrl);
  }

  $json = json_decode($data, true);
  if (!is_array($json)) {
    throw new RuntimeException('รูปแบบ JSON ไม่ถูกต้อง: ' . $pathOrUrl);
  }
  return $json;
}

/**
 * เติม/อัปเดตข้อมูลจังหวัด/อำเภอ โดยไม่ลบของเดิม (UPSERT)
 */
function seedThaiAdministrativeDivisions(PDO $pdo): void
{
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $baseDir  = __DIR__ . '/data';
  $provPath = $baseDir . '/province.json';
  $distPath = $baseDir . '/district.json';

  if (!is_file($provPath)) throw new RuntimeException('ไม่พบไฟล์ province.json ที่ ' . $provPath);
  if (!is_file($distPath)) throw new RuntimeException('ไม่พบไฟล์ district.json ที่ ' . $distPath);

  $provinces = fetchJson($provPath);
  $districts = fetchJson($distPath);

  if (!is_array($provinces) || empty($provinces)) throw new RuntimeException('province.json ว่างหรือรูปแบบไม่ถูกต้อง');
  if (!is_array($districts) || empty($districts)) throw new RuntimeException('district.json ว่างหรือรูปแบบไม่ถูกต้อง');

  // หมายเหตุ: MySQL affected rows:
  // - INSERT ใหม่ = 1
  // - UPDATE จาก ON DUPLICATE = 2
  // - ค่าเหมือนเดิม = 0
  $stmtProv = $pdo->prepare('
    INSERT INTO province (province_id, province_name)
    VALUES (:id, :name)
    ON DUPLICATE KEY UPDATE
      province_name = VALUES(province_name)
  ');

  $stmtDist = $pdo->prepare('
    INSERT INTO district (district_id, district_name, province_id)
    VALUES (:id, :name, :pid)
    ON DUPLICATE KEY UPDATE
      district_name = VALUES(district_name),
      province_id   = VALUES(province_id)
  ');

  $provInserted = 0;
  $provUpdated  = 0;
  $provUnchanged = 0;

  $distInserted = 0;
  $distUpdated  = 0;
  $distUnchanged = 0;

  $pdo->beginTransaction();
  try {
    foreach ($provinces as $prov) {
      $pid  = isset($prov['id']) ? (int)$prov['id'] : 0;
      $name = isset($prov['name_th']) ? (string)$prov['name_th'] : (isset($prov['name']) ? (string)$prov['name'] : '');
      $name = trim($name);

      if ($pid <= 0 || $name === '') continue;

      $stmtProv->execute([':id' => $pid, ':name' => $name]);
      $rc = $stmtProv->rowCount();

      if ($rc === 1) $provInserted++;
      elseif ($rc === 2) $provUpdated++;
      else $provUnchanged++;
    }

    foreach ($districts as $dist) {
      $did = isset($dist['id']) ? (int)$dist['id'] : 0;
      $pid = isset($dist['province_id']) ? (int)$dist['province_id'] : 0;

      $name = '';
      if (isset($dist['district_name'])) $name = (string)$dist['district_name'];
      elseif (isset($dist['name_th']))  $name = (string)$dist['name_th'];
      elseif (isset($dist['name']))     $name = (string)$dist['name'];

      $name = trim($name);

      if ($did <= 0 || $pid <= 0 || $name === '') continue;

      $stmtDist->execute([':id' => $did, ':name' => $name, ':pid' => $pid]);
      $rc = $stmtDist->rowCount();

      if ($rc === 1) $distInserted++;
      elseif ($rc === 2) $distUpdated++;
      else $distUnchanged++;
    }

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }

  out(sprintf('  Province: insert=%d update=%d unchanged=%d', $provInserted, $provUpdated, $provUnchanged));
  out(sprintf('  District: insert=%d update=%d unchanged=%d', $distInserted, $distUpdated, $distUnchanged));
}

/**
 * เพิ่มฟิลด์ใหม่ใน users table (account_number, bank_name, account_name)
 * เช็คว่ามีอยู่แล้วหรือยัง ถ้ายังไม่มีจึงเพิ่ม
 */
function addUserBankFields(PDO $pdo, string $databaseName): void
{
  if ($databaseName === '' || $databaseName === '-') {
    out('⚠️ ไม่ทราบชื่อฐานข้อมูล ข้ามการเพิ่มฟิลด์บัญชีธนาคาร');
    return;
  }

  $fieldsToAdd = [
    [
      'name' => 'account_number',
      'definition' => 'VARCHAR(50) NULL COMMENT \'เลขบัญชีธนาคาร/พร้อมเพย์\'',
      'after' => 'address'
    ],
    [
      'name' => 'bank_name',
      'definition' => 'VARCHAR(100) NULL COMMENT \'ชื่อธนาคาร\'',
      'after' => 'account_number'
    ],
    [
      'name' => 'account_name',
      'definition' => 'VARCHAR(100) NULL COMMENT \'ชื่อบัญชีเจ้าของเลขบัญชี\'',
      'after' => 'bank_name'
    ]
  ];

  try {
    // เช็คว่า users table มีอยู่หรือไม่
    $stmt = $pdo->prepare("
      SELECT COUNT(*) as cnt
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'users'
    ");
    $stmt->execute([':db' => $databaseName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || (int)$result['cnt'] === 0) {
      out('⚠️ ตาราง users ยังไม่มี ข้ามการเพิ่มฟิลด์บัญชีธนาคาร');
      return;
    }

    $added = 0;
    $existed = 0;

    foreach ($fieldsToAdd as $field) {
      // เช็คว่าฟิลด์มีอยู่แล้วหรือไม่
      $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = :col
      ");
      $stmt->execute([':db' => $databaseName, ':col' => $field['name']]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($result && (int)$result['cnt'] > 0) {
        $existed++;
        out("  ⊙ ฟิลด์ {$field['name']} มีอยู่แล้ว");
        continue;
      }

      // เพิ่มฟิลด์ใหม่
      $alterSql = sprintf(
        "ALTER TABLE users ADD COLUMN %s %s AFTER %s",
        $field['name'],
        $field['definition'],
        $field['after']
      );

      $pdo->exec($alterSql);
      $added++;
      out("  ✓ เพิ่มฟิลด์ {$field['name']} สำเร็จ");
    }

    if ($added > 0) {
      out(sprintf('  เพิ่มฟิลด์ใหม่ใน users: %d ฟิลด์', $added));
    }
    if ($existed > 0) {
      out(sprintf('  ฟิลด์ที่มีอยู่แล้ว: %d ฟิลด์', $existed));
    }
  } catch (Throwable $e) {
    out('  ✗ เกิดข้อผิดพลาดในการเพิ่มฟิลด์บัญชีธนาคาร: ' . $e->getMessage());
  }
}

/**
 * exit code
 */
const EXIT_OK      = 0;
const EXIT_DB_FAIL = 1;
const EXIT_PARTIAL = 2;

$appEnv  = envString('APP_ENV', 'local');
$isDebug = envBool('APP_DEBUG', false);

if (PHP_SAPI !== 'cli' && isProdEnv($appEnv)) {
  http_response_code(404);
  exit;
}

try {
  $schemaPath = __DIR__ . '/schema.sql';

  out('==============================================');
  out('  ตั้งค่าฐานข้อมูลสำหรับสิริณัฐ · พื้นที่เกษตรให้เช่า');
  out('  โหมด: UPDATE/UPSERT (ไม่ลบตาราง/ไม่ลบข้อมูล)');
  out('==============================================');
  out('Environment: ' . ($appEnv !== '' ? $appEnv : 'local'));
  out('');

  // health check
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
    out(sprintf("latency ฐานข้อมูล ~ %.2f ms", (float)$health['ping_time_ms']));
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

    // ✅ ข้าม statement อันตราย ไม่ให้ลบข้อมูล
    if (isDestructiveStatement($statement)) {
      $skipped++;
      out("  ⊙ [#{$index}] ข้าม: statement อันตราย (DROP/TRUNCATE/DELETE)");
      continue;
    }

    try {
      $pdo->exec($statement);
      $success++;

      if ($tableName !== null && str_starts_with($shortStmt, 'CREATE TABLE')) {
        out("  ✓ [#{$index}] สร้าง/อัปเดตตาราง {$tableName}");
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
          $driverCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
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
  out("  ⊙ ข้ามไป:   {$skipped} statements (ตารางซ้ำ/ข้อมูลซ้ำ/กันลบข้อมูล)");
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

  // seed จังหวัด/อำเภอ แบบ UPSERT
  out('');
  out('🌱 กำลังเติม/อัปเดตข้อมูล จังหวัด/อำเภอ (UPSERT)...');
  try {
    seedThaiAdministrativeDivisions($pdo);
    out('  ✓ เติม/อัปเดตข้อมูลจังหวัด/อำเภอเสร็จสมบูรณ์');
  } catch (Throwable $e) {
    out('  ✗ เติม/อัปเดตข้อมูลจังหวัด/อำเภอผิดพลาด: ' . $e->getMessage());
  }

  // เพิ่มฟิลด์บัญชีธนาคารใน users table
  out('');
  out('🏦 กำลังเช็คและเพิ่มฟิลด์บัญชีธนาคารใน users...');
  try {
    addUserBankFields($pdo, $dbName);
    out('  ✓ เช็คและเพิ่มฟิลด์บัญชีธนาคารเสร็จสมบูรณ์');
  } catch (Throwable $e) {
    out('  ✗ เช็คและเพิ่มฟิลด์บัญชีธนาคารผิดพลาด: ' . $e->getMessage());
  }

  out('');
  if (empty($errors)) {
    out('ตั้งค่าฐานข้อมูลเสร็จสมบูรณ์ (โหมด update-only)!');
    exit(EXIT_OK);
  }

  out('⚠️ ตั้งค่าฐานข้อมูลเสร็จ แต่มีบางส่วนผิดพลาด โปรดตรวจสอบ');
  if ($isDebug) {
    out('ℹ️ APP_DEBUG=true: คุณอาจเปิด log เพิ่มเติมฝั่ง MySQL/PHP เพื่อดูรายละเอียด');
  }
  exit(EXIT_PARTIAL);
} catch (Throwable $e) {
  out('❌ เกิดข้อผิดพลาดรุนแรง: ' . $e->getMessage());

  if ($isDebug) {
    out('--- DEBUG TRACE ---');
    out($e->getFile() . ':' . $e->getLine());
    out($e->getTraceAsString());
  }

  exit(EXIT_DB_FAIL);
}
