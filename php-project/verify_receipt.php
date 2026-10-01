<?php
require __DIR__.'/config.php';

$code = strtolower(trim($_GET['code'] ?? ''));
$receipt = null;

if (preg_match('/^[a-f0-9]{32}$/', $code)) {
    $sql = 'SELECT
              r.receipt_no,
              r.receipt_date,
              r.amount,
              r.verification_code,
              r.status AS receipt_status,
              r.cancelled_at,
              m.member_no,
              m.status AS member_status,
              a.title_prefix,
              a.full_name
            FROM receipts r
            JOIN members m ON m.id=r.member_id
            JOIN applications a ON a.id=m.application_id
            WHERE r.verification_code=?
            LIMIT 1';

    $st = db()->prepare($sql);
    $st->execute([$code]);
    $receipt = $st->fetch();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตรวจสอบใบเสร็จ SRU Alumni</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<main class="auth-wrap">
<div class="card">
<h1>ตรวจสอบใบเสร็จอิเล็กทรอนิกส์</h1>

<?php if ($receipt && $receipt['receipt_status'] === 'active' && $receipt['member_status'] === 'active'): ?>

<div class="alert ok">
  <b>✓ ใบเสร็จถูกต้องและมีสถานะใช้งาน</b>
</div>

<p>เลขที่ใบเสร็จ: <b><?=h($receipt['receipt_no'])?></b></p>
<p>วันที่ออกใบเสร็จ: <?=h($receipt['receipt_date'])?></p>
<p>ผู้ชำระ: <?=h(trim($receipt['title_prefix'].' '.$receipt['full_name']))?></p>
<p>เลขสมาชิก: <b><?=h($receipt['member_no'])?></b></p>
<p>จำนวนเงิน: <b><?=number_format((float)$receipt['amount'],2)?> บาท</b></p>

<?php elseif ($receipt): ?>

<div class="alert danger">
  <b>✕ ใบเสร็จนี้ถูกยกเลิกแล้ว</b><br>
  ไม่สามารถใช้เป็นหลักฐานการชำระเงินที่มีผลอยู่ในปัจจุบัน
</div>

<p>เลขที่ใบเสร็จ: <b><?=h($receipt['receipt_no'])?></b></p>
<p>เลขสมาชิก: <?=h($receipt['member_no'])?></p>
<?php if (!empty($receipt['cancelled_at'])): ?>
<p>วันที่ยกเลิก: <?=h($receipt['cancelled_at'])?></p>
<?php endif; ?>

<?php else: ?>

<div class="alert danger">
  <b>ไม่พบใบเสร็จ หรือรหัสตรวจสอบไม่ถูกต้อง</b>
</div>

<?php endif; ?>

</div>
</main>

</body>
</html>
