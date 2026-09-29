<?php
require __DIR__.'/config.php';$pdo=db();$error='';
if(isset($_GET['logout'])){unset($_SESSION['admin_id']);header('Location: admin.php');exit;}
if(!current_admin()){
 if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$st=$pdo->prepare('SELECT * FROM admins WHERE username=?');$st->execute([trim($_POST['username']??'')]);$a=$st->fetch();if($a&&password_verify($_POST['password']??'',$a['password_hash'])){$_SESSION['admin_id']=$a['id'];header('Location: admin.php');exit;}$error='เข้าสู่ระบบไม่สำเร็จ';}
 ?><!doctype html><html lang="th"><head><meta charset="utf-8"><title>Admin Login</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="auth-wrap"><div class="card"><h1>เจ้าหน้าที่ / ผู้ดูแลระบบ</h1><?php if($error):?><div class="alert danger"><?=h($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><label>Username<input name="username" required></label><label>Password<input name="password" type="password" required></label><button class="btn">เข้าสู่ระบบ</button></form></div></main></body></html><?php exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['payment_id'],$_POST['decision'])){
 verify_csrf();$paymentId=(int)$_POST['payment_id'];$decision=$_POST['decision'];$pdo->beginTransaction();
 try{
  $st=$pdo->prepare('SELECT p.*,a.user_id FROM payments p JOIN applications a ON a.id=p.application_id WHERE p.id=? FOR UPDATE');$st->execute([$paymentId]);$p=$st->fetch();if(!$p)throw new RuntimeException('ไม่พบรายการ');
  if($decision==='approve'){
   $pdo->prepare('UPDATE payments SET status="paid",reviewed_at=NOW(),reviewer_id=?,note=? WHERE id=?')->execute([$_SESSION['admin_id'],trim($_POST['note']??''),$paymentId]);
   $pdo->prepare('UPDATE applications SET status="approved" WHERE id=?')->execute([$p['application_id']]);
   $st=$pdo->prepare('SELECT * FROM members WHERE application_id=?');$st->execute([$p['application_id']]);$m=$st->fetch();
   if(!$m){$pdo->prepare('INSERT INTO members(application_id,user_id,member_no) VALUES(?,?,"PENDING")')->execute([$p['application_id'],$p['user_id']]);$mid=(int)$pdo->lastInsertId();$memberNo='ALUMNI-'.str_pad((string)$mid,6,'0',STR_PAD_LEFT);$pdo->prepare('UPDATE members SET member_no=? WHERE id=?')->execute([$memberNo,$mid]);}else{$mid=(int)$m['id'];}
   $st=$pdo->prepare('SELECT id FROM receipts WHERE payment_id=?');$st->execute([$paymentId]);
   if(!$st->fetch()){$pdo->prepare('INSERT INTO receipts(member_id,payment_id,receipt_no,receipt_date,amount) VALUES(?,?,"PENDING",CURDATE(),?)')->execute([$mid,$paymentId,$p['amount']]);$rid=(int)$pdo->lastInsertId();$no='RCPT-'.thai_year().'-'.str_pad((string)$rid,6,'0',STR_PAD_LEFT);$pdo->prepare('UPDATE receipts SET receipt_no=? WHERE id=?')->execute([$no,$rid]);}
  }else{
   $pdo->prepare('UPDATE payments SET status="invalid",reviewed_at=NOW(),reviewer_id=?,note=? WHERE id=?')->execute([$_SESSION['admin_id'],trim($_POST['note']??''),$paymentId]);
   $pdo->prepare('UPDATE applications SET status="payment_invalid" WHERE id=?')->execute([$p['application_id']]);
  }
  $pdo->commit();
 }catch(Throwable $e){$pdo->rollBack();$error=$e->getMessage();}
}
$rows=$pdo->query('SELECT a.*, (SELECT id FROM payments p WHERE p.application_id=a.id ORDER BY p.id DESC LIMIT 1) payment_id,(SELECT status FROM payments p WHERE p.application_id=a.id ORDER BY p.id DESC LIMIT 1) payment_status,(SELECT slip_path FROM payments p WHERE p.application_id=a.id ORDER BY p.id DESC LIMIT 1) slip_path FROM applications a ORDER BY a.id DESC')->fetchAll();
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin</title><link rel="stylesheet" href="assets/style.css"></head><body><header><div class="brand">SRU Alumni Admin</div><nav><a href="admin.php?logout=1">ออกจากระบบ</a></nav></header><main class="container"><div class="card"><h1>จัดการใบสมัคร</h1><?php if($error):?><div class="alert danger"><?=h($error)?></div><?php endif;?><table><tr><th>เลขใบสมัคร</th><th>ชื่อ</th><th>สถานะ</th><th>หลักฐาน</th><th>ดำเนินการ</th></tr><?php foreach($rows as $r):?><tr><td><?=h($r['application_no'])?></td><td><?=h($r['full_name'])?></td><td><?=h(app_status_th($r['status']))?></td><td><?php if($r['slip_path']):?><a href="<?=h($r['slip_path'])?>" target="_blank">เปิดสลิป</a><?php else:?>-<?php endif;?></td><td><?php if($r['payment_id']):?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="payment_id" value="<?=h((string)$r['payment_id'])?>"><input name="note" placeholder="หมายเหตุ"><button class="btn small" name="decision" value="approve">ยืนยัน</button><button class="btn danger small" name="decision" value="invalid">ไม่ถูกต้อง</button></form><?php endif;?></td></tr><?php endforeach;?></table></div></main></body></html>