<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ทดสอบการเชื่อมต่อฐานข้อมูล</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box
    }

    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: radial-gradient(circle at top, #1e40af 0%, #0f172a 55%, #020617 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      color: #0f172a
    }

    .container {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 24px 80px rgba(15, 23, 42, .6);
      max-width: 720px;
      width: 100%;
      padding: 28px 24px 24px
    }

    h1 {
      color: #0f172a;
      margin-bottom: 20px;
      font-size: 1.6rem;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px
    }

    h1 span {
      font-size: 1.8rem
    }

    .status-box {
      padding: 18px 16px;
      border-radius: 10px;
      margin-bottom: 18px;
      border-left: 5px solid
    }

    .status-box.success {
      background: #dcfce7;
      border-color: #16a34a;
      color: #14532d
    }

    .status-box.error {
      background: #fee2e2;
      border-color: #dc2626;
      color: #7f1d1d
    }

    .status-icon {
      font-size: 2.6rem;
      text-align: center;
      margin-bottom: 6px
    }

    .status-message {
      font-size: 1.15rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: 6px
    }

    .status-sub {
      text-align: center;
      font-size: .9rem;
      opacity: .9
    }

    .info-grid {
      display: grid;
      gap: 10px;
      margin-top: 14px
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 10px;
      background: #f8fafc;
      border-radius: 8px
    }

    .info-label {
      font-weight: 600;
      color: #475569;
      font-size: .9rem
    }

    .info-value {
      color: #0f172a;
      font-family: "JetBrains Mono", "Courier New", monospace;
      font-size: .9rem;
      max-width: 60%;
      text-align: right;
      word-wrap: break-word
    }

    .info-value.ok {
      color: #15803d
    }

    .info-value.bad {
      color: #b91c1c
    }

    .error-detail {
      background: #fef3c7;
      border: 1px solid #facc15;
      border-radius: 8px;
      padding: 12px;
      margin-top: 10px;
      color: #854d0e;
      font-family: "JetBrains Mono", monospace;
      font-size: .8rem;
      word-wrap: break-word
    }

    .btn-row {
      display: flex;
      gap: 10px;
      margin-top: 18px;
      flex-wrap: wrap
    }

    .btn {
      flex: 1;
      min-width: 140px;
      padding: 11px 12px;
      border-radius: 999px;
      border: none;
      font-size: .95rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .18s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px
    }

    .btn-primary {
      background: #1d4ed8;
      color: #fff
    }

    .btn-primary:hover {
      background: #1e40af;
      transform: translateY(-1px);
      box-shadow: 0 8px 20px rgba(37, 99, 235, .35)
    }

    .btn-outline {
      background: #fff;
      color: #0f172a;
      border: 1px solid #cbd5f5
    }

    .btn-outline:hover {
      background: #eff6ff;
      transform: translateY(-1px)
    }

    .timestamp {
      text-align: center;
      color: #64748b;
      font-size: .8rem;
      margin-top: 14px
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: .7rem;
      font-weight: 600;
      letter-spacing: .03em;
      text-transform: uppercase;
      background: #eff6ff;
      color: #1e40af
    }

    .badge-dot {
      width: 6px;
      height: 6px;
      border-radius: 999px;
      background: #22c55e
    }

    .debug-note {
      margin-top: 6px;
      text-align: center;
      font-size: .8rem;
      color: #6b7280
    }
  </style>
</head>

