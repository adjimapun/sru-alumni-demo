<?php
require __DIR__.'/config.php'; $u=require_login(); $pdo=db(); $msg=''; $err='';
$st=$pdo->prepare('SELECT * FROM applications WHERE user_id=?');$st->execute([$u['id']]);$app=$st->fetch();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
  $fullname=trim($_POST['full_name']??''); if($fullname==='') throw new RuntimeException('กรุณากรอกชื่อ-นามสกุล');
  if(empty($_POST['consent'])) throw new RuntimeException('กรุณายินยอมให้ใช้ข้อมูล');
  $vals=[
   'full_name'=>$fullname,'nickname'=>trim($_POST['nickname']??''),'gender'=>$_POST['gender']??'',
   'birth_date'=>$_POST['birth_date']?:null,'student_code'=>trim($_POST['student_code']??''),
   'generation'=>trim($_POST['generation']??''),'entry_year'=>trim($_POST['entry_year']??''),
   'faculty_major'=>trim($_POST['faculty_major']??''),'degree'=>$_POST['degree']??'','grad_year'=>trim($_POST['grad_year']??''),
   'address'=>trim($_POST['address']??''),'phone'=>trim($_POST['phone']??''),'email'=>trim($_POST['email']??''),
   'line_id'=>trim($_POST['line_id']??''),'workplace'=>trim($_POST['workplace']??''),'position'=>trim($_POST['position']??''),
   'occupation'=>trim($_POST['occupation']??''),'member_type'=>$_POST['member_type']??'สมาชิกสามัญ'
  ];
  $pdo->beginTransaction();
  if($app){
   $sql='UPDATE applications SET full_name=:full_name,nickname=:nickname,gender=:gender,birth_date=:birth_date,student_code=:student_code,generation=:generation,entry_year=:entry_year,faculty_major=:faculty_major,degree=:degree,grad_year=:grad_year,address=:address,phone=:phone,email=:email,line_id=:line_id,workplace=:workplace,position=:position,occupation=:occupation,member_type=:member_type,updated_at=NOW() WHERE id=:id';
   $vals['id']=$app['id'];$pdo->prepare($sql)->execute($vals);$appId=(int)$app['id'];
  }else{
   $sql='INSERT INTO applications(user_id,full_name,nickname,gender,birth_date,student_code,generation,entry_year,faculty_major,degree,grad_year,address,phone,email,line_id,workplace,position,occupation,member_type,status) VALUES(:user_id,:full_name,:nickname,:gender,:birth_date,:student_code,:generation,:entry_year,:faculty_major,:degree,:grad_year,:address,:phone,:email,:line_id,:workplace,:position,:occupation,:member_type,"pending_payment")';
   $vals['user_id']=$u['id'];$pdo->prepare($sql)->execute($vals);$appId=(int)$pdo->lastInsertId();
   $no='APP-'.thai_year().'-'.str_pad((string)$appId,5,'0',STR_PAD_LEFT);
   $pdo->prepare('UPDATE applications SET application_no=? WHERE id=?')->execute([$no,$appId]);
  }
  $slip=upload_file('slip','slip_');
  if($slip){
   $pdo->prepare('INSERT INTO payments(application_id,paid_date,paid_time,amount,channel,slip_path,status) VALUES(?,?,?,?,?,?,"pending")')->execute([$appId,$_POST['paid_date']?:date('Y-m-d'),$_POST['paid_time']?:null,(float)($_POST['amount']??100),$_POST['channel']??'โอนผ่านบัญชีธนาคาร',$slip]);
   $pdo->prepare('UPDATE applications SET status="payment_review" WHERE id=?')->execute([$appId]);
  } else {
   $has=$pdo->prepare('SELECT COUNT(*) FROM payments WHERE application_id=?');$has->execute([$appId]);
   if((int)$has->fetchColumn()===0) $pdo->prepare('UPDATE applications SET status="pending_payment" WHERE id=?')->execute([$appId]);
  }
  $pdo->commit(); header('Location: track.php'); exit;
 }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $err=$e->getMessage(); }
 $st=$pdo->prepare('SELECT * FROM applications WHERE user_id=?');$st->execute([$u['id']]);$app=$st->fetch();
}
function v($app,$k){return h($app[$k]??'');}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ใบสมัครสมาชิก</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header><div class="brand">SRU Alumni</div><nav><a href="dashboard.php">Dashboard</a><a href="track.php">ติดตามสถานะ</a></nav></header>
<main class="container"><div class="card"><h1>ใบสมัครสมาชิกสมาคมศิษย์เก่า</h1><p class="note">กรอกข้อมูลและชำระเงิน/แนบสลิปในหน้าเดียวกัน หากยังไม่ชำระ สามารถส่งใบสมัครก่อนได้</p>
<?php if($err):?><div class="alert danger"><?=h($err)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<section><h2>1. ข้อมูลผู้สมัคร</h2><div class="form-grid">
<label>ชื่อ-นามสกุล *<input name="full_name" value="<?=v($app,'full_name')?>" required></label><label>ชื่อเล่น<input name="nickname" value="<?=v($app,'nickname')?>"></label>
<label>เพศ<select name="gender"><?php foreach(['ชาย','หญิง','ไม่ประสงค์ระบุ'] as $x):?><option <?=$app&&$app['gender']===$x?'selected':''?>><?=h($x)?></option><?php endforeach;?></select></label>
<label>วันเกิด<input type="date" name="birth_date" value="<?=v($app,'birth_date')?>"></label><label>รหัสนักศึกษา/รหัสเดิม<input name="student_code" value="<?=v($app,'student_code')?>"></label><label>รุ่น<input name="generation" value="<?=v($app,'generation')?>"></label>
<label>ปีที่เข้าศึกษา<input name="entry_year" value="<?=v($app,'entry_year')?>"></label><label>คณะ/สาขาวิชา<input name="faculty_major" value="<?=v($app,'faculty_major')?>"></label>
<label>วุฒิการศึกษา<select name="degree"><?php foreach(['ปริญญาตรี','ปริญญาโท','ปริญญาเอก','อื่น ๆ'] as $x):?><option <?=$app&&$app['degree']===$x?'selected':''?>><?=h($x)?></option><?php endforeach;?></select></label><label>ปีที่สำเร็จการศึกษา<input name="grad_year" value="<?=v($app,'grad_year')?>"></label></div></section>
<section><h2>2. ข้อมูลการติดต่อ</h2><div class="form-grid"><label class="full">ที่อยู่<textarea name="address"><?=v($app,'address')?></textarea></label><label>โทรศัพท์<input name="phone" value="<?=v($app,'phone')?:h($u['phone'])?>"></label><label>E-mail<input type="email" name="email" value="<?=v($app,'email')?>"></label><label>LINE ID<input name="line_id" value="<?=v($app,'line_id')?>"></label></div></section>
<section><h2>3. ข้อมูลการทำงาน</h2><div class="form-grid"><label class="full">สถานที่ทำงาน/หน่วยงาน<input name="workplace" value="<?=v($app,'workplace')?>"></label><label>ตำแหน่ง<input name="position" value="<?=v($app,'position')?>"></label><label>อาชีพ/ประเภทธุรกิจ<input name="occupation" value="<?=v($app,'occupation')?>"></label></div></section>
<section><h2>4. ประเภทสมาชิก</h2><label>ประเภทสมาชิก<select name="member_type"><?php foreach(['สมาชิกสามัญ','สมาชิกกิตติมศักดิ์','สมาชิกประเภทอื่น ๆ'] as $x):?><option <?=$app&&$app['member_type']===$x?'selected':''?>><?=h($x)?></option><?php endforeach;?></select></label></section>
<section><h2>5. การชำระค่าธรรมเนียม / แนบสลิป</h2><div class="pay"><b>ค่าธรรมเนียม 100 บาท/คน</b><br>ธนาคารกรุงไทย จำกัด (มหาชน) สาขาขุนทะเล<br>ชื่อบัญชี สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี<br><b>เลขที่บัญชี 664-3-78504-9</b></div><div class="form-grid"><label>วันที่ชำระ<input type="date" name="paid_date"></label><label>เวลา<input type="time" name="paid_time"></label><label>จำนวนเงิน<input type="number" name="amount" value="100" step="0.01"></label><label>ช่องทาง<select name="channel"><option>โอนผ่านบัญชีธนาคาร</option><option>QR Code</option><option>อื่น ๆ</option></select></label><label class="full upload">แนบสลิป (JPG/PNG/PDF ไม่เกิน 5 MB)<input type="file" name="slip" accept=".jpg,.jpeg,.png,.pdf"></label></div></section>
<section><h2>6. การยินยอม</h2><div class="privacy"><label class="check"><input type="checkbox" name="consent" value="1" required> ยินยอมให้สมาคมเก็บรวบรวมและใช้ข้อมูลเพื่อบริหารสมาชิก ประชาสัมพันธ์ กิจกรรม และการติดต่อสื่อสารตามวัตถุประสงค์ของสมาคม</label></div></section>
<button class="btn big">บันทึก / ส่งใบสมัคร</button></form></div></main></body></html>