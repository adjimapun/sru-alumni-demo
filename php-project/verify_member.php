<?php
require __DIR__.'/config.php';
header('X-Robots-Tag: noindex, nofollow, noarchive');

$code = strtolower(trim((string)($_GET['code'] ?? '')));
$member = null;

if (preg_match('/^[a-f0-9]{32}$/', $code)) {
    $st = db()->prepare(
        'SELECT
            m.member_no,
            m.status,
            m.approved_at,
            a.title_prefix,
            a.full_name,
            a.grad_year,
            f.name AS faculty_name,
            mt.name AS member_type_name
         FROM members m
         JOIN applications a ON a.id=m.application_id
         LEFT JOIN faculties f ON f.id=a.faculty_id
         LEFT JOIN member_types mt ON mt.id=a.member_type_id
         WHERE m.verification_code=?
         LIMIT 1'
    );
    $st->execute([$code]);
    $member = $st->fetch() ?: null;
}

$isActive = $member && ($member['status'] ?? '') === 'active';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตรวจสอบสมาชิก - SRU Alumni Association</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');
*{box-sizing:border-box}
body{
  margin:0;
  min-height:100vh;
  display:grid;
  place-items:center;
  padding:24px;
  font-family:'Kanit',sans-serif;
  background:linear-gradient(135deg,#eef5f8,#f9fcfd);
  color:#173149;
}
.card{
  width:min(620px,100%);
  background:#fff;
  border-radius:22px;
  padding:28px;
  box-shadow:0 18px 50px rgba(23,49,73,.13);
  text-align:center;
}
.logo{width:100px;height:112px;object-fit:contain;margin:auto}
.status{
  margin:16px auto;
  padding:12px 16px;
  border-radius:999px;
  width:max-content;
  max-width:100%;
  font-weight:700;
}
.ok{background:#e8f7ef;color:#0c7047}
.fail{background:#fff0ef;color:#a02820}
.no{
  font-size:27px;
  color:#0a527e;
  font-weight:700;
  letter-spacing:1px;
}
.info{
  margin-top:18px;
  border-top:1px solid #e1ebf0;
  padding-top:16px;
  display:grid;
  gap:8px;
  text-align:left;
}
.row{
  display:grid;
  grid-template-columns:145px 1fr;
  gap:10px;
}
.label{color:#6d7f90}
@media(max-width:520px){.row{grid-template-columns:1fr;gap:2px}}
</style>
</head>
<body>
<div class="card">
  <img class="logo" src="assets/alumni-logo.png" alt="โลโก้สมาคมศิษย์เก่า">

  <h1>ตรวจสอบสมาชิกสมาคมศิษย์เก่า</h1>

  <?php if (!$member): ?>
    <div class="status fail">ไม่พบข้อมูลสมาชิก</div>
  <?php elseif (!$isActive): ?>
    <div class="status fail">สมาชิกนี้ไม่ได้อยู่ในสถานะใช้งาน</div>
    <div class="no"><?=h($member['member_no'])?></div>
  <?php else: ?>
    <div class="status ok">✓ สมาชิกสมาคมศิษย์เก่า มรส. สถานะปกติ</div>
    <div class="no"><?=h($member['member_no'])?></div>

    <div class="info">
      <div class="row">
        <div class="label">ชื่อ-นามสกุล</div>
        <div><?=h(trim($member['title_prefix'].' '.$member['full_name']))?></div>
      </div>
      <div class="row">
        <div class="label">คณะ</div>
        <div><?=h((string)($member['faculty_name'] ?: '-'))?></div>
      </div>
      <div class="row">
        <div class="label">ปีที่สำเร็จการศึกษา</div>
        <div><?=h((string)($member['grad_year'] ?: '-'))?></div>
      </div>
      <div class="row">
        <div class="label">ประเภทสมาชิก</div>
        <div><?=h((string)($member['member_type_name'] ?: '-'))?></div>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
