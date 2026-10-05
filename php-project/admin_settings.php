<?php
require __DIR__.'/config.php';

$admin = current_admin();
if (!$admin) {
    header('Location: admin.php');
    exit;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตั้งค่าระบบ - SRU Alumni Association Admin</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
  <div class="brand">SRU Alumni Association Admin</div>
  <nav>
    <a href="admin_dashboard.php">แดชบอร์ด</a>
    <a href="admin.php">ใบสมัคร</a>

    <span class="nav-settings">
      <a href="admin_settings.php">ตั้งค่า ▾</a>
      <span class="nav-submenu">
        <a href="admin_receipt_settings.php">ผู้รับเงิน / ลายเซ็นใบเสร็จ</a>
        <a href="admin_member_card_settings.php">บัตรสมาชิก / ลายเซ็นนายกสมาคม</a>
        <a href="admin_master.php">คณะ / ประเภทสมาชิก</a>
        <a href="admin_users.php">ผู้ดูแลระบบหลังบ้าน</a>
      </span>
    </span>

    <a href="index.php">หน้าหลักของระบบ</a>
    <a href="admin.php?logout=1&amp;token=<?=h(csrf_token())?>">ออกจากระบบ</a>
  </nav>
</header>

<main class="container">
  <div class="card">
    <h1 style="margin-top:0">ตั้งค่าระบบ</h1>
    <p class="note">รวมเมนูตั้งค่าที่ใช้ในการดูแลระบบหลังบ้านของสมาคมศิษย์เก่า</p>

    <div class="settings-grid" style="margin-top:20px">
      <a class="setting-card" href="admin_receipt_settings.php">
        <h3>ผู้รับเงิน / ลายเซ็นใบเสร็จ</h3>
        <p>แก้ไขชื่อผู้รับเงิน ตำแหน่ง และอัปโหลดลายเซ็นสำหรับใบเสร็จอิเล็กทรอนิกส์</p>
      </a>

      <a class="setting-card" href="admin_member_card_settings.php">
        <h3>บัตรสมาชิก / ลายเซ็นนายกสมาคม</h3>
        <p>กำหนดชื่อ ตำแหน่ง และลายเซ็นนายกสมาคม สำหรับแสดงบนบัตรสมาชิกดิจิทัล</p>
      </a>

      <a class="setting-card" href="admin_master.php">
        <h3>คณะ / ประเภทสมาชิก</h3>
        <p>จัดการข้อมูลคณะ วิทยาลัย และประเภทสมาชิกที่ใช้ในใบสมัคร</p>
      </a>

      <a class="setting-card" href="admin_users.php">
        <h3>ผู้ดูแลระบบหลังบ้าน</h3>
        <p>เพิ่มบัญชีเจ้าหน้าที่ เปิด/ปิดการใช้งาน และกำหนดรหัสผ่านใหม่</p>
      </a>
    </div>
  </div>
</main>

</body>
</html>
