<?php

declare(strict_types=1);

// ใช้ฐานข้อมูล + helpers (กันกรณีเผื่อเรียกไฟล์ตรง ๆ)
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// ตรวจสอบการล็อกอินให้ใช้ helper กลาง
$user = current_user();
if ($user === null) {
    flash('error', 'กรุณาเข้าสู่ระบบก่อนจัดการพื้นที่ของคุณ');
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    // กัน session เพี้ยน
    app_log('my_properties_invalid_user', ['session_user' => $user]);
    flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin');
}

// ดึงพื้นที่ของผู้ใช้ (เฉพาะ owner_id ของ user นี้)
$myProperties = [];
$imagesByProperty = [];

try {
    $myProperties = Database::fetchAll(
        '
        SELECT 
            p.*,
            COALESCE((
                SELECT COUNT(*) 
                FROM property_images 
                WHERE property_id = p.id
            ), 0) AS image_count,
            COALESCE((
                SELECT COUNT(*) 
                FROM bookings 
                WHERE property_id = p.id
            ), 0) AS booking_count
        FROM properties p
        WHERE p.owner_id = ?
        ORDER BY p.created_at DESC
        ',
        [$userId]
    );
} catch (Throwable $e) {
    app_log('my_properties_query_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
    $myProperties = [];
}

// ดึงรูปเฉพาะของ properties ที่เจอในหน้านี้
if (!empty($myProperties)) {
    $ids = array_column($myProperties, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $allImages = Database::fetchAll(
            "SELECT property_id, image_url 
             FROM property_images 
             WHERE property_id IN ({$placeholders})
             ORDER BY property_id, display_order",
            $ids
        );

        foreach ($allImages as $img) {
            $pid = (int) $img['property_id'];
            $imagesByProperty[$pid][] = $img['image_url'];
        }
    } catch (Throwable $e) {
        app_log('my_properties_images_query_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
        $imagesByProperty = [];
    }
}

// mapping สถานะ → text / class (กันกรณี status แปลก ๆ)
$statusText = [
    'available' => 'พร้อมเช่า',
    'booked'    => 'ติดจอง',
    'sold'      => 'ขายแล้ว',
];

$statusClass = [
    'available' => 'status-available',
    'booked'    => 'status-booked',
    'sold'      => 'status-sold',
];

?>
<div class="my-properties-container">
    <div class="page-header">
        <div class="header-left">
            <h1>พื้นที่ของฉัน</h1>
            <p class="subtitle">จัดการรายการที่ปล่อยเช่า</p>
        </div>
        <a href="?page=add_property" class="btn-add-property">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>เพิ่มรายการ</span>
        </a>
    </div>

    <?php if (empty($myProperties)): ?>
        <div class="empty-state">
            <svg class="empty-icon" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <h3>ยังไม่มีรายการ</h3>
            <p>เริ่มต้นปล่อยเช่าพื้นที่ของคุณ</p>
            <a href="?page=add_property" class="btn-add-first">เพิ่มรายการแรก</a>
        </div>
    <?php else: ?>
        <div class="properties-grid">
            <?php foreach ($myProperties as $property):

                $pid          = (int) $property['id'];
                $images       = $imagesByProperty[$pid] ?? [];
                $mainImage    = $property['main_image'] ?? ($images[0] ?? 'https://via.placeholder.com/400x300?text=No+Image');
                $price        = (float) $property['price'];
                $priceFormatted = number_format($price);

                $statusKey    = (string) ($property['status'] ?? 'available');
                $statusLabel  = $statusText[$statusKey] ?? 'ไม่ทราบสถานะ';
                $statusCss    = $statusClass[$statusKey] ?? 'status-unknown';

                $createdAtRaw = $property['created_at'] ?? null;
                $createdLabel = '-';
                if ($createdAtRaw) {
                    try {
                        $createdAt = new DateTimeImmutable((string) $createdAtRaw);
                        $createdLabel = $createdAt->format('d/m/Y');
                    } catch (Throwable $e) {
                        $createdLabel = e((string) $createdAtRaw);
                    }
                }

                $titleForJs = addslashes((string) $property['title']);
            ?>
                <div class="property-card">
                    <div class="card-image">
                        <img
                            src="<?= e($mainImage); ?>"
                            alt="<?= e($property['title'] ?? 'พื้นที่เกษตรของฉัน'); ?>">
                        <span class="status-badge <?= e($statusCss); ?>">
                            <?= e($statusLabel); ?>
                        </span>

                        <?php if ((int) ($property['image_count'] ?? 0) > 1): ?>
                            <span class="image-count">
                                <?= (int) $property['image_count']; ?> รูป
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="card-content">
                        <h3 class="property-title">
                            <?= e($property['title']); ?>
                        </h3>
                        <p class="property-location">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <?= e($property['location']); ?>
                            <?php if (!empty($property['province'])): ?>
                                , <?= e($property['province']); ?>
                            <?php endif; ?>
                        </p>

                        <div class="property-meta">
                            <div class="meta-item">
                                <span class="meta-label">ราคา/ปี:</span>
                                <span class="meta-value">฿<?= $priceFormatted; ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">การจอง:</span>
                                <span class="meta-value">
                                    <?= (int) $property['booking_count']; ?> รายการ
                                </span>
                            </div>
                        </div>

                        <div class="property-stats">
                            <div class="stat">
                                <svg class="stat-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <span class="stat-text"><?= e($createdLabel); ?></span>
                            </div>

                            <?php if (!empty($property['category'])): ?>
                                <div class="stat">
                                    <svg class="stat-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                        <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                    </svg>
                                    <span class="stat-text"><?= e($property['category']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <a href="?page=detail&id=<?= (int) $property['id']; ?>" class="btn-action view">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <span>ดูรายละเอียด</span>
                            </a>

                            <a href="?page=property_bookings&id=<?= (int) $property['id']; ?>" class="btn-action bookings">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <span>รายการจอง</span>
                                <?php if ((int) $property['booking_count'] > 0): ?>
                                    <span class="badge-count"><?= (int) $property['booking_count']; ?></span>
                                <?php endif; ?>
                            </a>

                            <a href="?page=edit_property&id=<?= (int) $property['id']; ?>" class="btn-action edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                <span>แก้ไข</span>
                            </a>

                            <button
                                type="button"
                                class="btn-action delete"
                                onclick="confirmDelete(<?= (int) $property['id']; ?>, '<?= $titleForJs; ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                </svg>
                                <span>ลบ</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    (function() {
        'use strict';

        window.confirmDelete = function(propertyId, propertyTitle) {
            var msg = 'คุณต้องการลบ "' + String(propertyTitle) + '" ใช่หรือไม่?\n\nการลบจะไม่สามารถกู้คืนได้';
            if (!confirm(msg)) {
                return;
            }

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '?page=delete_property';

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'property_id';
            input.value = String(propertyId);

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        };
    })();
</script>