<?php

declare(strict_types=1);

// home.php ถูก include จาก index.php แล้ว (มี helpers + session + navbar)
// แต่ Database ยังต้อง require เอง ถ้า index.php ไม่ได้ require ไว้
require_once APP_PATH . '/config/Database.php';

// current_user() จะเรียก session ให้เองผ่าน helpers.php
$user   = current_user();
$userId = isset($user['id']) ? (int) $user['id'] : null;

// ตั้งค่าพื้นฐานสำหรับ pagination
if (!defined('PROPERTIES_PER_PAGE')) {
    define('PROPERTIES_PER_PAGE', 24);
}

$pgParam     = isset($_GET['pg']) ? (int) $_GET['pg'] : 1;
$currentPage = $pgParam > 0 ? $pgParam : 1;
$offset      = ($currentPage - 1) * PROPERTIES_PER_PAGE;

// เฉพาะประกาศที่ active (1 หรือ NULL) และยังไม่ขาย
$whereBase = 'WHERE (is_active = 1 OR is_active IS NULL) AND status IN ("available", "booked")';

// นับจำนวนทั้งหมดเพื่อทำ pagination
$totalRow = 0;
try {
    $row      = Database::fetchOne("SELECT COUNT(*) AS cnt FROM properties {$whereBase}");
    $totalRow = isset($row['cnt']) ? (int) $row['cnt'] : 0;

    app_log('home_total_properties', [
        'totalRow'  => $totalRow,
        'whereBase' => $whereBase,
    ]);
} catch (Throwable $e) {
    app_log('home_count_error', [
        'message' => $e->getMessage(),
        'where'   => $whereBase,
    ]);
    $totalRow = 0;
}

$totalPages = max(1, (int) ceil($totalRow / PROPERTIES_PER_PAGE));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset      = ($currentPage - 1) * PROPERTIES_PER_PAGE;
}

// ดึง properties (page ปัจจุบัน)
$items            = [];
$imagesByProperty = [];

try {
    $limit  = (int) PROPERTIES_PER_PAGE;
    $offset = max(0, (int) $offset);

    // ใช้ prepared statement สำหรับ LIMIT/OFFSET แบบปลอดภัย
    $pdo = Database::connection();

    $sql = "
        SELECT 
            p.id, p.owner_id, p.title, p.location, p.province, p.category,
            p.has_water, p.has_electric, p.price, p.status,
            p.main_image, p.description, p.created_at
        FROM properties p
        {$whereBase}
        ORDER BY p.created_at DESC
        LIMIT :offset, :limit
    ";

    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Failed to prepare home list query');
    }

    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // โหลดรูปทั้งหมดของ property ที่อยู่ในหน้านี้
    if (!empty($items)) {
        $ids = array_map('intval', array_column($items, 'id'));
        $ids = array_values(array_filter($ids, fn($v) => $v > 0));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $allImages = Database::fetchAll(
                "SELECT property_id, image_url 
                 FROM property_images 
                 WHERE property_id IN ({$placeholders})
                 ORDER BY property_id, display_order",
                $ids
            );

            foreach ($allImages as $img) {
                $pid = isset($img['property_id']) ? (int) $img['property_id'] : 0;
                if ($pid <= 0) continue;

                $imagesByProperty[$pid] ??= [];
                $imagesByProperty[$pid][] = (string)($img['image_url'] ?? '');
            }
        }
    }
} catch (Throwable $e) {
    app_log('home_page_query_error', ['message' => $e->getMessage()]);
    $items = [];
    $imagesByProperty = [];
}

