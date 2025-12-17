<?php
// require APP_PATH . '/components/navbar.php';

app_session_start(); // ใช้ helper ตัวเดียวพอ (จะสร้าง CSRF token ให้ด้วย)

// ตรวจจับหน้าปัจจุบันแบบ fallback
$currentPage = $page ?? ($_GET['page'] ?? 'home');
$onHome = $currentPage === 'home';

// ดึงข้อมูลผู้ใช้จาก session แบบปลอดภัย
$user = $_SESSION['user'] ?? null;
$isAuthenticated = is_array($user) && !empty($user['id'] ?? null);
$userRole = $user['role'] ?? null;
$isAdmin = $isAuthenticated && $userRole === 'admin';

// แสดงชื่อในปุ่มบัญชี ถ้าไม่มีใช้ default
$displayName = 'บัญชีของฉัน';

if ($isAuthenticated) {
    $fullName = trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['lastname'] ?? ''));
    $displayName = $fullName !== '' ? $fullName : (string)($user['username'] ?? $displayName);
}

// เตรียมเมนูตามสถานะล็อกอิน
$authMenuItems = [];
$guestMenuItems = [];

// เมนูผู้ใช้ล็อกอินแล้ว
if ($isAuthenticated) {
    $authMenuItems = [
        [
            'href'      => '?page=home',
            'label'     => 'รายการพื้นที่เกษตรให้เช่า',
            'page'      => 'home',
            'highlight' => false,
        ],
        [
            'href'      => '?page=my_properties',
            'label'     => 'พื้นที่ปล่อยเช่าของฉัน',
            'page'      => 'my_properties',
            'highlight' => false,
        ],
        [
            'href'      => '?page=history',
            'label'     => 'ประวัติการเช่าพื้นที่เกษตร',
            'page'      => 'history',
            'highlight' => false,
        ],
        [
            'href'      => '?page=profile',
            'label'     => 'ข้อมูลสมาชิก',
            'page'      => 'profile',
            'highlight' => false,
        ],
    ];

    if ($isAdmin) {
        $authMenuItems[] = [
            'href'      => '?page=admin_dashboard',
            'label'     => 'แดชบอร์ดผู้ดูแล',
            'page'      => 'admin_dashboard',
            'highlight' => true,
        ];
    }
} else {
    // เมนูผู้ใช้ guest
    $guestMenuItems = [
        [
            'href'  => '?page=signin',
            'label' => 'เข้าสู่ระบบ',
            'page'  => 'signin',
        ],
        [
            'href'  => '?page=signup',
            'label' => 'สมัครสมาชิก',
            'page'  => 'signup',
        ],
    ];
}

// helper เล็ก ๆ เพื่อ highlight เมนูหน้าปัจจุบัน
$activePage = $currentPage;

// ป้องกัน id ชนกันถ้ามี navbar หลายอันในหน้าเดียว
try {
    $navInstanceId = bin2hex(random_bytes(4));
} catch (Throwable $e) {
    $navInstanceId = uniqid('nav', false);
}

$accountBtnId  = 'accountBtn-' . $navInstanceId;
$accountMenuId = 'accountMenu-' . $navInstanceId;
?>

