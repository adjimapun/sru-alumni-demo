<?php
require __DIR__.'/config.php';

$pdo = db();
$admin = current_admin();

if (!$admin) {
    header('Location: admin.php');
    exit;
}

$error = '';
$success = '';
$settings = [
    'payee_name' => 'ผู้รับเงิน',
    'payee_position' => 'สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี',
    'signature_path' => null,
];

try {
    $st = $pdo->query('SELECT * FROM receipt_settings WHERE id=1 LIMIT 1');
    $row = $st->fetch();
    if ($row) {
        $settings = array_merge($settings, $row);
    }
} catch (PDOException $e) {
    $error = 'ยังไม่พบตารางตั้งค่าใบเสร็จ กรุณารัน sql/migrate_v4.sql ก่อนใช้งานเมนูนี้';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    verify_csrf();

    try {
        $payeeName = trim($_POST['payee_name'] ?? '');
        $payeePosition = trim($_POST['payee_position'] ?? '');

        if ($payeeName === '') {
            throw new RuntimeException('กรุณาระบุชื่อผู้รับเงิน');
        }

        $oldSignature = $settings['signature_path'] ?? null;
        $newSignature = $oldSignature;
        $uploadedPath = null;

        if (!empty($_POST['remove_signature'])) {
            $newSignature = null;
        }

        if (!empty($_FILES['signature']['name'])) {
            $uploadedPath = upload_image_file(
                'signature',
                'signature_',
                2 * 1024 * 1024
            );
            $newSignature = $uploadedPath;
        }

        $st = $pdo->prepare(
            'INSERT INTO receipt_settings(
                id,payee_name,payee_position,signature_path,updated_by
             ) VALUES(1,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                payee_name=VALUES(payee_name),
                payee_position=VALUES(payee_position),
                signature_path=VALUES(signature_path),
                updated_by=VALUES(updated_by)'
        );
        $st->execute([
            $payeeName,
            $payeePosition !== '' ? $payeePosition : null,
            $newSignature,
            (int)$admin['id'],
        ]);

        if ($oldSignature && $oldSignature !== $newSignature) {
            delete_managed_upload($oldSignature, 'signature_');
        }

        $settings = [
            'payee_name' => $payeeName,
            'payee_position' => $payeePosition,
            'signature_path' => $newSignature,
        ];

        $success = 'บันทึกข้อมูลผู้รับเงินและลายเซ็นเรียบร้อยแล้ว';

    } catch (Throwable $e) {
        if (!empty($uploadedPath)) {
            delete_managed_upload($uploadedPath, 'signature_');
        }
        $error = safe_error_message($e);
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตั้งค่าผู้รับเงิน - SRU Alumni Association Admin</title>
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
  <div class="actions" style="justify-content:space-between;align-items:center">
    <div>
      <h1 style="margin:0">ตั้งค่าผู้รับเงินในใบเสร็จ</h1>
      <div class="note" style="margin-top:5px">ตั้งค่า → ผู้รับเงิน / ลายเซ็นใบเสร็จ</div>
    </div>
    <a class="btn alt" href="admin_settings.php">← กลับเมนูตั้งค่า</a>
  </div>

  <?php if ($error): ?><div class="alert danger"><?=h($error)?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert ok"><?=h($success)?></div><?php endif; ?>

  <?php if ($error === '' || !str_contains($error, 'migrate_v4.sql')): ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

    <div class="form-grid">
      <label>
        ชื่อผู้รับเงิน
        <input
          name="payee_name"
          required
          maxlength="255"
          value="<?=h((string)$settings['payee_name'])?>"
          placeholder="เช่น นายสมชาย ใจดี"
        >
      </label>

      <label>
        ตำแหน่ง / ข้อความใต้ชื่อ
        <input
          name="payee_position"
          maxlength="255"
          value="<?=h((string)($settings['payee_position'] ?? ''))?>"
          placeholder="เช่น เหรัญญิก สมาคมศิษย์เก่า..."
        >
      </label>

      <div class="full">
        <label>
          อัปโหลดลายเซ็น
          <input type="file" name="signature" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
        </label>
        <div class="note">แนะนำ PNG พื้นหลังโปร่งใส หรือ JPG · ขนาดไม่เกิน 2 MB</div>

        <?php if (!empty($settings['signature_path'])): ?>
          <div style="margin-top:14px">
            <div class="note" style="margin-bottom:6px">ลายเซ็นปัจจุบัน</div>
            <img class="signature-preview" src="<?=h($settings['signature_path'])?>" alt="ลายเซ็นผู้รับเงิน">

            <label class="check" style="margin-top:10px">
              <input type="checkbox" name="remove_signature" value="1">
              นำลายเซ็นปัจจุบันออก
            </label>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="actions" style="margin-top:20px">
      <button class="btn" type="submit">บันทึกการตั้งค่า</button>
      <a class="btn alt" href="admin_receipt_settings.php">ยกเลิกการแก้ไข</a>
    </div>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin-top:0">ตัวอย่างส่วนผู้รับเงิน</h2>
  <div style="max-width:390px;text-align:center;padding:24px;border:1px solid #dce8ef;border-radius:14px">
    <?php if (!empty($settings['signature_path'])): ?>
      <img src="<?=h($settings['signature_path'])?>" alt="ลายเซ็นผู้รับเงิน" style="max-width:220px;max-height:85px;object-fit:contain">
    <?php else: ?>
      <div class="note" style="height:65px;display:grid;place-items:center">ยังไม่ได้อัปโหลดลายเซ็น</div>
    <?php endif; ?>

    <div style="border-top:1px solid #7fa9bb;padding-top:8px;margin-top:6px">
      <b><?=h((string)$settings['payee_name'])?></b><br>
      <?php if (!empty($settings['payee_position'])): ?>
        <?=h((string)$settings['payee_position'])?>
      <?php endif; ?>
    </div>
  </div>
</div>

</main>
</body>
</html>