// รายชื่อจังหวัด (แทนการเขียน option ยาว ๆ)
$thaiProvinces = [
    'กระบี่',
    'กรุงเทพมหานคร',
    'กาญจนบุรี',
    'กาฬสินธุ์',
    'กำแพงเพชร',
    'ขอนแก่น',
    'จันทบุรี',
    'ฉะเชิงเทรา',
    'ชลบุรี',
    'ชัยนาท',
    'ชัยภูมิ',
    'ชุมพร',
    'เชียงราย',
    'เชียงใหม่',
    'ตรัง',
    'ตราด',
    'ตาก',
    'นครนายก',
    'นครปฐม',
    'นครพนม',
    'นครราชสีมา',
    'นครศรีธรรมราช',
    'นครสวรรค์',
    'นนทบุรี',
    'นราธิวาส',
    'น่าน',
    'บึงกาฬ',
    'บุรีรัมย์',
    'ปทุมธานี',
    'ประจวบคีรีขันธ์',
    'ปราจีนบุรี',
    'ปัตตานี',
    'พระนครศรีอยุธยา',
    'พังงา',
    'พัทลุง',
    'พิจิตร',
    'พิษณุโลก',
    'เพชรบุรี',
    'เพชรบูรณ์',
    'แพร่',
    'พะเยา',
    'ภูเก็ต',
    'มหาสารคาม',
    'มุกดาหาร',
    'แม่ฮ่องสอน',
    'ยโสธร',
    'ยะลา',
    'ร้อยเอ็ด',
    'ระนอง',
    'ระยอง',
    'ราชบุรี',
    'ลพบุรี',
    'ลำปาง',
    'ลำพูน',
    'เลย',
    'ศรีสะเกษ',
    'สกลนคร',
    'สงขลา',
    'สตูล',
    'สมุทรปราการ',
    'สมุทรสงคราม',
    'สมุทรสาคร',
    'สระแก้ว',
    'สระบุรี',
    'สิงห์บุรี',
    'สุโขทัย',
    'สุพรรณบุรี',
    'สุราษฎร์ธานี',
    'สุรินทร์',
    'หนองคาย',
    'หนองบัวลำพู',
    'อ่างทอง',
    'อุดรธานี',
    'อุทัยธานี',
    'อุตรดิตถ์',
    'อุบลราชธานี',
    'อำนาจเจริญ',
];

// category ที่มีในระบบ (ตรงกับ DB)
$categories = ['ไร่นา', 'สวนผลไม้', 'แปลงผัก', 'เลี้ยงสัตว์'];

