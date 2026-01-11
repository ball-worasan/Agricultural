<?php

declare(strict_types=1);

/**
 * Route schema:
 * - title: string
 * - view: string (path under app/pages without .php)
 * - css/js: list of relative files (no leading slash), will be mapped by build_page_css/js
 * - flags: auth | admin | guest_only (bool)
 */

$R = static function (
  string $title,
  string $view,
  array $css = [],
  array $js = [],
  array $flags = []
): array {
  // normalize list
  $normList = static function (array $xs): array {
    $xs = array_map('strval', $xs);
    $xs = array_map('trim', $xs);
    $xs = array_values(array_filter($xs, static fn($v) => $v !== ''));
    return $xs;
  };

  $route = [
    'title' => $title,
    'view'  => $view,
    'css'   => $normList($css),
    'js'    => $normList($js),
  ];

  // flags (ensure bool)
  foreach (['auth', 'admin', 'guest_only'] as $k) {
    if (array_key_exists($k, $flags)) {
      $route[$k] = (bool)$flags[$k];
    }
  }

  return $route;
};

return [
  'home'   => $R('พื้นที่การเกษตรให้เช่า', 'home', ['pages/home.css'], ['pages/home.js']),

  'signin' => $R('เข้าสู่ระบบ', 'signin', ['pages/signin.css'], ['pages/signin.js'], ['guest_only' => true]),
  'signup' => $R('สมัครสมาชิก', 'signup', ['pages/signup.css'], ['pages/signup.js'], ['guest_only' => true]),

  'profile' => $R('โปรไฟล์สมาชิกเกษตร', 'profile', ['pages/profile.css'], ['pages/profile.js'], ['auth' => true]),
  'detail'  => $R('รายละเอียดแปลงเกษตร', 'detail', ['pages/detail.css'], ['pages/detail.js']),

  'payment'  => $R('ชำระมัดจำเช่าพื้นที่เกษตร', 'payment', ['pages/payment.css'], ['pages/payment.js'], ['auth' => true]),
  'contract' => $R('ทำสัญญาเช่า 1 ปี', 'contract', ['pages/contract.css'], [], ['auth' => true]),

  'history' => $R('ประวัติการเช่าพื้นที่เกษตร', 'history', ['pages/history.css'], ['pages/history.js'], ['auth' => true]),

  'my_properties' => $R(
    'พื้นที่ปล่อยเช่าของฉัน',
    'properties/my_properties',
    ['pages/properties/my_properties.css'],
    ['pages/properties/my_properties.js', 'features/property-images.js'],
    ['auth' => true]
  ),

  'property_bookings' => $R('รายการจองพื้นที่', 'property_bookings', ['pages/property_bookings.css'], [], ['auth' => true]),

  'add_property' => $R(
    'เพิ่มพื้นที่ปล่อยเช่า',
    'properties/add_property',
    ['pages/properties/add_property.css'],
    ['pages/properties/add_property.js'],
    ['auth' => true]
  ),

  'edit_property' => $R(
    'แก้ไขพื้นที่ปล่อยเช่า',
    'properties/edit_property',
    ['pages/properties/add_property.css'],
    ['pages/properties/edit_property.js'],
    ['auth' => true]
  ),

  'delete_property' => $R(
    'ลบพื้นที่',
    'properties/delete_property',
    [],
    ['pages/properties/delete_property.js'],
    ['auth' => true]
  ),

  'delete_property_image' => $R(
    'ลบรูปภาพ',
    'properties/delete_property_image',
    [],
    ['features/property-images.js'],
    ['auth' => true]
  ),

  'admin_dashboard' => $R(
    'แดชบอร์ดผู้ดูแลระบบ',
    'admin_dashboard',
    ['pages/admin_dashboard.css'],
    ['pages/admin_dashboard.js'],
    ['auth' => true, 'admin' => true]
  ),
];
