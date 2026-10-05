<?php
require __DIR__.'/config.php';

$admin = current_admin();
if (!$admin) {
    header('Location: admin.php');
    exit;
}

$pdo = db();
$error = '';
$success = '';

$settings = member_card_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $newSignature = null;

    try {
        $presidentName = trim($_POST['president_name'] ?? '');
        $presidentPosition = trim($_POST['president_position'] ?? '');

        if ($presidentPosition === '') {
            $presidentPosition = 'นายกสมาคมศิษย์เก่า มรส.';
        }

        $oldSignature = $settings['president_signature_path'] ?? null;
        $signaturePath = $oldSignature;

        if (!empty($_POST['remove_signature'])) {
            $signaturePath = null;
        }

        if (!empty($_FILES['president_signature']['name'])) {
            $newSignature = upload_image_file(
                'president_signature',
                'president_signature_',
                2 * 1024 * 1024
            );
            $signaturePath = $newSignature;
        }

        $st = $pdo->prepare(
            'INSERT INTO member_card_settings(
                id,president_name,president_position,president_signature_path,updated_by
             ) VALUES(1,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                president_name=VALUES(president_name),
                president_position=VALUES(president_position),
                president_signature_path=VALUES(president_signature_path),
                updated_by=VALUES(updated_by)'
        );

        $st->execute([
            $presidentName !== '' ? $presidentName : null,
            $presidentPosition,
            $signaturePath,
            (int)$admin['id'],
        ]);

        if ($oldSignature && $oldSignature !== $signaturePath) {
            delete_managed_upload($oldSignature, 'president_signature_');
        }

        $settings = member_card_settings();
        $success = 'บันทึกข้อมูลนายกสมาคมและลายเซ็นสำหรับบัตรสมาชิกเรียบร้อยแล้ว';

    } catch (Throwable $e) {
        if ($newSignature) {
            delete_managed_upload($newSignature, 'president_signature_');
        }

        if ($e instanceof PDOException) {
            error_log('Member card settings error: '.$e->getMessage());
            $error = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาตรวจสอบฐานข้อมูลและ migrate_v5.sql';
        } else {
            $error = safe_error_message($e);
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตั้งค่าบัตรสมาชิก - SRU Alumni Association Admin</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.preview{
  max-width:760px;
  margin-top:20px;
  border:1px solid #dce8ef;
  border-radius:16px;
  padding:22px;
  background:linear-gradient(135deg,#f7fbfd,#fff);
}
.signature-preview{
  display:block;
  max-width:260px;
  max-height:95px;
  object-fit:contain;
  margin:8px auto;
  background:transparent;
}
.signature-box{
  text-align:center;
  padding:18px;
}
.signature-line{
  max-width:300px;
  margin:8px auto 0;
  border-top:1px solid #7997a7;
  padding-top:7px;
}
</style>
</head>
<body>

<header>
  <div class="brand">SRU Alumni Association Admin</div>
  <nav>
    <a href="admin_dashboard.php">แดชบอร์ด</a>
    <a href="admin.php">ใบสมัคร</a>
    <a href="admin_settings.php">ตั้งค่า</a>
    <a href="index.php">หน้าหลักของระบบ</a>
    <a href="admin.php?logout=1&amp;token=<?=h(csrf_token())?>">ออกจากระบบ</a>
  </nav>
</header>

<main class="container">
<div class="card">
  <h1 style="margin-top:0">ตั้งค่าบัตรสมาชิกดิจิทัล</h1>
  <p class="note">
    กำหนดชื่อ ตำแหน่ง และลายเซ็นของนายกสมาคมศิษย์เก่า มรส. สำหรับแสดงบนบัตรสมาชิก
  </p>

  <?php if ($error): ?>
    <div class="alert danger"><?=h($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert ok"><?=h($success)?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

    <div class="form-grid">
      <label>
        ชื่อ-นามสกุล นายกสมาคมศิษย์เก่า
        <input
          name="president_name"
          value="<?=h((string)($settings['president_name'] ?? ''))?>"
          placeholder="เช่น นาย..."
        >
      </label>

      <label>
        ตำแหน่ง
        <input
          name="president_position"
          value="<?=h((string)($settings['president_position'] ?? 'นายกสมาคมศิษย์เก่า มรส.'))?>"
        >
      </label>

      <label class="full">
        ลายเซ็นนายกสมาคม (PNG/JPG/WEBP ไม่เกิน 2 MB)
        <input
          type="file"
          name="president_signature"
          accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
        >
        <span class="note">แนะนำไฟล์ PNG พื้นหลังโปร่งใส เพื่อให้แสดงบนบัตรสมาชิกได้สวยที่สุด</span>
      </label>
    </div>

    <?php if (!empty($settings['president_signature_path'])): ?>
      <div style="margin:14px 0">
        <img
          class="signature-preview"
          src="<?=h($settings['president_signature_path'])?>"
          alt="ลายเซ็นนายกสมาคม"
        >
        <label class="check" style="max-width:360px;margin:auto">
          <input type="checkbox" name="remove_signature" value="1">
          <span>นำลายเซ็นปัจจุบันออก</span>
        </label>
      </div>
    <?php endif; ?>

    <button class="btn big" type="submit">บันทึกการตั้งค่าบัตรสมาชิก</button>
  </form>

  <div class="preview">
    <h3 style="margin-top:0">ตัวอย่างส่วนลายเซ็นบนบัตร</h3>
    <div class="signature-box">
      <?php if (!empty($settings['president_signature_path'])): ?>
        <img
          class="signature-preview"
          src="<?=h($settings['president_signature_path'])?>"
          alt="ลายเซ็นนายกสมาคม"
        >
      <?php else: ?>
        <div class="note" style="height:70px;display:grid;place-items:center">
          ยังไม่ได้อัปโหลดลายเซ็น
        </div>
      <?php endif; ?>

      <div class="signature-line">
        <?php if (!empty($settings['president_name'])): ?>
          <b>(<?=h($settings['president_name'])?>)</b><br>
        <?php endif; ?>
        <?=h((string)$settings['president_position'])?>
      </div>
    </div>
  </div>
</div>
</main>

</body>
</html>
