<?php
require __DIR__.'/config.php';

$u = require_login();
$pdo = db();
$err = '';

$faculties = $pdo->query('SELECT id,name FROM faculties WHERE is_active=1 ORDER BY name')->fetchAll();
$memberTypes = $pdo->query('SELECT id,name,allow_other_text FROM member_types WHERE is_active=1 ORDER BY id')->fetchAll();

$st = $pdo->prepare('SELECT * FROM applications WHERE user_id=? LIMIT 1');
$st->execute([$u['id']]);
$app = $st->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $titlePrefix = trim($_POST['title_prefix'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $major = trim($_POST['major'] ?? '');
        $memberTypeId = (int)($_POST['member_type_id'] ?? 0);
        $memberTypeOther = trim($_POST['member_type_other'] ?? '');

        if ($titlePrefix === '') throw new RuntimeException('กรุณากรอกคำนำหน้าชื่อ');
        if ($fullName === '') throw new RuntimeException('กรุณากรอกชื่อ-นามสกุล');
        if ($facultyId <= 0) throw new RuntimeException('กรุณาเลือกคณะ');
        if ($major === '') throw new RuntimeException('กรุณากรอกสาขา');
        if ($memberTypeId <= 0) throw new RuntimeException('กรุณาเลือกประเภทสมาชิก');
        if (empty($_POST['consent'])) throw new RuntimeException('กรุณายินยอมให้ใช้ข้อมูล');

        $st = $pdo->prepare('SELECT id,name FROM faculties WHERE id=? AND is_active=1');
        $st->execute([$facultyId]);
        if (!$st->fetch()) throw new RuntimeException('ไม่พบข้อมูลคณะที่เลือก');

        $st = $pdo->prepare('SELECT id,name,allow_other_text FROM member_types WHERE id=? AND is_active=1');
        $st->execute([$memberTypeId]);
        $selectedMemberType = $st->fetch();
        if (!$selectedMemberType) throw new RuntimeException('ไม่พบประเภทสมาชิกที่เลือก');

        if ((int)$selectedMemberType['allow_other_text'] === 1 && $memberTypeOther === '') {
            throw new RuntimeException('กรุณาระบุประเภทสมาชิกอื่น ๆ');
        }
        if ((int)$selectedMemberType['allow_other_text'] !== 1) {
            $memberTypeOther = '';
        }

        $vals = [
            'title_prefix' => $titlePrefix,
            'full_name' => $fullName,
            'nickname' => trim($_POST['nickname'] ?? ''),
            'gender' => $_POST['gender'] ?? '',
            'birth_date' => ($_POST['birth_date'] ?? '') ?: null,
            'student_code' => trim($_POST['student_code'] ?? ''),
            'entry_year' => trim($_POST['entry_year'] ?? ''),
            'faculty_id' => $facultyId,
            'major' => $major,
            'degree' => $_POST['degree'] ?? '',
            'grad_year' => trim($_POST['grad_year'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'line_id' => trim($_POST['line_id'] ?? ''),
            'workplace' => trim($_POST['workplace'] ?? ''),
            'position' => trim($_POST['position'] ?? ''),
            'occupation' => trim($_POST['occupation'] ?? ''),
            'member_type_id' => $memberTypeId,
            'member_type_other' => $memberTypeOther,
        ];

        $pdo->beginTransaction();

        if ($app) {
            $sql = 'UPDATE applications SET
                title_prefix=:title_prefix, full_name=:full_name, nickname=:nickname, gender=:gender,
                birth_date=:birth_date, student_code=:student_code, entry_year=:entry_year,
                faculty_id=:faculty_id, major=:major, degree=:degree, grad_year=:grad_year,
                address=:address, phone=:phone, email=:email, line_id=:line_id,
                workplace=:workplace, position=:position, occupation=:occupation,
                member_type_id=:member_type_id, member_type_other=:member_type_other,
                updated_at=NOW()
                WHERE id=:id';
            $vals['id'] = $app['id'];
            $pdo->prepare($sql)->execute($vals);
            $appId = (int)$app['id'];
        } else {
            $sql = 'INSERT INTO applications(
                user_id,title_prefix,full_name,nickname,gender,birth_date,student_code,entry_year,
                faculty_id,major,degree,grad_year,address,phone,email,line_id,workplace,position,
                occupation,member_type_id,member_type_other,status
            ) VALUES(
                :user_id,:title_prefix,:full_name,:nickname,:gender,:birth_date,:student_code,:entry_year,
                :faculty_id,:major,:degree,:grad_year,:address,:phone,:email,:line_id,:workplace,:position,
                :occupation,:member_type_id,:member_type_other,"pending_payment"
            )';
            $vals['user_id'] = $u['id'];
            $pdo->prepare($sql)->execute($vals);
            $appId = (int)$pdo->lastInsertId();

            $applicationNo = 'APP-'.thai_year().'-'.str_pad((string)$appId, 5, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE applications SET application_no=? WHERE id=?')
                ->execute([$applicationNo, $appId]);
        }

        $slip = upload_file('slip', 'slip_');

        if ($slip) {
            $paidDate = ($_POST['paid_date'] ?? '') ?: date('Y-m-d');
            $paidTime = ($_POST['paid_time'] ?? '') ?: null;
            $amount = (float)($_POST['amount'] ?? 100);

            if ($amount <= 0) throw new RuntimeException('จำนวนเงินไม่ถูกต้อง');

            // ช่องทางการชำระเงินล็อกฝั่ง Server ไม่รับค่าจากผู้ใช้
            $channel = 'โอนผ่านบัญชีธนาคาร';

            $pdo->prepare(
                'INSERT INTO payments(application_id,paid_date,paid_time,amount,channel,slip_path,status)
                 VALUES(?,?,?,?,?,?,"pending")'
            )->execute([$appId, $paidDate, $paidTime, $amount, $channel, $slip]);

            $pdo->prepare('UPDATE applications SET status="payment_review" WHERE id=?')
                ->execute([$appId]);
        } else {
            $has = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE application_id=?');
            $has->execute([$appId]);
            if ((int)$has->fetchColumn() === 0) {
                $pdo->prepare('UPDATE applications SET status="pending_payment" WHERE id=?')
                    ->execute([$appId]);
            }
        }

        $pdo->commit();
        header('Location: track.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }

    $st = $pdo->prepare('SELECT * FROM applications WHERE user_id=? LIMIT 1');
    $st->execute([$u['id']]);
    $app = $st->fetch();
}

function v($app, string $key): string {
    return h($app[$key] ?? '');
}

$selectedFacultyId = (int)($app['faculty_id'] ?? 0);
$selectedMemberTypeId = (int)($app['member_type_id'] ?? 1);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ใบสมัครสมาชิกสมาคมศิษย์เก่า</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <div class="brand">SRU Alumni</div>
  <nav><a href="dashboard.php">Dashboard</a><a href="track.php">ติดตามสถานะ</a></nav>
</header>

<main class="container">
<div class="card">
<h1>ใบสมัครสมาชิกสมาคมศิษย์เก่า</h1>
<p class="note">กรอกข้อมูลและชำระเงิน/แนบสลิปในหน้าเดียวกัน หากยังไม่ชำระ สามารถส่งใบสมัครก่อนได้</p>

<?php if ($err): ?><div class="alert danger"><?=h($err)?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

<section>
<h2>1. ข้อมูลผู้สมัคร</h2>
<div class="form-grid">

<label>
คำนำหน้าชื่อ *
<input name="title_prefix" list="prefix-list" value="<?=v($app,'title_prefix')?>" placeholder="เช่น นาย / นาง / นางสาว / ดร." required>
<datalist id="prefix-list">
  <option value="นาย"><option value="นาง"><option value="นางสาว"><option value="ดร.">
</datalist>
</label>

<label>
ชื่อ-นามสกุล *
<input name="full_name" value="<?=v($app,'full_name')?>" required>
</label>

<label>
ชื่อเล่น
<input name="nickname" value="<?=v($app,'nickname')?>">
</label>

<label>
เพศ
<select name="gender">
<?php foreach (['ชาย','หญิง','ไม่ประสงค์ระบุ'] as $x): ?>
<option <?=$app && $app['gender']===$x ? 'selected' : ''?>><?=h($x)?></option>
<?php endforeach; ?>
</select>
</label>

<label>
วัน/เดือน/ปีเกิด
<input type="date" name="birth_date" value="<?=v($app,'birth_date')?>">
</label>

<label>
รหัสนักศึกษา / รหัสประจำตัวเดิม
<input name="student_code" value="<?=v($app,'student_code')?>">
</label>

<label>
ปีที่เข้าศึกษา
<input name="entry_year" value="<?=v($app,'entry_year')?>">
</label>

<label>
คณะ *
<select name="faculty_id" required>
<option value="">-- เลือกคณะ --</option>
<?php foreach ($faculties as $f): ?>
<option value="<?=h((string)$f['id'])?>" <?=$selectedFacultyId===(int)$f['id']?'selected':''?>><?=h($f['name'])?></option>
<?php endforeach; ?>
</select>
<?php if (!$faculties): ?><span class="note">ยังไม่มีข้อมูลคณะ กรุณาให้ผู้ดูแลระบบเพิ่มข้อมูลคณะก่อน</span><?php endif; ?>
</label>

<label>
สาขา *
<input name="major" value="<?=v($app,'major')?>" placeholder="ระบุสาขาวิชา" required>
</label>

<label>
วุฒิการศึกษาที่สำเร็จ
<select name="degree">
<?php foreach (['ปริญญาตรี','ปริญญาโท','ปริญญาเอก','อื่น ๆ'] as $x): ?>
<option <?=$app && $app['degree']===$x ? 'selected' : ''?>><?=h($x)?></option>
<?php endforeach; ?>
</select>
</label>

<label>
ปีที่สำเร็จการศึกษา
<input name="grad_year" value="<?=v($app,'grad_year')?>">
</label>

</div>
</section>

<section>
<h2>2. ข้อมูลการติดต่อ</h2>
<div class="form-grid">
<label class="full">
ที่อยู่ปัจจุบัน
<textarea name="address"><?=v($app,'address')?></textarea>
</label>
<label>
โทรศัพท์มือถือ
<input name="phone" value="<?=v($app,'phone') ?: h($u['phone'])?>">
</label>
<label>
E-mail
<input type="email" name="email" value="<?=v($app,'email')?>">
</label>
<label>
LINE ID / ช่องทางติดต่ออื่น
<input name="line_id" value="<?=v($app,'line_id')?>">
</label>
</div>
</section>

<section>
<h2>3. ข้อมูลการทำงาน</h2>
<div class="form-grid">
<label class="full">
สถานที่ทำงาน / หน่วยงาน
<input name="workplace" value="<?=v($app,'workplace')?>">
</label>
<label>
ตำแหน่ง
<input name="position" value="<?=v($app,'position')?>">
</label>
<label>
อาชีพ / ประเภทธุรกิจ
<input name="occupation" value="<?=v($app,'occupation')?>">
</label>
</div>
</section>

<section>
<h2>4. ประเภทสมาชิก</h2>
<div class="form-grid">
<label>
ประเภทสมาชิก *
<select name="member_type_id" id="memberType" onchange="toggleOtherMemberType()" required>
<option value="">-- เลือกประเภทสมาชิก --</option>
<?php foreach ($memberTypes as $mt): ?>
<option
  value="<?=h((string)$mt['id'])?>"
  data-other="<?=h((string)$mt['allow_other_text'])?>"
  <?=$selectedMemberTypeId===(int)$mt['id']?'selected':''?>
><?=h($mt['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label id="memberTypeOtherWrap" class="hidden">
ระบุประเภทสมาชิกอื่น ๆ *
<input id="memberTypeOther" name="member_type_other" value="<?=v($app,'member_type_other')?>" placeholder="โปรดระบุ">
</label>
</div>
</section>

<section>
<h2>5. การชำระค่าธรรมเนียม / แนบสลิป</h2>

<div class="pay">
<b>ค่าธรรมเนียมสมาชิก 100 บาท / คน</b><br>
ธนาคารกรุงไทย จำกัด (มหาชน) สาขาขุนทะเล<br>
ชื่อบัญชี: สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี<br>
<b>เลขที่บัญชี 664-3-78504-9</b>
</div>

<div class="form-grid">
<label>
วันที่ชำระเงิน
<input type="date" name="paid_date">
</label>

<label>
เวลา
<input type="time" name="paid_time">
</label>

<label>
จำนวนเงิน
<input type="number" name="amount" value="100" step="0.01">
</label>

<label>
ช่องทาง
<input type="text" value="โอนผ่านบัญชีธนาคาร" readonly class="readonly-field">
<input type="hidden" name="channel" value="โอนผ่านบัญชีธนาคาร">
<span class="note">ระบบกำหนดช่องทางนี้อัตโนมัติและไม่อนุญาตให้แก้ไข</span>
</label>

<label class="full upload">
แนบหลักฐานการชำระเงิน (JPG/PNG/PDF ไม่เกิน 5 MB)
<input type="file" name="slip" accept=".jpg,.jpeg,.png,.pdf">
</label>
</div>
</section>

<section>
<h2>6. การยินยอมให้ใช้ข้อมูล</h2>
<div class="privacy">
<label class="check">
<input type="checkbox" name="consent" value="1" required>
ยินยอมให้สมาคมเก็บรวบรวมและใช้ข้อมูลเพื่อบริหารสมาชิก การประชาสัมพันธ์ กิจกรรม และการติดต่อสื่อสารตามวัตถุประสงค์ของสมาคม
</label>
</div>
</section>

<button class="btn big">บันทึก / ส่งใบสมัคร</button>
</form>
</div>
</main>

<script>
function toggleOtherMemberType(){
    const select = document.getElementById('memberType');
    const option = select.options[select.selectedIndex];
    const show = option && option.dataset.other === '1';
    const wrap = document.getElementById('memberTypeOtherWrap');
    const input = document.getElementById('memberTypeOther');

    wrap.classList.toggle('hidden', !show);
    input.required = show;
    if (!show) input.value = '';
}
toggleOtherMemberType();
</script>
</body>
</html>
