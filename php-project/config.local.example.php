<?php
/**
 * คัดลอกไฟล์นี้เป็น config.local.php บนเซิร์ฟเวอร์
 * แล้วใส่ค่าการเชื่อมต่อฐานข้อมูลจริง
 * ห้าม commit config.local.php ขึ้น GitHub
 */
return [
    'host' => '127.0.0.1',
    'name' => 'database_name',
    'user' => 'database_user',
    'pass' => 'database_password',
];