<nav
    class="navbar"
    role="navigation"
    aria-label="แถบนำทางหลัก"
    data-nav-id="<?php echo e($navInstanceId); ?>">
    <div class="nav-inner">
        <div class="nav-left">
            <a href="?page=home" class="brand" aria-label="ไปหน้าหลัก">
                สิริณัฐ · พื้นที่การเกษตรให้เช่า
            </a>
        </div>

        <div class="nav-center">
            <?php if ($onHome): ?>
                <input
                    type="search"
                    id="globalSearch"
                    class="nav-search"
                    placeholder="ค้นหาแปลงเกษตรหรือทำเล..."
                    aria-label="ค้นหารายการพื้นที่เกษตร"
                    autocomplete="off" />
            <?php endif; ?>
        </div>

        <div class="nav-right">
            <div class="nav-account">
                <?php if ($isAuthenticated): ?>
                    <button
                        type="button"
                        class="account-btn"
                        id="<?php echo e($accountBtnId); ?>"
                        data-account-btn="true"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="<?php echo e($accountMenuId); ?>"
                        aria-label="เมนูบัญชีของ <?php echo e($displayName); ?>">
                        <?php echo e($displayName); ?>
                    </button>

                    <div
                        class="account-menu"
                        id="<?php echo e($accountMenuId); ?>"
                        role="menu"
                        hidden
                        data-account-menu="true"
                        data-menu-root="account">

                        <?php foreach ($authMenuItems as $item): ?>
                            <?php
                            $isActive = ($item['page'] === $activePage);
                            $classes = ['menu-item'];
                            if (!empty($item['highlight'])) {
                                $classes[] = 'highlight';
                            }
                            if ($isActive) {
                                $classes[] = 'is-active';
                            }
                            ?>
                            <a
                                class="<?php echo e(implode(' ', $classes)); ?>"
                                href="<?php echo e($item['href']); ?>"
                                role="menuitem"
                                data-menu-item="true">
                                <?php echo e($item['label']); ?>
                            </a>
                        <?php endforeach; ?>

                        <!-- Logout: POST + CSRF -->
                        <form
                            method="post"
                            action="?page=<?php echo e($currentPage); ?>"
                            class="menu-item-form"
                            data-menu-item="true"
                            role="none">
                            <input type="hidden" name="action" value="logout">
                            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">

                            <button
                                type="submit"
                                class="menu-item danger menu-btn"
                                role="menuitem">
                                ออกจากระบบ
                            </button>
                        </form>

                    </div>
                <?php else: ?>
                    <button
                        type="button"
                        class="account-btn"
                        id="<?php echo e($accountBtnId); ?>"
                        data-account-btn="true"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="<?php echo e($accountMenuId); ?>"
                        aria-label="เมนูบัญชีสำหรับผู้ใช้ทั่วไป">
                        เมนู
                    </button>

                    <div
                        class="account-menu"
                        id="<?php echo e($accountMenuId); ?>"
                        role="menu"
                        hidden
                        data-account-menu="true"
                        data-menu-root="account">

                        <?php foreach ($guestMenuItems as $item): ?>
                            <?php
                            $isActive = ($item['page'] === $activePage);
                            $classes = ['menu-item'];
                            if ($isActive) {
                                $classes[] = 'is-active';
                            }
                            ?>
                            <a
                                class="<?php echo e(implode(' ', $classes)); ?>"
                                href="<?php echo e($item['href']); ?>"
                                role="menuitem"
                                data-menu-item="true">
                                <?php echo e($item['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
    (() => {
        'use strict';

        const nav = document.querySelector('.navbar[data-nav-id="<?php echo e($navInstanceId); ?>"]');
        if (!nav) return;

        const accountBtn = nav.querySelector('[data-account-btn="true"]');
        const accountMenu = nav.querySelector('[data-account-menu="true"]');
        const searchInput = nav.querySelector('#globalSearch');

        if (!accountBtn || !accountMenu) return;

        const menuItems = Array.from(
            accountMenu.querySelectorAll('[data-menu-item="true"], .menu-btn')
        );

        const openMenu = () => {
            accountMenu.removeAttribute('hidden');
            accountMenu.classList.add('is-open');
            accountBtn.setAttribute('aria-expanded', 'true');
        };

        const closeMenu = () => {
            accountMenu.setAttribute('hidden', '');
            accountMenu.classList.remove('is-open');
            accountBtn.setAttribute('aria-expanded', 'false');
        };

        const isMenuOpen = () => !accountMenu.hasAttribute('hidden');

        const focusFirstItem = () => {
            const first = menuItems.find(el => el && typeof el.focus === 'function');
            if (first) first.focus();
        };

        const focusLastItem = () => {
            const last = [...menuItems].reverse().find(el => el && typeof el.focus === 'function');
            if (last) last.focus();
        };

        const focusNextItem = (current) => {
            if (!menuItems.length) return;
            const index = menuItems.indexOf(current);
            const nextIndex = (index + 1) % menuItems.length;
            menuItems[nextIndex]?.focus?.();
        };

        const focusPrevItem = (current) => {
            if (!menuItems.length) return;
            const index = menuItems.indexOf(current);
            const prevIndex = (index - 1 + menuItems.length) % menuItems.length;
            menuItems[prevIndex]?.focus?.();
        };

        // Toggle เมนูเมื่อคลิกปุ่มบัญชี
        accountBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (isMenuOpen()) {
                closeMenu();
            } else {
                openMenu();
                focusFirstItem();
            }
        });

        // เปิดเมนูด้วยคีย์บอร์ดจากปุ่ม
        accountBtn.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!isMenuOpen()) openMenu();
                focusFirstItem();
            }
        });

        // ปิดเมื่อคลิกข้างนอก
        document.addEventListener('click', (e) => {
            if (!isMenuOpen()) return;
            if (!accountMenu.contains(e.target) && !accountBtn.contains(e.target)) {
                closeMenu();
            }
        });

        // ปิดเมื่อกด Escape ที่ใดก็ได้
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isMenuOpen()) {
                closeMenu();
                accountBtn.focus();
            }
        });

        // ควบคุมคีย์บอร์ดภายในเมนู (ArrowUp/Down, Home/End)
        accountMenu.addEventListener('keydown', (e) => {
            const currentItem =
                e.target.closest('[data-menu-item="true"]') ||
                e.target.closest('.menu-btn');

            if (!currentItem) return;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    focusNextItem(currentItem);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    focusPrevItem(currentItem);
                    break;
                case 'Home':
                    e.preventDefault();
                    focusFirstItem();
                    break;
                case 'End':
                    e.preventDefault();
                    focusLastItem();
                    break;
                case 'Escape':
                    e.preventDefault();
                    closeMenu();
                    accountBtn.focus();
                    break;
            }
        });

        // คลิกเมนู item → ปิดเมนูให้เลย (รวมปุ่ม logout ด้วย)
        accountMenu.addEventListener('click', (e) => {
            const disabledItem = e.target.closest('[data-disabled="true"]');
            if (disabledItem) {
                e.preventDefault();
                alert('ฟีเจอร์นี้จะพร้อมใช้งานเร็วๆ นี้');
                return;
            }

            const menuItem =
                e.target.closest('[data-menu-item="true"]') ||
                e.target.closest('.menu-btn');

            if (menuItem && !disabledItem) {
                closeMenu();
            }
        });

        // logic สำหรับ search ทั่วไป (home page)
        if (searchInput) {
            let searchTimeout = null;

            const emitSearchEvent = (value) => {
                const event = new CustomEvent('global:search-change', {
                    detail: {
                        value
                    },
                });
                window.dispatchEvent(event);
            };

            searchInput.addEventListener('input', (e) => {
                const value = e.target.value.trim();

                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => emitSearchEvent(value), 250);
            });
        }
    })();
</script>