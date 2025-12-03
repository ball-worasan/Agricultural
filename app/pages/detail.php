<?php

declare(strict_types=1);

// ให้ไฟล์นี้ทำงานได้ทั้งกรณีถูก include ผ่าน index.php และถูกเปิดตรง ๆ (dev)
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2)); // จาก /app/public/pages → /app
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// รับ id จาก query
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    redirect('?page=home');
}

// ดึงข้อมูลพื้นที่จากฐานข้อมูล
$item = null;
try {
    $item = Database::fetchOne(
        'SELECT * FROM properties WHERE id = ? LIMIT 1',
        [$id]
    );
} catch (Throwable $e) {
    app_log('property_detail_fetch_error', [
        'property_id' => $id,
        'error'       => $e->getMessage(),
    ]);
}

if (!$item) {
?>
    <div class="container">
        <h1>ไม่พบข้อมูลพื้นที่ที่ต้องการ</h1>
        <a href="?page=home">กลับหน้าหลัก</a>
    </div>
<?php
    exit();
}

// ดึงรูปภาพทั้งหมดของพื้นที่นี้
$imageUrls = [];
try {
    $images    = Database::fetchAll(
        'SELECT image_url FROM property_images WHERE property_id = ? ORDER BY display_order',
        [$id]
    );
    $imageUrls = array_column($images, 'image_url');
} catch (Throwable $e) {
    app_log('property_detail_fetch_images_error', [
        'property_id' => $id,
        'error'       => $e->getMessage(),
    ]);
    $imageUrls = [];
}

// ถ้าไม่มีรูปใน property_images ให้ใช้ main_image
if (empty($imageUrls) && !empty($item['main_image'])) {
    $imageUrls = [(string) $item['main_image']];
}

// ถ้ายังไม่มีให้ใช้ placeholder
if (empty($imageUrls)) {
    $imageUrls = ['https://via.placeholder.com/800x600?text=No+Image'];
}

// กำหนดสถานะ
$statusText = [
    'available' => 'ว่าง',
    'booked'    => 'ติดจอง',
    'sold'      => 'ขายแล้ว',
];

$statusClass = [
    'available' => 'status-available',
    'booked'    => 'status-booked',
    'sold'      => 'status-sold',
];

$rawStatus   = (string) ($item['status'] ?? 'available');
$currentStatus = array_key_exists($rawStatus, $statusText) ? $rawStatus : 'available';

// คำนวณมัดจำ / ราคา
$priceRaw       = (int) ($item['price'] ?? 0);
$depositRaw     = (int) ceil($priceRaw / 12 ?: 0);
$deposit        = number_format($depositRaw);
$priceFormatted = number_format($priceRaw);

// ตรวจสอบว่าเป็นเจ้าของหรือไม่ + เตรียมข้อมูล user ที่ล็อกอิน
$isOwner       = false;
$loggedInUser  = current_user();
$userFullName  = 'ไม่ระบุ';
$userPhoneText = 'ไม่ระบุ';

