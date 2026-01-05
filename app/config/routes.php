<?php

declare(strict_types=1);

return [
  'home' => [
    'title' => 'พื้นที่การเกษตรให้เช่า',
    'view'  => 'home',
    'css'   => ['pages/home.css'],
    'js'    => ['pages/home.js'],
  ],
  'signin' => [
    'title'      => 'เข้าสู่ระบบ',
    'view'       => 'signin',
    'css'        => ['pages/signin.css'],
    'js'         => ['pages/signin.js'],
    'guest_only' => true,
  ],
  'signup' => [
    'title'      => 'สมัครสมาชิก',
    'view'       => 'signup',
    'css'        => ['pages/signup.css'],
    'js'         => ['pages/signup.js'],
    'guest_only' => true,
  ],
  'profile' => [
    'title' => 'โปรไฟล์สมาชิกเกษตร',
    'view'  => 'profile',
    'css'   => ['pages/profile.css'],
    'js'    => ['pages/profile.js'],
    'auth'  => true,
  ],
  'detail' => [
    'title' => 'รายละเอียดแปลงเกษตร',
    'view'  => 'detail',
    'css'   => ['pages/detail.css'],
    'js'    => ['pages/detail.js'],
  ],
  'payment' => [
    'title' => 'ชำระมัดจำเช่าพื้นที่เกษตร',
    'view'  => 'payment',
    'css'   => ['pages/payment.css'],
    'js'    => ['pages/payment.js'],
    'auth'  => true,
  ],
  'contract' => [
    'title' => 'ทำสัญญาเช่า 1 ปี',
    'view'  => 'contract',
    'css'   => ['pages/contract.css'],
    'auth'  => true,
  ],
  'history' => [
    'title' => 'ประวัติการเช่าพื้นที่เกษตร',
    'view'  => 'history',
    'css'   => ['pages/history.css'],
    'js'    => ['pages/history.js'],
    'auth'  => true,
  ],
  'my_properties' => [
    'title' => 'พื้นที่ปล่อยเช่าของฉัน',
    'view'  => 'properties/my_properties',
    'css'   => ['pages/properties/my_properties.css'],
    'js'    => ['pages/properties/my_properties.js', 'features/property-images.js'],
    'auth'  => true,
  ],
  'property_bookings' => [
    'title' => 'รายการจองพื้นที่',
    'view'  => 'property_bookings',
    'css'   => ['pages/property_bookings.css'],
    'auth'  => true,
  ],
  'add_property' => [
    'title' => 'เพิ่มพื้นที่ปล่อยเช่า',
    'view'  => 'properties/add_property',
    'css'   => ['pages/properties/add_property.css'],
    'js'    => ['pages/properties/add_property.js'],
    'auth'  => true,
  ],
  'edit_property' => [
    'title' => 'แก้ไขพื้นที่ปล่อยเช่า',
    'view'  => 'properties/edit_property',
    'css'   => ['pages/properties/add_property.css'],
    'js'    => ['pages/properties/edit_property.js'],
    'auth'  => true,
  ],
  'delete_property' => [
    'title' => 'ลบพื้นที่',
    'view'  => 'properties/delete_property',
    'js'    => ['pages/properties/delete_property.js'],
    'auth'  => true,
  ],
  'delete_property_image' => [
    'title' => 'ลบรูปภาพ',
    'view'  => 'properties/delete_property_image',
    'js'    => ['features/property-images.js'],
    'auth'  => true,
  ],
  'admin_dashboard' => [
    'title' => 'แดชบอร์ดผู้ดูแลระบบ',
    'view'  => 'admin_dashboard',
    'css'   => ['pages/admin_dashboard.css'],
    'js'    => ['pages/admin_dashboard.js'],
    'auth'  => true,
    'admin' => true,
  ],
];
