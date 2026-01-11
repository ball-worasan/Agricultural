<?php

declare(strict_types=1);

/**
 * Application constants (immutable)
 * - ห้ามใส่ function ในไฟล์นี้
 * - ค่าที่เปลี่ยนตาม env ควรไปอยู่ bootstrap/Config loader ไม่ใช่ const
 */

// Locale default (fallback only; actual locale should come from env/config)
const DEFAULT_APP_LOCALE = 'th';

// Roles (IDs are persisted values)
const ROLE_ADMIN  = 1;
const ROLE_MEMBER = 2;
const ROLE_GUEST  = 3;

// System role keys (internal)
const ROLE_KEY_ADMIN  = 'admin';
const ROLE_KEY_MEMBER = 'member';
const ROLE_KEY_GUEST  = 'guest';

// Maps
const ROLE_ID_TO_KEY = [
  ROLE_ADMIN  => ROLE_KEY_ADMIN,
  ROLE_MEMBER => ROLE_KEY_MEMBER,
  ROLE_GUEST  => ROLE_KEY_GUEST,
];

const ROLE_KEY_TO_ID = [
  ROLE_KEY_ADMIN  => ROLE_ADMIN,
  ROLE_KEY_MEMBER => ROLE_MEMBER,
  ROLE_KEY_GUEST  => ROLE_GUEST,
];

// Optional: UI labels (ไทย) — ใช้แสดงผลในหน้าเว็บ
const ROLE_ID_TO_LABEL_TH = [
  ROLE_ADMIN  => 'ผู้ดูแลระบบ',
  ROLE_MEMBER => 'สมาชิก',
  ROLE_GUEST  => 'ผู้เยี่ยมชม',
];
