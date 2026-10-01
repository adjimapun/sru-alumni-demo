<?php
require __DIR__.'/config.php';
$u = require_login();
$pdo = db();

$st = $pdo->prepare('SELECT * FROM applications WHERE user_id=? LIMIT 1');
$st->execute([$u['id']]);
$app = $st->fetch();

$member = null;
if ($app) {
    $st = $pdo->prepare('SELECT * FROM members WHERE application_id=? AND status="active" LIMIT 1');
    $st->execute([$app['id']]);
    $member = $st->fetch();
}

$last4 = preg_replace('/\D/', '', (string)($u['citizen_last4'] ?? ''));
$maskedCitizen = '•••••••••' . $last4;
$phone = preg_replace('/\D/', '', (string)($u['phone'] ?? ''));
$maskedPhone = $phone;
if (strlen($phone) === 10) {
    $maskedPhone = substr($phone, 0, 2) . 'X-XXX-' . substr($phone, -4);
}

$appStatus = $app ? app_status_th((string)$app['status']) : 'ยังไม่มีใบสมัคร';
$appNo = $app['application_no'] ?? '-';
$memberNo = $member['member_no'] ?? 'ยังไม่ได้รับเลขสมาชิก';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SRU Alumni Membership</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');
:root{--p:#075b8f;--p2:#0a8fa9;--bg:#f4f8fb;--ink:#173149;--mut:#6d7f90;--line:#dce8ef;--ok:#138a5b;--warn:#e4a11b}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:'Kanit',sans-serif;background:var(--bg);color:var(--ink)}
a{color:inherit}.shell{display:grid;grid-template-columns:230px 1fr;max-width:1280px;margin:22px auto;gap:20px;padding:0 16px}
header{background:linear-gradient(120deg,#064c78,#0786a6);color:#fff;padding:16px 5%;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:20}
.crest{width:58px;height:58px;display:grid;place-items:center;flex:0 0 auto}.crest img{width:58px;height:58px;object-fit:contain;display:block}
header small{display:block;opacity:.84}.demo{margin-left:auto;font-size:12px;background:#ffffff22;padding:7px 11px;border-radius:18px;white-space:nowrap}
.side,.card{background:#fff;border-radius:18px;box-shadow:0 8px 28px #173b5512}.side{padding:18px;height:max-content;position:sticky;top:105px}
.side-title{font-weight:600}.note{font-size:12px;color:var(--mut)}.side .note{margin:6px 0 12px}
.menu{display:block;text-decoration:none;padding:11px 12px;border-radius:10px;margin:5px 0;color:#536c80}.menu:hover,.menu.on{background:#eaf6fb;color:var(--p);font-weight:600}
.content{min-width:0}.hero{background:linear-gradient(125deg,#075b8f,#09a2b4);color:#fff;border-radius:18px;padding:25px;margin-bottom:18px}.hero h2{margin:0 0 5px;font-weight:600}
.card{padding:25px;margin-bottom:18px}.status{padding:15px;border-radius:10px;background:#fff8e5;border-left:5px solid var(--warn)}.status.ok{background:#eefaf4;border-color:var(--ok)}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0 0}.btn{display:inline-block;text-decoration:none;border:0;border-radius:10px;padding:11px 18px;background:var(--p);color:#fff;font-weight:600;cursor:pointer}.btn.alt{background:#eaf4f8;color:var(--p)}
.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px}.summary-item{padding:16px;border:1px solid #e4edf3;border-radius:14px;background:#f7fafc}.summary-item span{display:block;font-size:12px;color:var(--mut);margin-bottom:5px}.summary-item b{font-size:15px;color:var(--ink);word-break:break-word}
@media(max-width:900px){.shell{grid-template-columns:1fr}.side{position:static}.summary{grid-template-columns:1fr}}
@media(max-width:650px){header{padding:13px 16px}header b{font-size:14px}header small{font-size:11px}.demo{display:none}.crest{width:52px;height:52px}.shell{padding:0 12px;margin:14px auto}.hero,.card{padding:20px}}
</style>
</head>
<body>
<header>
  <div class="crest"><img src="assets/alumni-logo.png" alt="โลโก้สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี"></div>
  <div>
    <b>สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี</b>
    <small>Suratthani Rajabhat University Alumni Association</small>
  </div>
  <span class="demo">PHP + DATABASE</span>
</header>

<div class="shell">
<aside class="side">
  <div class="side-title">SRU Alumni Digital Service</div>
  <p class="note">เข้าสู่ระบบ: <?=h($maskedCitizen)?></p>
  <a class="menu on" href="dashboard.php">⌂ หน้าหลัก</a>
  <a class="menu" href="application.php">▤ ใบสมัครสมาชิก</a>
  <a class="menu" href="track.php">◷ ติดตามสถานะ</a>  <a class="menu" href="logout.php">↪ ออกจากระบบ</a>
</aside>

<main class="content">
  <div class="hero">
    <h2>ระบบรับสมัครสมาชิกสมาคมศิษย์เก่า</h2>
    <div>สมัครบัญชี • กรอกใบสมัครหน้าเดียว • แนบสลิปในใบสมัคร • ติดตามสถานะ</div>
  </div>

  <section class="card">
    <h2 style="margin-top:0">ยินดีต้อนรับเข้าสู่ระบบ</h2>
    <div class="status ok">
      <b>เข้าสู่ระบบสำเร็จ</b><br>
      Username: <?=h($maskedCitizen)?>
      <span class="note" style="display:block;margin-top:4px">หมายเลขโทรศัพท์: <?=h($maskedPhone)?></span>
    </div>

    <div class="summary">
      <div class="summary-item">
        <span>เลขที่ใบสมัคร</span>
        <b><?=h((string)$appNo)?></b>
      </div>
      <div class="summary-item">
        <span>สถานะสมาชิก</span>
        <b><?=h($appStatus)?></b>
      </div>
      <div class="summary-item">
        <span>เลขสมาชิก</span>
        <b><?=h((string)$memberNo)?></b>
      </div>
    </div>

    <div class="toolbar">
      <a class="btn" href="application.php"><?= $app ? 'เปิด / แก้ไขใบสมัครสมาชิก' : 'กรอกใบสมัครสมาชิก' ?></a>
      <a class="btn alt" href="track.php">ติดตามสถานะ</a>
      <?php if ($member): ?>
        <a class="btn alt" href="receipt.php">ใบเสร็จรับเงิน</a>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
</body>
</html>