<body>
  <div class="container">
    <h1><span>🔍</span>ทดสอบการเชื่อมต่อฐานข้อมูล</h1>

    <?php if (!empty($status['ok'])): ?>
      <div class="status-box success">
        <div class="status-icon">✅</div>
        <div class="status-message">เชื่อมต่อฐานข้อมูลสำเร็จ</div>
        <div class="status-sub">ระบบสามารถเชื่อมต่อ MySQL และรันคำสั่งตรวจสอบพื้นฐานได้</div>

        <div class="info-grid">
          <div class="info-row"><span class="info-label">สถานะ</span><span class="info-value ok">เชื่อมต่อแล้ว</span></div>
          <div class="info-row"><span class="info-label">Database</span><span class="info-value"><?= e($status['database'] ?? 'N/A'); ?></span></div>
          <div class="info-row"><span class="info-label">Host</span><span class="info-value"><?= e($status['host'] ?? 'N/A'); ?></span></div>
          <div class="info-row"><span class="info-label">Driver</span><span class="info-value"><?= e($status['driver'] ?? 'N/A'); ?></span></div>
          <div class="info-row"><span class="info-label">Server Version</span><span class="info-value"><?= e($status['server_version'] ?? 'N/A'); ?></span></div>
          <div class="info-row">
            <span class="info-label">Ping</span>
            <span class="info-value ok">
              OK
              <?php if (isset($status['ping_time_ms'])): ?>
                (<?= number_format((float)$status['ping_time_ms'], 2); ?> ms)
              <?php endif; ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (is_array($testQuery)): ?>
        <div class="status-box success">
          <div class="status-message" style="font-size:1rem;">ทดสอบ Query ตัวอย่าง</div>
          <div class="status-sub">SELECT DATABASE(), NOW(), VERSION()</div>
          <div class="info-grid">
            <div class="info-row"><span class="info-label">Database Name</span><span class="info-value"><?= e($testQuery['db_name'] ?? ''); ?></span></div>
            <div class="info-row"><span class="info-label">Server Time</span><span class="info-value"><?= e($testQuery['server_time'] ?? ''); ?></span></div>
            <div class="info-row"><span class="info-label">MySQL Version</span><span class="info-value"><?= e($testQuery['version'] ?? ''); ?></span></div>
          </div>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="status-box error">
        <div class="status-icon">❌</div>
        <div class="status-message">เชื่อมต่อฐานข้อมูลไม่สำเร็จ</div>
        <div class="status-sub">กรุณาตรวจสอบการตั้งค่า <code>.env</code> และสิทธิ์การเข้าถึงฐานข้อมูล</div>

        <div class="info-grid">
          <div class="info-row"><span class="info-label">สถานะ</span><span class="info-value bad">ไม่สามารถเชื่อมต่อ</span></div>
          <div class="info-row"><span class="info-label">Database</span><span class="info-value"><?= e($status['database'] ?? 'N/A'); ?></span></div>
          <div class="info-row"><span class="info-label">Host</span><span class="info-value"><?= e($status['host'] ?? 'N/A'); ?></span></div>
          <div class="info-row"><span class="info-label">Driver</span><span class="info-value"><?= e($status['driver'] ?? 'N/A'); ?></span></div>
        </div>

        <?php if (!empty($status['error'])): ?>
          <div class="error-detail">
            <strong>รายละเอียดข้อผิดพลาด:</strong><br>
            <?= e($isDebug ? (string)$status['error'] : 'มีข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาตรวจสอบ log หรือเปิดโหมด DEBUG'); ?>
          </div>
        <?php endif; ?>

        <?php if ($errorException instanceof Throwable && $isDebug): ?>
          <div class="error-detail" style="margin-top:8px;">
            <strong>Exception Debug (โหมด DEV):</strong><br>
            Message: <?= e($errorException->getMessage()); ?><br>
            File: <?= e($errorException->getFile()); ?><br>
            Line: <?= (int)$errorException->getLine(); ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="btn-row">
      <button class="btn btn-primary" onclick="location.reload()">🔄 ทดสอบอีกครั้ง</button>
      <button class="btn btn-outline" onclick="window.open('?format=json', '_blank')">📡 ดูข้อมูลในรูปแบบ JSON</button>
    </div>

    <div class="timestamp">
      <div class="badge"><span class="badge-dot"></span>HEALTH CHECK</div>
      <div style="margin-top:6px;">
        ⏰ เวลาทดสอบ:
        <?php
        try {
          $testTime = now('Asia/Bangkok');
          echo e($testTime->format('d/m/Y H:i:s'));
        } catch (Throwable) {
          echo e(date('d/m/Y H:i:s'));
        }
        ?>
      </div>
      <div class="debug-note">โหมดดีบัก: <?= $isDebug ? 'ON (APP_DEBUG=true)' : 'OFF'; ?></div>
    </div>
  </div>
</body>

</html>