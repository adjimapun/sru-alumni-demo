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

$latestPayment = null;
if ($app) {
    $st = $pdo->prepare('SELECT * FROM payments WHERE application_id=? ORDER BY id DESC LIMIT 1');
    $st->execute([$app['id']]);
    $latestPayment = $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        // ข้อมูลผู้สมัคร — บังคับกรอกทุกช่อง
        $titlePrefix = trim($_POST['title_prefix'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $birthDate = trim($_POST['birth_date'] ?? '');
        $studentCode = trim($_POST['student_code'] ?? '');
        $entryYear = trim($_POST['entry_year'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $major = trim($_POST['major'] ?? '');
        $degree = trim($_POST['degree'] ?? '');
        $gradYear = trim($_POST['grad_year'] ?? '');

        // ข้อมูลการติดต่อ — บังคับกรอกทุกช่อง
        $address = trim($_POST['address'] ?? '');
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $lineId = trim($_POST['line_id'] ?? '');

        // ข้อมูลการทำงาน — บังคับกรอกทุกช่อง
        $workplace = trim($_POST['workplace'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');

        // ประเภทสมาชิก
        $memberTypeId = (int)($_POST['member_type_id'] ?? 0);
        $memberTypeOther = trim($_POST['member_type_other'] ?? '');

        // ข้อมูลการชำระเงิน — บังคับกรอกทุกช่อง
        $paidDate = trim($_POST['paid_date'] ?? '');
        $paidTime = trim($_POST['paid_time'] ?? '');
        $amountRaw = trim($_POST['amount'] ?? '');
        $amount = (float)$amountRaw;

        $required = [
            'คำนำหน้าชื่อ' => $titlePrefix,
            'ชื่อ-นามสกุล' => $fullName,
            'ชื่อเล่น' => $nickname,
            'เพศ' => $gender,
            'วัน/เดือน/ปีเกิด' => $birthDate,
            'รหัสนักศึกษา / รหัสประจำตัวเดิม' => $studentCode,
            'ปีที่เข้าศึกษา' => $entryYear,
            'สาขา' => $major,
            'วุฒิการศึกษา' => $degree,
            'ปีที่สำเร็จการศึกษา' => $gradYear,
            'ที่อยู่ปัจจุบัน' => $address,
            'โทรศัพท์มือถือ' => $phone,
            'E-mail' => $email,
            'LINE ID / ช่องทางติดต่ออื่น' => $lineId,
            'สถานที่ทำงาน / หน่วยงาน' => $workplace,
            'ตำแหน่ง' => $position,
            'อาชีพ / ประเภทธุรกิจ' => $occupation,
            'วันที่ชำระเงิน' => $paidDate,
            'เวลาชำระเงิน' => $paidTime,
            'จำนวนเงิน' => $amountRaw,
        ];

        foreach ($required as $label => $value) {
            if ($value === '') {
                throw new RuntimeException('กรุณากรอกข้อมูลให้ครบทุกช่อง: '.$label);
            }
        }

        if ($facultyId <= 0) {
            throw new RuntimeException('กรุณาเลือกคณะ');
        }
        if ($memberTypeId <= 0) {
            throw new RuntimeException('กรุณาเลือกประเภทสมาชิก');
        }
        if (!preg_match('/^0\d{9}$/', $phone)) {
            throw new RuntimeException('กรุณากรอกหมายเลขโทรศัพท์มือถือให้ถูกต้อง 10 หลัก');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('กรุณากรอก E-mail ให้ถูกต้อง');
        }
        if ($amount <= 0) {
            throw new RuntimeException('จำนวนเงินไม่ถูกต้อง');
        }
        if (empty($_POST['consent'])) {
            throw new RuntimeException('กรุณายินยอมให้ใช้ข้อมูล');
        }

        $st = $pdo->prepare('SELECT id,name FROM faculties WHERE id=? AND is_active=1');
        $st->execute([$facultyId]);
        if (!$st->fetch()) {
            throw new RuntimeException('ไม่พบข้อมูลคณะที่เลือก');
        }

        $st = $pdo->prepare('SELECT id,name,allow_other_text FROM member_types WHERE id=? AND is_active=1');
        $st->execute([$memberTypeId]);
        $selectedMemberType = $st->fetch();

        if (!$selectedMemberType) {
            throw new RuntimeException('ไม่พบประเภทสมาชิกที่เลือก');
        }

        if ((int)$selectedMemberType['allow_other_text'] === 1 && $memberTypeOther === '') {
            throw new RuntimeException('กรุณาระบุประเภทสมาชิกอื่น ๆ');
        }
        if ((int)$selectedMemberType['allow_other_text'] !== 1) {
            $memberTypeOther = '';
        }

        // ต้องมีหลักฐานการชำระเงินในการส่งครั้งแรก
        // หากหลักฐานเดิมถูกเจ้าหน้าที่ตีกลับ ต้องแนบหลักฐานใหม่ก่อนส่งอีกครั้ง
        $mustUploadSlip = !$latestPayment || ($latestPayment['status'] ?? '') === 'invalid';
        if ($mustUploadSlip && empty($_FILES['slip']['name'])) {
            throw new RuntimeException('กรุณาแนบหลักฐานการชำระเงินก่อนส่งใบสมัคร');
        }

        $vals = [
            'title_prefix' => $titlePrefix,
            'full_name' => $fullName,
            'nickname' => $nickname,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'student_code' => $studentCode,
            'entry_year' => $entryYear,
            'faculty_id' => $facultyId,
            'major' => $major,
            'degree' => $degree,
            'grad_year' => $gradYear,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'line_id' => $lineId,
            'workplace' => $workplace,
            'position' => $position,
            'occupation' => $occupation,
            'member_type_id' => $memberTypeId,
            'member_type_other' => $memberTypeOther,
        ];

        $pdo->beginTransaction();

        if ($app) {
            $sql = 'UPDATE applications SET
                title_prefix=:title_prefix,
                full_name=:full_name,
                nickname=:nickname,
                gender=:gender,
                birth_date=:birth_date,
                student_code=:student_code,
                entry_year=:entry_year,
                faculty_id=:faculty_id,
                major=:major,
                degree=:degree,
                grad_year=:grad_year,
                address=:address,
                phone=:phone,
                email=:email,
                line_id=:line_id,
                workplace=:workplace,
                position=:position,
                occupation=:occupation,
                member_type_id=:member_type_id,
                member_type_other=:member_type_other,
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
            // ช่องทางล็อกฝั่ง Server ไม่รับค่าจาก Browser
            $channel = 'โอนผ่านบัญชีธนาคาร';

            $pdo->prepare(
                'INSERT INTO payments(application_id,paid_date,paid_time,amount,channel,slip_path,status)
                 VALUES(?,?,?,?,?,?,"pending")'
            )->execute([$appId, $paidDate, $paidTime, $amount, $channel, $slip]);

            $pdo->prepare('UPDATE applications SET status="payment_review" WHERE id=?')
                ->execute([$appId]);
        } elseif ($latestPayment) {
            // มีหลักฐานเดิมอยู่แล้ว ไม่สร้างรายการซ้ำ
            if (($latestPayment['status'] ?? '') === 'pending') {
                $pdo->prepare('UPDATE applications SET status="payment_review" WHERE id=?')
                    ->execute([$appId]);
            }
        }

        $pdo->commit();

        header('Location: track.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = $e->getMessage();
    }

    $st = $pdo->prepare('SELECT * FROM applications WHERE user_id=? LIMIT 1');
    $st->execute([$u['id']]);
    $app = $st->fetch();

    $latestPayment = null;
    if ($app) {
        $st = $pdo->prepare('SELECT * FROM payments WHERE application_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([$app['id']]);
        $latestPayment = $st->fetch() ?: null;
    }
}

function v($app, string $key): string {
    return h($app[$key] ?? '');
}

function pv($payment, string $key): string {
    return h($payment[$key] ?? '');
}

$selectedFacultyId = (int)($app['faculty_id'] ?? 0);
$selectedMemberTypeId = (int)($app['member_type_id'] ?? 1);
$mustUploadSlip = !$latestPayment || ($latestPayment['status'] ?? '') === 'invalid';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ใบสมัครสมาชิกสมาคมศิษย์เก่า</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.required-note{background:#fff6e6;border-left:5px solid #e4a11b;padding:12px 14px;border-radius:10px;margin:12px 0}
.req{color:#b42318;font-weight:600}
</style>
</head>
<body>

<header>
  <div class="brand">SRU Alumni</div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="track.php">ติดตามสถานะ</a>
  </nav>
</header>

<main class="container">
<div class="card">

<h1>ใบสมัครสมาชิกสมาคมศิษย์เก่า</h1>

<div class="required-note">
  <b>กรุณากรอกข้อมูลให้ครบทุกช่องก่อนส่งใบสมัคร</b><br>
  <span class="note">ช่องที่มีเครื่องหมาย <span class="req">*</span> เป็นข้อมูลบังคับทั้งหมด รวมถึงข้อมูลการชำระเงินและหลักฐานการชำระเงิน</span>
</div>

<?php if ($err): ?>
  <div class="alert danger"><?=h($err)?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

<section>
<h2>1. ข้อมูลผู้สมัคร</h2>
<div class="form-grid">

<label>
คำนำหน้าชื่อ <span class="req">*</span>
<input name="title_prefix" list="prefix-list" value="<?=v($app,'title_prefix')?>" placeholder="เช่น นาย / นาง / นางสาว / ดร." required>
<datalist id="prefix-list">
  <option value="นาย">
  <option value="นาง">
  <option value="นางสาว">
  <option value="ดร.">
</datalist>
</label>

<label>
ชื่อ-นามสกุล <span class="req">*</span>
<input name="full_name" value="<?=v($app,'full_name')?>" required>
</label>

<label>
ชื่อเล่น <span class="req">*</span>
<input name="nickname" value="<?=v($app,'nickname')?>" required>
</label>

<label>
เพศ <span class="req">*</span>
<select name="gender" required>
<option value="">-- เลือกเพศ --</option>
<?php foreach (['ชาย','หญิง','ไม่ประสงค์ระบุ'] as $x): ?>
<option value="<?=h($x)?>" <?=$app && $app['gender']===$x ? 'selected' : ''?>><?=h($x)?></option>
<?php endforeach; ?>
</select>
</label>

<label>
วัน/เดือน/ปีเกิด <span class="req">*</span>
<input type="date" name="birth_date" value="<?=v($app,'birth_date')?>" required>
</label>

<label>
รหัสนักศึกษา / รหัสประจำตัวเดิม <span class="req">*</span>
<input name="student_code" value="<?=v($app,'student_code')?>" required>
</label>

<label>
ปีที่เข้าศึกษา <span class="req">*</span>
<input name="entry_year" value="<?=v($app,'entry_year')?>" required>
</label>

<label>
คณะ <span class="req">*</span>
<select name="faculty_id" required>
<option value="">-- เลือกคณะ --</option>
<?php foreach ($faculties as $faculty): ?>
<option value="<?=h((string)$faculty['id'])?>" <?=$selectedFacultyId === (int)$faculty['id'] ? 'selected' : ''?>>
  <?=h($faculty['name'])?>
</option>
<?php endforeach; ?>
</select>
</label>

<label>
สาขา <span class="req">*</span>
<input name="major" value="<?=v($app,'major')?>" placeholder="ระบุสาขาวิชา" required>
</label>

<label>
วุฒิการศึกษาที่สำเร็จ <span class="req">*</span>
<select name="degree" required>
<option value="">-- เลือกวุฒิการศึกษา --</option>
<?php foreach (['ปริญญาตรี','ปริญญาโท','ปริญญาเอก','อื่น ๆ'] as $x): ?>
<option value="<?=h($x)?>" <?=$app && $app['degree']===$x ? 'selected' : ''?>><?=h($x)?></option>
<?php endforeach; ?>
</select>
</label>

<label>
ปีที่สำเร็จการศึกษา <span class="req">*</span>
<input name="grad_year" value="<?=v($app,'grad_year')?>" required>
</label>

</div>
</section>

<section>
<h2>2. ข้อมูลการติดต่อ</h2>
<div class="form-grid">

<label class="full">
ที่อยู่ปัจจุบัน <span class="req">*</span>
<textarea name="address" required><?=v($app,'address')?></textarea>
</label>

<label>
โทรศัพท์มือถือ <span class="req">*</span>
<input
  name="phone"
  value="<?=v($app,'phone') ?: h($u['phone'])?>"
  maxlength="10"
  inputmode="tel"
  pattern="0[0-9]{9}"
  required
>
</label>

<label>
E-mail <span class="req">*</span>
<input type="email" name="email" value="<?=v($app,'email')?>" required>
</label>

<label>
LINE ID / ช่องทางติดต่ออื่น <span class="req">*</span>
<input name="line_id" value="<?=v($app,'line_id')?>" required>
</label>

</div>
</section>

<section>
<h2>3. ข้อมูลการทำงาน</h2>
<div class="form-grid">

<label class="full">
สถานที่ทำงาน / หน่วยงาน <span class="req">*</span>
<input name="workplace" value="<?=v($app,'workplace')?>" required>
</label>

<label>
ตำแหน่ง <span class="req">*</span>
<input name="position" value="<?=v($app,'position')?>" required>
</label>

<label>
อาชีพ / ประเภทธุรกิจ <span class="req">*</span>
<input name="occupation" value="<?=v($app,'occupation')?>" required>
</label>

</div>
</section>

<section>
<h2>4. ประเภทสมาชิก</h2>
<div class="form-grid">

<label>
ประเภทสมาชิก <span class="req">*</span>
<select name="member_type_id" id="memberType" onchange="toggleOtherMemberType()" required>
<option value="">-- เลือกประเภทสมาชิก --</option>
<?php foreach ($memberTypes as $mt): ?>
<option
  value="<?=h((string)$mt['id'])?>"
  data-other="<?=h((string)$mt['allow_other_text'])?>"
  <?=$selectedMemberTypeId === (int)$mt['id'] ? 'selected' : ''?>
><?=h($mt['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label id="memberTypeOtherWrap" class="hidden">
ระบุประเภทสมาชิกอื่น ๆ <span class="req">*</span>
<input
  id="memberTypeOther"
  name="member_type_other"
  value="<?=v($app,'member_type_other')?>"
  placeholder="โปรดระบุ"
>
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
วันที่ชำระเงิน <span class="req">*</span>
<input type="date" name="paid_date" value="<?=pv($latestPayment,'paid_date')?>" required>
</label>

<label>
เวลาชำระเงิน <span class="req">*</span>
<input type="time" name="paid_time" value="<?=pv($latestPayment,'paid_time')?>" required>
</label>

<label>
จำนวนเงิน <span class="req">*</span>
<input
  type="number"
  name="amount"
  value="<?=pv($latestPayment,'amount') ?: '100.00'?>"
  min="0.01"
  step="0.01"
  required
>
</label>

<label>
ช่องทาง <span class="req">*</span>
<input type="text" value="โอนผ่านบัญชีธนาคาร" readonly class="readonly-field" required>
<input type="hidden" name="channel" value="โอนผ่านบัญชีธนาคาร">
<span class="note">ระบบกำหนดช่องทางอัตโนมัติและไม่อนุญาตให้แก้ไข</span>
</label>

<label class="full upload">
หลักฐานการชำระเงิน (JPG/PNG/PDF ไม่เกิน 5 MB) <span class="req">*</span>
<input
  type="file"
  name="slip"
  accept=".jpg,.jpeg,.png,.pdf"
  <?=$mustUploadSlip ? 'required' : ''?>
>
<?php if ($latestPayment && !$mustUploadSlip): ?>
  <div class="note">ระบบมีหลักฐานการชำระเงินที่ส่งไว้แล้ว หากไม่ต้องการเปลี่ยนหลักฐาน ไม่จำเป็นต้องเลือกไฟล์ใหม่</div>
<?php else: ?>
  <div class="note">ต้องแนบหลักฐานการชำระเงินก่อนส่งใบสมัคร</div>
<?php endif; ?>
</label>

</div>
</section>

<section>
<h2>6. การยินยอมให้ใช้ข้อมูล</h2>
<div class="privacy">
<label class="check">
<input type="checkbox" name="consent" value="1" required>
<span>ยินยอมให้สมาคมเก็บรวบรวมและใช้ข้อมูลเพื่อบริหารสมาชิก การประชาสัมพันธ์ กิจกรรม และการติดต่อสื่อสารตามวัตถุประสงค์ของสมาคม <span class="req">*</span></span>
</label>
</div>
</section>

<button class="btn big" type="submit">ส่งใบสมัครสมาชิก</button>

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

    if (!show) {
        input.value = '';
    }
}
toggleOtherMemberType();
</script>

</body>
</html>
