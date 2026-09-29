<?php
require __DIR__.'/config.php';$u=require_login();
$st=db()->prepare('SELECT * FROM applications WHERE user_id=?');$st->execute([$u['id']]);$app=$st->fetch();
$payment=$member=$receipt=null;
if($app){
 $st=db()->prepare('SELECT * FROM payments WHERE application_id=? ORDER BY id DESC LIMIT 1');$st->execute([$app['id']]);$payment=$st->fetch();
 $st=db()->prepare('SELECT * FROM members WHERE application_id=?');$st->execute([$app['id']]);$member=$st->fetch();
 if($member){$st=db()->prepare('SELECT * FROM receipts WHERE member_id=? ORDER BY id DESC LIMIT 1');$st->execute([$member['id']]);$receipt=$st->fetch();}
}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ติดตามสถานะ</title><link rel="stylesheet" href="assets/style.css"></head><body><header><div class="brand">SRU Alumni</div><nav><a href="dashboard.php">Dashboard</a></nav></header><main class="container"><div class="card"><h1>ติดตามสถานะ</h1>
<?php if(!$app):?><div class="alert">ยังไม่มีใบสมัคร</div><a class="btn" href="application.php">กรอกใบสมัคร</a>
<?php else:?><p>เลขที่ใบสมัคร <b><?=h($app['application_no'])?></b></p><div class="status"><b><?=h(app_status_th($app['status']))?></b></div>
<?php if($payment):?><div class="info"><h3>หลักฐานการชำระเงินล่าสุด</h3><p>จำนวน <?=number_format((float)$payment['amount'],2)?> บาท · สถานะ <?=h($payment['status'])?></p><?php if($payment['note']):?><p>หมายเหตุ: <?=h($payment['note'])?></p><?php endif;?></div><?php endif;?>
<?php if($member):?><div class="alert ok"><b>สมาชิกสมบูรณ์</b><br>เลขสมาชิก <?=h($member['member_no'])?></div><?php endif;?>
<div class="actions"><a class="btn alt" href="application.php">เปิดใบสมัคร / แนบสลิป</a><?php if($receipt):?><a class="btn" href="receipt.php">ดูใบเสร็จ</a><?php endif;?></div><?php endif;?></div></main></body></html>