<?php
require __DIR__.'/config.php';
if(current_user()){ header('Location: dashboard.php'); exit; }
$error=''; $success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $action=$_POST['action'] ?? '';
  $citizen=preg_replace('/\D/','',$_POST['citizen_id'] ?? '');
  $phone=preg_replace('/\D/','',$_POST['phone'] ?? '');
  try{
    if($action==='register'){
      if(!preg_match('/^\d{13}$/',$citizen)) throw new RuntimeException('กรุณากรอกเลขบัตรประชาชน 13 หลัก');
      if(!preg_match('/^0\d{9}$/',$phone)) throw new RuntimeException('กรุณากรอกเบอร์โทรศัพท์มือถือ 10 หลัก');
      if(empty($_POST['privacy'])) throw new RuntimeException('กรุณายอมรับนโยบายความเป็นส่วนตัว');
      $st=db()->prepare('INSERT INTO users(citizen_hash,citizen_last4,phone,password_hash) VALUES(?,?,?,?)');
      $st->execute([citizen_hash($citizen),substr($citizen,-4),$phone,password_hash($phone,PASSWORD_DEFAULT)]);
      $success='สมัครบัญชีสำเร็จ สามารถเข้าสู่ระบบได้ทันที';
    } elseif($action==='login'){
      $st=db()->prepare('SELECT * FROM users WHERE citizen_hash=? LIMIT 1'); $st->execute([citizen_hash($citizen)]);
      $u=$st->fetch();
      if(!$u || !password_verify($phone,$u['password_hash'])) throw new RuntimeException('Username หรือ Password ไม่ถูกต้อง');
      $_SESSION['user_id']=$u['id']; header('Location: dashboard.php'); exit;
    }
  }catch(PDOException $e){ $error='เลขบัตรประชาชนหรือเบอร์โทรศัพท์นี้มีบัญชีแล้ว'; }
   catch(Throwable $e){ $error=$e->getMessage(); }
}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SRU Alumni</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header><div class="brand">SRU Alumni</div><div>สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี</div></header>
<main class="auth-wrap"><div class="card"><h1>ระบบสมัครสมาชิกศิษย์เก่า</h1>
<?php if($error):?><div class="alert danger"><?=h($error)?></div><?php endif;?><?php if($success):?><div class="alert ok"><?=h($success)?></div><?php endif;?>
<div class="tabs"><button onclick="tab('login')">เข้าสู่ระบบ</button><button onclick="tab('register')">สมัครบัญชี</button></div>
<form id="login" method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="login">
<label>Username — หมายเลขบัตรประชาชน 13 หลัก<input name="citizen_id" maxlength="13" inputmode="numeric" required></label>
<label>Password — หมายเลขโทรศัพท์มือถือ<input name="phone" type="password" maxlength="10" inputmode="tel" required></label>
<button class="btn">เข้าสู่ระบบ</button></form>
<form id="register" class="hidden" method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="register">
<label>หมายเลขบัตรประชาชน<input name="citizen_id" maxlength="13" inputmode="numeric" required></label>
<label>หมายเลขโทรศัพท์มือถือ<input name="phone" maxlength="10" inputmode="tel" required></label>
<label class="check"><input type="checkbox" name="privacy" value="1"> ยอมรับเงื่อนไขและนโยบายความเป็นส่วนตัว</label>
<div class="note">Username = เลขบัตรประชาชน และ Password เริ่มต้น = เบอร์โทรศัพท์มือถือ โดยระบบจัดเก็บ Password แบบ Hash</div>
<button class="btn">สมัครบัญชี</button></form></div></main>
<script>function tab(x){document.getElementById('login').classList.toggle('hidden',x!=='login');document.getElementById('register').classList.toggle('hidden',x!=='register')}</script></body></html>