?>
<div class="home-container">
    <!-- Filter Section (Agriculture) -->
    <div class="filter-section">
        <div class="filter-left">
            <div class="filter-group">
                <label for="province">จังหวัด</label>
                <select id="province" name="province" onchange="filterItems()">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($thaiProvinces as $prov): ?>
                        <option value="<?= e($prov); ?>"><?= e($prov); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="price">ราคาเช่า (บาท/ปี)</label>
                <select id="price" name="price" onchange="filterItems()">
                    <option value="">ทั้งหมด</option>
                    <option value="0-10000">0 - 10,000</option>
                    <option value="10000-20000">10,000 - 20,000</option>
                    <option value="20000-30000">20,000 - 30,000</option>
                    <option value="30000-50000">30,000 - 50,000</option>
                    <option value="50000-100000">50,000 - 100,000</option>
                    <option value="100000-200000">100,000 - 200,000</option>
                    <option value="200000-500000">200,000 - 500,000</option>
                    <option value="500000-1000000">500,000 - 1,000,000</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="tag">หมวดหมู่ / คุณสมบัติ</label>
                <select id="tag" name="tag" onchange="filterItems()">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat); ?>"><?= e($cat); ?></option>
                    <?php endforeach; ?>
                    <option value="น้ำพร้อมใช้">น้ำพร้อมใช้</option>
                    <option value="ไฟฟ้าพร้อมใช้">ไฟฟ้าพร้อมใช้</option>
                    <option value="ติดจอง">มีผู้จอง</option>
                    <option value="พร้อมเช่า">พร้อมเช่า</option>
                </select>
            </div>
        </div>

        <div class="filter-right">
            <div class="filter-group">
                <label for="sort">เรียงตาม</label>
                <select id="sort" name="sort" onchange="filterItems()">
                    <option value="latest">ล่าสุด</option>
                    <option value="oldest">เก่าสุด</option>
                    <option value="price-low">ราคาต่ำ-สูง</option>
                    <option value="price-high">ราคาสูง-ต่ำ</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Items List Section (Compact) -->
    <div class="items-section compact" id="itemsContainer">
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🌾</div>
                <div class="empty-state-title">ยังไม่มีพื้นที่เกษตรให้เช่า</div>
                <div class="empty-state-desc">
                    กรุณากลับมาตรวจสอบใหม่อีกครั้งหรือติดต่อผู้ดูแลระบบ
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item):

                $propertyId = isset($item['id']) ? (int) $item['id'] : 0;
                if ($propertyId <= 0) continue;

                $priceRaw   = isset($item['price']) ? (int) $item['price'] : 0;
                $depositRaw = (int) ceil($priceRaw / 12); // สมมติให้มัดจำ ~ 1 เดือน
                $status     = isset($item['status']) ? (string) $item['status'] : 'available';

                $isBooked = $status === 'booked';
                $ownerId  = isset($item['owner_id']) ? (int) $item['owner_id'] : null;
                $isOwner  = $userId !== null && $ownerId !== null && $ownerId === $userId;

                $cardClass = $isBooked ? 'item-card booked' : 'item-card';

                $images    = $imagesByProperty[$propertyId] ?? [];
                $mainImage = (!empty($item['main_image']) ? (string) $item['main_image'] : '')
                    ?: (!empty($images) ? (string) $images[0] : 'https://via.placeholder.com/400x300?text=No+Image');

                $createdAt = isset($item['created_at']) ? (string) $item['created_at'] : '';
                try {
                    $dateObj = $createdAt !== '' ? new DateTimeImmutable($createdAt) : now();
                } catch (Exception $e) {
                    $dateObj = now();
                }

                $dataDate    = $dateObj->format('Y-m-d');
                $displayDate = $dateObj->format('d M Y');

                // สร้างแท็กจากข้อมูลจริงใน DB
                $tags = [];

                if (!empty($item['category'])) {
                    $tags[] = (string) $item['category'];
                }

                $tags[] = $isBooked ? 'ติดจอง' : 'พร้อมเช่า';

                if (isset($item['has_water']) && (int) $item['has_water'] === 1) {
                    $tags[] = 'น้ำพร้อมใช้';
                }
                if (isset($item['has_electric']) && (int) $item['has_electric'] === 1) {
                    $tags[] = 'ไฟฟ้าพร้อมใช้';
                }

                // Fallback: เดาจาก title/description ถ้า field ยังไม่ set
                $descText   = isset($item['description']) ? (string) $item['description'] : '';
                $titleText  = isset($item['title']) ? (string) $item['title'] : '';
                $descLower  = mb_strtolower($descText, 'UTF-8');
                $titleLower = mb_strtolower($titleText, 'UTF-8');

                if (empty($item['category'])) {
                    if (mb_strpos($titleLower, 'ไร่') !== false) $tags[] = 'ไร่นา';
                    if (mb_strpos($titleLower, 'สวน') !== false) $tags[] = 'สวนผลไม้';
                    if (mb_strpos($titleLower, 'ผัก') !== false) $tags[] = 'แปลงผัก';
                    if (mb_strpos($titleLower, 'เลี้ยง') !== false) $tags[] = 'เลี้ยงสัตว์';
                }

                if ((!isset($item['has_water']) || (int) $item['has_water'] !== 1) && mb_strpos($descLower, 'น้ำ') !== false) {
                    $tags[] = 'น้ำพร้อมใช้';
                }
                if (
                    (!isset($item['has_electric']) || (int) $item['has_electric'] !== 1)
                    && (mb_strpos($descLower, 'ไฟ') !== false || mb_strpos($descLower, 'ไฟฟ้า') !== false)
                ) {
                    $tags[] = 'ไฟฟ้าพร้อมใช้';
                }

                $tagsAttr = implode(',', array_values(array_unique($tags)));
                $province = isset($item['province']) ? (string) $item['province'] : '';
            ?>
                <a
                    href="?page=detail&id=<?= $propertyId; ?>"
                    class="<?= e($cardClass); ?>"
                    style="text-decoration: none; color: inherit;"
                    data-province="<?= e($province); ?>"
                    data-price="<?= (int)$priceRaw; ?>"
                    data-deposit="<?= (int)$depositRaw; ?>"
                    data-date="<?= e($dataDate); ?>"
                    data-tags="<?= e($tagsAttr); ?>">
                    <div class="card-badges">
                        <?php if ($isOwner): ?>
                            <span class="badge badge-owner">ของคุณ</span>
                        <?php endif; ?>
                        <?php if ($isBooked): ?>
                            <span class="badge badge-booked">Booked</span>
                        <?php else: ?>
                            <span class="badge badge-available">พร้อมเช่า</span>
                        <?php endif; ?>
                    </div>

                    <div class="item-image">
                        <img
                            data-src="<?= e($mainImage); ?>"
                            alt="<?= e($titleText !== '' ? $titleText : 'พื้นที่เกษตรให้เช่า'); ?>"
                            loading="lazy"
                            style="background: var(--skeleton-bg);">
                    </div>

                    <div class="item-details">
                        <h3 class="item-title"><?= e($titleText); ?></h3>
                        <p class="item-location">
                            <?= e((string)($item['location'] ?? '')); ?><?= $province !== '' ? ', ' . e($province) : ''; ?>
                        </p>

                        <div class="item-meta">
                            <span class="meta-price">
                                <?= number_format($priceRaw); ?> บาท/ปี
                            </span>
                            <span class="meta-deposit">
                                มัดจำประมาณ <?= number_format($depositRaw); ?> บาท
                            </span>
                            <span class="meta-date">
                                <?= e($displayDate); ?>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a class="page-link" href="?page=home&pg=<?= $currentPage - 1; ?>">ก่อนหน้า</a>
            <?php endif; ?>

            <span class="page-info">
                หน้า <?= (int)$currentPage; ?> / <?= (int)$totalPages; ?>
            </span>

            <?php if ($currentPage < $totalPages): ?>
                <a class="page-link" href="?page=home&pg=<?= $currentPage + 1; ?>">ถัดไป</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // state สำหรับ search จาก navbar
    let globalSearchText = '';

    function filterItems() {
        const provinceSelect = document.getElementById('province');
        const priceSelect = document.getElementById('price');
        const sortSelect = document.getElementById('sort');
        const tagSelect = document.getElementById('tag');

        const container = document.getElementById('itemsContainer');
        const emptyEl = document.getElementById('homeEmptyState');

        if (!container) return;

        // ถ้าไม่มีรายการเลย (หน้าโล่ง / มีแต่ empty state แบบ "ยังไม่มีพื้นที่")
        // อย่าโชว์ empty state ของ "ผลลัพธ์การกรอง"
        const items = Array.from(container.querySelectorAll('.item-card'));
        if (items.length === 0) {
            if (emptyEl) emptyEl.hidden = true;
            return;
        }

        const provinceFilter = provinceSelect ? provinceSelect.value : '';
        const priceFilter = priceSelect ? priceSelect.value : '';
        const sortFilter = sortSelect ? sortSelect.value : 'latest';
        const tagFilter = tagSelect ? tagSelect.value : '';

        const queryInput = document.getElementById('globalSearch');
        const query = ((queryInput && queryInput.value) || globalSearchText || '').trim().toLowerCase();

        items.forEach((item) => {
            let showItem = true;

            if (query) {
                const titleEl = item.querySelector('.item-title');
                const locationEl = item.querySelector('.item-location');
                const title = titleEl ? (titleEl.textContent || '').toLowerCase() : '';
                const location = locationEl ? (locationEl.textContent || '').toLowerCase() : '';

                if (!title.includes(query) && !location.includes(query)) {
                    showItem = false;
                }
            }

            if (provinceFilter) {
                const itemProvince = item.getAttribute('data-province') || '';
                if (itemProvince !== provinceFilter) showItem = false;
            }

            if (priceFilter) {
                const itemPrice = parseInt(item.getAttribute('data-price') || '0', 10) || 0;
                const parts = priceFilter.split('-');
                if (parts.length === 2) {
                    const minPrice = parseInt(parts[0], 10) || 0;
                    const maxPrice = parseInt(parts[1], 10) || 0;
                    if (itemPrice < minPrice || itemPrice > maxPrice) showItem = false;
                }
            }

            if (tagFilter) {
                const tagsRaw = (item.getAttribute('data-tags') || '').toLowerCase();
                const tagsArr = tagsRaw.split(',').map(t => t.trim()).filter(Boolean);
                if (!tagsArr.includes(tagFilter.toLowerCase())) showItem = false;
            }

            item.style.display = showItem ? 'flex' : 'none';
        });

        const visibleItems = items.filter((item) => item.style.display !== 'none');

        if (!visibleItems.length) {
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;

        // sort เฉพาะ items ที่ยังแสดงอยู่
        visibleItems.sort((a, b) => {
            const priceA = parseInt(a.getAttribute('data-price') || '0', 10) || 0;
            const priceB = parseInt(b.getAttribute('data-price') || '0', 10) || 0;

            const dateAStr = a.getAttribute('data-date') || '';
            const dateBStr = b.getAttribute('data-date') || '';
            const dateA = dateAStr ? new Date(dateAStr) : new Date(0);
            const dateB = dateBStr ? new Date(dateBStr) : new Date(0);

            switch (sortFilter) {
                case 'price-low':
                    return priceA - priceB;
                case 'price-high':
                    return priceB - priceA;
                case 'oldest':
                    return dateA - dateB;
                case 'latest':
                default:
                    return dateB - dateA;
            }
        });

        visibleItems.forEach((item) => container.appendChild(item));
    }

    document.addEventListener('DOMContentLoaded', () => {
        // ใช้ระบบ lazy loading จาก utilities.js (ถ้ามี)
        if (typeof initLazyLoading === 'function') {
            initLazyLoading();
        }

        // ฟัง event จาก navbar (global search)
        window.addEventListener('global:search-change', (event) => {
            if (event && event.detail && typeof event.detail.value === 'string') {
                globalSearchText = event.detail.value.toLowerCase();
            } else {
                globalSearchText = '';
            }
            filterItems();
        });

        // เรียกครั้งแรกให้ state ตรงกับ default
        filterItems();
    });
</script>