if ($loggedInUser !== null) {
    $currentUserId = (int) ($loggedInUser['id'] ?? 0);
    $ownerId       = (int) ($item['owner_id'] ?? 0);
    $isOwner       = $currentUserId > 0 && $currentUserId === $ownerId;

    $firstName = (string) ($loggedInUser['firstname'] ?? '');
    $lastName  = (string) ($loggedInUser['lastname'] ?? '');
    $userFullName = trim($firstName . ' ' . $lastName) !== '' ? trim($firstName . ' ' . $lastName) : 'ไม่ระบุ';

    // ถ้า session ไม่มี phone ค่อยยิง DB เอา
    $phoneFromSession = $loggedInUser['phone'] ?? null;
    if ($phoneFromSession !== null && $phoneFromSession !== '') {
        $userPhoneText = (string) $phoneFromSession;
    } elseif (!empty($currentUserId)) {
        try {
            $userRow = Database::fetchOne(
                'SELECT phone FROM users WHERE id = ? LIMIT 1',
                [$currentUserId]
            );
            if ($userRow && !empty($userRow['phone'])) {
                $userPhoneText = (string) $userRow['phone'];
            }
        } catch (Throwable $e) {
            app_log('property_detail_fetch_user_phone_error', [
                'user_id' => $currentUserId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
?>
<div class="detail-container compact">
    <div class="detail-wrapper">
        <div class="detail-topbar">
            <a href="?page=home" class="back-button minimal" aria-label="กลับหน้ารายการ">ย้อนกลับ</a>
            <div class="topbar-right">
                <h1 class="detail-title"><?= e($item['title'] ?? ''); ?></h1>
                <?php if (!empty($item['location'])): ?>
                    <span class="meta-location">📍 <?= e($item['location']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-content">
            <div class="detail-left">
                <div class="image-gallery">
                    <div class="main-image-wrapper">
                        <img
                            data-src="<?= e($imageUrls[0]); ?>"
                            alt="<?= e($item['title'] ?? 'ภาพพื้นที่'); ?>"
                            id="mainImage"
                            class="main-image"
                            style="background: var(--skeleton-bg);">
                        <?php if (count($imageUrls) > 1): ?>
                            <button
                                type="button"
                                class="gallery-nav prev"
                                onclick="changeImage(-1)"
                                aria-label="รูปก่อนหน้า">
                                ‹
                            </button>
                            <button
                                type="button"
                                class="gallery-nav next"
                                onclick="changeImage(1)"
                                aria-label="รูปถัดไป">
                                ›
                            </button>
                            <div class="image-counter" id="imageCounter">
                                1 / <?= count($imageUrls); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($imageUrls) > 1): ?>
                        <div class="thumbs" id="thumbs">
                            <?php foreach ($imageUrls as $i => $u): ?>
                                <img
                                    data-src="<?= e($u); ?>"
                                    class="thumb <?= $i === 0 ? 'active' : ''; ?>"
                                    data-index="<?= (int) $i; ?>"
                                    alt="ตัวอย่างรูปที่ <?= (int) ($i + 1); ?>"
                                    style="background: var(--skeleton-bg);">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="description-box" id="descriptionBox">
                    <h2>รายละเอียด</h2>
                    <p><?= nl2br(e($item['description'] ?? '')); ?></p>
                </div>

                <div class="date-selection" id="dateSection" style="display: none;">
                    <h3>เลือกวันที่นัดหมาย</h3>
                    <div class="date-picker">
                        <select id="daySelect" class="date-select">
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?= $d; ?>"><?= $d; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="monthSelect" class="date-select">
                            <?php
                            $thaiMonths = [
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
                            foreach ($thaiMonths as $i => $month): ?>
                                <option value="<?= $i; ?>"><?= $month; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="yearSelect" class="date-select">
                            <?php
                            $currentYear = (int) date('Y');
                            for ($y = $currentYear; $y <= $currentYear + 2; $y++): ?>
                                <option value="<?= $y; ?>"><?= $y + 543; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="date-preview" id="datePreview"></div>
                </div>
            </div>

            <div class="detail-right">
                <div class="info-box">
                    <h2 id="boxTitle">ข้อมูลพื้นที่</h2>

                    <div id="userBookingInfo" style="display: none;">
                        <div class="user-info-item">
                            <strong>ผู้จอง:</strong> <?= e($userFullName); ?>
                        </div>
                        <div class="user-info-item">
                            <strong>เบอร์ติดต่อ:</strong> <?= e($userPhoneText); ?>
                        </div>
                    </div>

                    <div id="specsBox">
                        <div class="spec-item">
                            <span class="spec-label">📐 ขนาดพื้นที่:</span>
                            <span class="spec-value">
                                <?= e($item['area'] !== null && $item['area'] !== '' ? $item['area'] : 'ไม่ระบุ'); ?>
                            </span>
                        </div>
                        <?php if (!empty($item['soil_type'])): ?>
                            <div class="spec-item">
                                <span class="spec-label">🌱 ประเภทดิน:</span>
                                <span class="spec-value"><?= e($item['soil_type']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['irrigation'])): ?>
                            <div class="spec-item">
                                <span class="spec-label">💧 ระบบน้ำ:</span>
                                <span class="spec-value"><?= e($item['irrigation']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="statusBox" class="status-row">
                        <span class="status-label">สถานะ:</span>
                        <span class="status-badge <?= $statusClass[$currentStatus]; ?>">
                            <?= $statusText[$currentStatus]; ?>
                        </span>
                    </div>

                    <div class="price-section">
                        <div class="price-label">ราคาเช่า (ต่อปี)</div>
                        <div class="price-value">฿<?= e($priceFormatted); ?></div>
                        <div class="deposit-info">มัดจำ: ฿<?= e($deposit); ?></div>
                    </div>

                    <div id="normalButtons">
                        <?php if ($isOwner): ?>
                            <div class="owner-notice">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <span>นี่คือรายการของคุณ</span>
                            </div>
                            <a
                                href="?page=edit_property&id=<?= (int) $id; ?>"
                                class="btn-book btn-edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                <span>แก้ไขรายการ</span>
                            </a>
                        <?php elseif ($currentStatus === 'available'): ?>
                            <?php if ($loggedInUser !== null): ?>
                                <button
                                    type="button"
                                    class="btn-book"
                                    onclick="showBookingForm()">
                                    📝 จองพื้นที่เช่า
                                </button>
                            <?php else: ?>
                                <a href="?page=signin" class="btn-book">🔐 เข้าสู่ระบบเพื่อจอง</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button
                                type="button"
                                class="btn-book"
                                disabled
                                style="opacity: 0.5; cursor: not-allowed;">
                                <?= $currentStatus === 'booked' ? 'ติดจองแล้ว' : 'ขายแล้ว'; ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div id="bookingActions" style="display: none;">
                        <button type="button" class="btn-confirm" onclick="confirmBooking()">
                            ✅ ยืนยันการจอง
                        </button>
                        <button type="button" class="btn-cancel" onclick="cancelBooking()">
                            ❌ ยกเลิก
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const images = <?= json_encode($imageUrls, JSON_UNESCAPED_UNICODE); ?>;
    let currentImageIndex = 0;

    document.addEventListener('DOMContentLoaded', function() {
        const mainImg = document.getElementById('mainImage');
        if (mainImg && mainImg.dataset.src) {
            mainImg.src = mainImg.dataset.src;
            mainImg.removeAttribute('data-src');
        }

        if (mainImg) {
            mainImg.addEventListener('click', () => changeImage(1));
        }

        const thumbs = document.querySelectorAll('#thumbs .thumb');
        thumbs.forEach((t) => {
            t.addEventListener('click', () => {
                const idx = parseInt(t.getAttribute('data-index'), 10);
                if (Number.isNaN(idx)) return;
                currentImageIndex = idx;
                updateMainImage();
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') changeImage(-1);
            if (e.key === 'ArrowRight') changeImage(1);
        });
    });

    function updateMainImage() {
        const main = document.getElementById('mainImage');
        const counter = document.getElementById('imageCounter');
        if (!main || !images.length) return;

        main.src = images[currentImageIndex];

        if (counter) {
            counter.textContent = (currentImageIndex + 1) + ' / ' + images.length;
        }
        updateThumbActive();
    }

    function changeImage(direction) {
        if (!images.length) return;
        currentImageIndex += direction;
        if (currentImageIndex >= images.length) currentImageIndex = 0;
        if (currentImageIndex < 0) currentImageIndex = images.length - 1;
        updateMainImage();
    }

    function updateThumbActive() {
        const thumbs = document.querySelectorAll('#thumbs .thumb');
        thumbs.forEach((t, i) => {
            t.classList.toggle('active', i === currentImageIndex);
        });
    }

    /* ---------- Booking Flow ---------- */

    function showBookingForm() {
        const boxTitle = document.getElementById('boxTitle');
        const userInfo = document.getElementById('userBookingInfo');
        const specsBox = document.getElementById('specsBox');
        const descBox = document.getElementById('descriptionBox');
        const dateSection = document.getElementById('dateSection');
        const statusBox = document.getElementById('statusBox');
        const normalButtons = document.getElementById('normalButtons');
        const bookingActions = document.getElementById('bookingActions');

        if (!boxTitle) return;

        boxTitle.textContent = 'จองพื้นที่การเกษตร';

        if (userInfo) userInfo.style.display = 'block';
        if (specsBox) specsBox.style.display = 'none';
        if (descBox) descBox.style.display = 'none';
        if (dateSection) dateSection.style.display = 'block';
        if (statusBox) statusBox.style.display = 'none';
        if (normalButtons) normalButtons.style.display = 'none';
        if (bookingActions) bookingActions.style.display = 'flex';

        initializeDatePicker();
    }

    function initializeDatePicker() {
        const daySelect = document.getElementById('daySelect');
        const monthSelect = document.getElementById('monthSelect');
        const yearSelect = document.getElementById('yearSelect');
        if (!daySelect || !monthSelect || !yearSelect) return;

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);

        daySelect.value = String(tomorrow.getDate());
        monthSelect.value = String(tomorrow.getMonth());
        yearSelect.value = String(tomorrow.getFullYear());

        daySelect.addEventListener('change', updateDatePreview);
        monthSelect.addEventListener('change', () => {
            updateDaysInMonth();
            updateDatePreview();
        });
        yearSelect.addEventListener('change', () => {
            updateDaysInMonth();
            updateDatePreview();
        });

        updateDaysInMonth();
        updateDatePreview();
    }

    function updateDaysInMonth() {
        const daySelect = document.getElementById('daySelect');
        const monthSelect = document.getElementById('monthSelect');
        const yearSelect = document.getElementById('yearSelect');
        if (!daySelect || !monthSelect || !yearSelect) return;

        const month = parseInt(monthSelect.value, 10);
        const year = parseInt(yearSelect.value, 10);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const currentDay = parseInt(daySelect.value, 10) || 1;

        daySelect.innerHTML = '';
        for (let d = 1; d <= daysInMonth; d++) {
            const option = document.createElement('option');
            option.value = String(d);
            option.textContent = String(d);
            daySelect.appendChild(option);
        }
        daySelect.value = String(currentDay <= daysInMonth ? currentDay : daysInMonth);
    }

    function updateDatePreview() {
        const daySelect = document.getElementById('daySelect');
        const monthSelect = document.getElementById('monthSelect');
        const yearSelect = document.getElementById('yearSelect');
        const preview = document.getElementById('datePreview');
        if (!daySelect || !monthSelect || !yearSelect || !preview) return;

        const day = parseInt(daySelect.value, 10);
        const monthIndex = parseInt(monthSelect.value, 10);
        const year = parseInt(yearSelect.value, 10);

        const thaiMonths = [
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
            'ธันวาคม'
        ];

        const selectedDate = new Date(year, monthIndex, day);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        preview.style.display = 'block';

        if (selectedDate <= today) {
            preview.innerHTML =
                '<span class="error-text">กรุณาเลือกวันที่ถัดจากวันนี้อย่างน้อย 1 วัน</span>';
            return;
        }

        const buddhistYear = year + 543;
        const dateStr = day + ' ' + thaiMonths[monthIndex] + ' ' + buddhistYear;

        preview.innerHTML =
            '<span class="preview-text">คุณเลือกวันที่ ' + dateStr + '</span>';
    }

    function confirmBooking() {
        const dayEl = document.getElementById('daySelect');
        const monthEl = document.getElementById('monthSelect');
        const yearEl = document.getElementById('yearSelect');

        if (!dayEl || !monthEl || !yearEl) return;

        const day = dayEl.value;
        const month = monthEl.value;
        const year = yearEl.value;

        if (!day || !month || !year) return;

        const thaiMonths = [
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
            'ธันวาคม'
        ];
        const buddhistYear = parseInt(year, 10) + 543;
        const dateStr = day + ' ' + thaiMonths[parseInt(month, 10)] + ' ' + buddhistYear;

        const propertyId = <?= (int) $id; ?>;

        if (confirm('คุณต้องการจองพื้นที่นี้และนัดหมายวันที่ ' + dateStr + ' ใช่หรือไม่?')) {
            window.location.href =
                '?page=payment&id=' + propertyId +
                '&day=' + encodeURIComponent(day) +
                '&month=' + encodeURIComponent(month) +
                '&year=' + encodeURIComponent(year);
        }
    }

    function cancelBooking() {
        const boxTitle = document.getElementById('boxTitle');
        const userInfo = document.getElementById('userBookingInfo');
        const specsBox = document.getElementById('specsBox');
        const descBox = document.getElementById('descriptionBox');
        const dateSection = document.getElementById('dateSection');
        const statusBox = document.getElementById('statusBox');
        const normalButtons = document.getElementById('normalButtons');
        const bookingActions = document.getElementById('bookingActions');

        if (boxTitle) boxTitle.textContent = 'ข้อมูลพื้นที่';
        if (userInfo) userInfo.style.display = 'none';
        if (specsBox) specsBox.style.display = 'block';
        if (descBox) descBox.style.display = 'block';
        if (dateSection) dateSection.style.display = 'none';
        if (statusBox) statusBox.style.display = 'flex';
        if (normalButtons) normalButtons.style.display = 'block';
        if (bookingActions) bookingActions.style.display = 'none';
    }
</script>