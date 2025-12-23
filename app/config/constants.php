<?php

declare(strict_types=1);

/**
 * Role Constants
 * ตัวเลขที่ใช้แทนบทบาทของผู้ใช้ในระบบ
 */

// Role IDs
const ROLE_ADMIN = 1;      // ผู้ดูแลระบบ
const ROLE_MEMBER = 2;     // สมาชิกทั่วไป (ผู้ให้เช่า/ผู้เช่า)
const ROLE_GUEST = 3;      // ผู้ใช้ทั่วไป (ยังไม่สมัครสมาชิก)

/**
 * Map role ID to display name
 */
const ROLE_NAMES = [
  ROLE_ADMIN  => 'admin',
  ROLE_MEMBER => 'member',
  ROLE_GUEST  => 'guest',
];

/**
 * Map role name to role ID
 */
const ROLE_IDS = [
  'admin'  => ROLE_ADMIN,
  'member' => ROLE_MEMBER,
  'guest'  => ROLE_GUEST,
];
