<?php
require __DIR__.'/config.php'; $u=require_login();
$st=db()->prepare('SELECT * FROM applications WHERE user_id=?'); $st->execute([$u['id']]); $app=$st->fetch();
$member=null; if($app){$st=db()->prepare('SELECT * FROM members WHERE application_id=?');$st->execute([$app['id']]);$member=$st->fetch();}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header><div class="brand">SRU Alumni</div><nav><a href="dashboard.php">Dashboard</a><a href="logout.php">ออกจากระบบ</a></nav></header>
<main class="container"><div class="hero"><h1>ยินดีต้อนรับ</h1><p>บัญชี ****<?=h($u['citizen_last4'])?> · <?=h($u['phone'])?></p></div>
<div class="grid-cards">
<div class="card"><h3>ใบสมัครสมาชิก</h3><p><?= $app ? 'เลขที่ '.h($app['application_no']) : 'ยังไม่มีใบสมัคร' ?></p><a class="btn" href="application.php"><?= $app?'เปิด/แก้ไขใบสมัคร':'กรอกใบสมัคร' ?></a></div>
<div class="card"><h3>สถานะ</h3><p><?= $app?h(app_status_th($app['status'])):'-' ?></p><a class="btn alt" href="track.php">ติดตามสถานะ</a></div>
<div class="card"><h3>เลขสมาชิก</h3><p><?= $member?h($member['member_no']):'ยังไม่ได้รับเลขสมาชิก' ?></p><?php if($member):?><a class="btn alt" href="receipt.php">ใบเสร็จรับเงิน</a><?php endif;?></div>
<div class="card"><h3>ประวัติพัฒนาศักยภาพ</h3><p>บันทึกการอบรม/สัมมนา/กิจกรรม</p><a class="btn alt" href="training.php">เปิดประวัติ</a></div>
</div></main></body></html>