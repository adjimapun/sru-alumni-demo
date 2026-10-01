<?php
require __DIR__.'/config.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$activeTab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $activeTab = $action === 'register' ? 'register' : 'login';

    $citizen = preg_replace('/\D/', '', $_POST['citizen_id'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $confirmPhone = preg_replace('/\D/', '', $_POST['confirm_phone'] ?? '');

    try {
        if ($action === 'register') {
            if (!preg_match('/^\d{13}$/', $citizen)) {
                throw new RuntimeException('กรุณากรอกหมายเลขบัตรประชาชนให้ครบ 13 หลัก');
            }
            if (!preg_match('/^0\d{9}$/', $phone)) {
                throw new RuntimeException('กรุณากรอกหมายเลขโทรศัพท์มือถือ 10 หลัก');
            }
            if ($phone !== $confirmPhone) {
                throw new RuntimeException('หมายเลขโทรศัพท์มือถือทั้งสองช่องไม่ตรงกัน');
            }
            if (empty($_POST['privacy'])) {
                throw new RuntimeException('กรุณายอมรับเงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว');
            }

            $st = db()->prepare(
                'INSERT INTO users(citizen_hash,citizen_last4,phone,password_hash) VALUES(?,?,?,?)'
            );
            $st->execute([
                citizen_hash($citizen),
                substr($citizen, -4),
                $phone,
                password_hash($phone, PASSWORD_DEFAULT)
            ]);

            $success = 'สมัครบัญชีสำเร็จ สามารถเข้าสู่ระบบได้ทันที';
            $activeTab = 'login';
        } elseif ($action === 'login') {
            $st = db()->prepare('SELECT * FROM users WHERE citizen_hash=? LIMIT 1');
            $st->execute([citizen_hash($citizen)]);
            $u = $st->fetch();

            if (!$u || !password_verify($phone, $u['password_hash'])) {
                throw new RuntimeException('Username หรือ Password ไม่ถูกต้อง');
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $u['id'];

            header('Location: dashboard.php');
            exit;
        }
    } catch (PDOException $e) {
        $error = 'เลขบัตรประชาชนหรือเบอร์โทรศัพท์นี้มีบัญชีแล้ว';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SRU Alumni Membership</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');

:root{
  --p:#075b8f;
  --p2:#0a8fa9;
  --bg:#f4f8fb;
  --ink:#173149;
  --mut:#6d7f90;
  --line:#dce8ef;
  --ok:#138a5b;
  --warn:#e4a11b;
  --danger:#b42318;
}

*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  font-family:'Kanit',sans-serif;
  background:var(--bg);
  color:var(--ink);
}

button,input,select,textarea{font-family:'Kanit',sans-serif}

header{
  background:linear-gradient(120deg,#064c78,#0786a6);
  color:#fff;
  padding:16px 5%;
  display:flex;
  align-items:center;
  gap:14px;
  position:sticky;
  top:0;
  z-index:20;
}

.crest{
  width:58px;
  height:58px;
  display:grid;
  place-items:center;
  flex:0 0 auto;
}
.crest img{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
}

header small{display:block;opacity:.84}
.demo{
  margin-left:auto;
  font-size:12px;
  background:#ffffff22;
  padding:7px 11px;
  border-radius:18px;
  white-space:nowrap;
}

.shell{
  display:grid;
  grid-template-columns:230px 1fr;
  max-width:1280px;
  margin:22px auto;
  gap:20px;
  padding:0 16px;
}

.side,.card{
  background:#fff;
  border-radius:18px;
  box-shadow:0 8px 28px #173b5512;
}

.side{
  padding:18px;
  height:max-content;
  position:sticky;
  top:105px;
}

.menu{
  display:block;
  text-decoration:none;
  padding:11px 12px;
  border-radius:10px;
  margin:5px 0;
  cursor:pointer;
  color:#536c80;
}

.menu:hover,.menu.on{
  background:#eaf6fb;
  color:var(--p);
  font-weight:600;
}

.content{min-width:0}

.hero{
  background:linear-gradient(125deg,#075b8f,#09a2b4);
  color:#fff;
  border-radius:18px;
  padding:25px;
  margin-bottom:18px;
}

.hero h2{margin:0 0 5px;font-weight:600}
.card{padding:25px;margin-bottom:18px}

.auth{max-width:560px;margin:0 auto}
.authHead{text-align:center;margin-bottom:22px}

.authHead .icon{
  width:70px;
  height:70px;
  border-radius:50%;
  margin:auto;
  background:#e8f6fb;
  color:var(--p);
  display:grid;
  place-items:center;
  font-size:30px;
  font-weight:700;
}

.authHead h2{margin-bottom:6px}
.note{font-size:12px;color:var(--mut)}

.authTabs{
  display:grid;
  grid-template-columns:1fr 1fr;
  background:#eef4f7;
  border-radius:12px;
  padding:4px;
  margin-bottom:20px;
}

.authTabs button{
  border:0;
  background:transparent;
  padding:11px;
  border-radius:9px;
  font-weight:600;
  color:#617789;
  cursor:pointer;
}

.authTabs button.on{
  background:#fff;
  color:var(--p);
  box-shadow:0 2px 8px #16324a12;
}

label{
  display:block;
  font-size:14px;
  font-weight:500;
}

input{
  width:100%;
  padding:11px;
  border:1px solid var(--line);
  border-radius:9px;
  margin-top:6px;
  background:#fff;
  outline:none;
}

input:focus{
  border-color:#79b8cc;
  box-shadow:0 0 0 3px #0a8fa914;
}

.field{margin-top:13px}

.check{
  display:flex;
  align-items:flex-start;
  gap:8px;
  margin:10px 0;
  font-weight:400;
}

.check input{
  width:auto;
  margin:4px 0 0;
}

.btn{
  border:0;
  border-radius:10px;
  padding:11px 18px;
  background:var(--p);
  color:#fff;
  font-weight:600;
  cursor:pointer;
  width:100%;
  margin-top:13px;
}

.btn:hover{filter:brightness(.96)}

.hint{
  background:#eef8ff;
  border:1px solid #d2eaf8;
  border-radius:10px;
  padding:12px;
  font-size:13px;
  margin:13px 0;
}

.alert{
  padding:13px 14px;
  border-radius:10px;
  margin-bottom:15px;
  font-size:14px;
}

.alert.ok{
  background:#eefaf4;
  border-left:5px solid var(--ok);
  color:#0f6846;
}

.alert.danger{
  background:#fff0ef;
  border-left:5px solid var(--danger);
  color:#8d1c14;
}

.hidden{display:none}

.auth-switch{
  text-align:center;
  margin:14px 0 0;
  font-size:13px;
}

.auth-switch a{
  color:var(--p);
  text-decoration:none;
  font-weight:500;
}

.side-title{font-weight:600}
.side .note{margin:6px 0 12px}

@media(max-width:900px){
  .shell{grid-template-columns:1fr}
  .side{display:none}
}

@media(max-width:650px){
  header{padding:13px 16px}
  header b{font-size:14px}
  header small{font-size:11px}
  .demo{display:none}
  .crest{width:52px;height:52px}
  .shell{padding:0 12px;margin:14px auto}
  .hero,.card{padding:20px}
}
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
  <p class="note">ยังไม่ได้เข้าสู่ระบบ</p>
  <a class="menu on" href="index.php">⌂ หน้าหลัก</a>
  <a class="menu" href="application.php">▤ ใบสมัครสมาชิก</a>
  <a class="menu" href="track.php">◷ ติดตามสถานะ</a>
  <a class="menu" href="admin.php">⚙ สำหรับเจ้าหน้าที่</a>
</aside>

<main class="content">

<div class="hero">
  <h2>ระบบรับสมัครสมาชิกสมาคมศิษย์เก่า</h2>
  <div>สมัครบัญชี • กรอกใบสมัครหน้าเดียว • แนบสลิปในใบสมัคร • ติดตามสถานะ</div>
</div>

<section class="card">
<div class="auth">

  <div class="authHead">
    <div class="icon">A</div>
    <h2>บัญชีผู้ใช้งาน SRU Alumni</h2>
    <p class="note">เข้าสู่ระบบเพื่อสมัครสมาชิก ตรวจสอบสถานะ และใช้บริการสมาชิกศิษย์เก่า</p>
  </div>

  <?php if ($error): ?>
    <div class="alert danger"><?=h($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert ok"><?=h($success)?></div>
  <?php endif; ?>

  <div class="authTabs">
    <button type="button" id="loginTab" class="<?=$activeTab==='login'?'on':''?>" onclick="tab('login')">เข้าสู่ระบบ</button>
    <button type="button" id="registerTab" class="<?=$activeTab==='register'?'on':''?>" onclick="tab('register')">สมัครบัญชี</button>
  </div>

  <form id="loginBox" class="<?=$activeTab==='login'?'':'hidden'?>" method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="action" value="login">

    <label>
      Username — หมายเลขบัตรประชาชน 13 หลัก
      <input
        id="loginCitizen"
        name="citizen_id"
        maxlength="13"
        inputmode="numeric"
        autocomplete="username"
        placeholder="เลขบัตรประชาชน 13 หลัก"
        value="<?=h($activeTab==='login' ? ($_POST['citizen_id'] ?? '') : '')?>"
        required
      >
    </label>

    <label class="field">
      Password — หมายเลขโทรศัพท์มือถือ
      <input
        id="loginPhone"
        name="phone"
        type="password"
        maxlength="10"
        inputmode="tel"
        autocomplete="current-password"
        placeholder="08XXXXXXXX"
        required
      >
    </label>

    <label class="check">
      <input type="checkbox" onchange="document.getElementById('loginPhone').type=this.checked?'text':'password'">
      <span>แสดงรหัสผ่าน</span>
    </label>

    <button class="btn" type="submit">เข้าสู่ระบบ</button>

    <p class="auth-switch">
      <a href="#" onclick="tab('register');return false;">ยังไม่มีบัญชี? สมัครบัญชีใช้งาน</a>
    </p>
  </form>

  <form id="registerBox" class="<?=$activeTab==='register'?'':'hidden'?>" method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="action" value="register">

    <label>
      หมายเลขบัตรประชาชน *
      <input
        id="regCitizen"
        name="citizen_id"
        maxlength="13"
        inputmode="numeric"
        placeholder="เลขบัตรประชาชน 13 หลัก"
        value="<?=h($activeTab==='register' ? ($_POST['citizen_id'] ?? '') : '')?>"
        required
      >
    </label>

    <label class="field">
      หมายเลขโทรศัพท์มือถือ *
      <input
        id="regPhone"
        name="phone"
        maxlength="10"
        inputmode="tel"
        placeholder="08XXXXXXXX"
        value="<?=h($activeTab==='register' ? ($_POST['phone'] ?? '') : '')?>"
        required
      >
    </label>

    <label class="field">
      ยืนยันหมายเลขโทรศัพท์มือถือ *
      <input
        id="regPhone2"
        name="confirm_phone"
        maxlength="10"
        inputmode="tel"
        placeholder="08XXXXXXXX"
        required
      >
    </label>

    <div class="hint">
      <b>ข้อมูลเข้าสู่ระบบ</b><br>
      Username = หมายเลขบัตรประชาชน<br>
      Password เริ่มต้น = หมายเลขโทรศัพท์มือถือ
    </div>

    <label class="check">
      <input type="checkbox" name="privacy" value="1" required>
      <span>ยอมรับเงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว</span>
    </label>

    <button class="btn" type="submit">สมัครบัญชี</button>

    <p class="auth-switch">
      <a href="#" onclick="tab('login');return false;">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
    </p>
  </form>

</div>
</section>

</main>
</div>

<script>
function tab(name){
  const loginBox = document.getElementById('loginBox');
  const registerBox = document.getElementById('registerBox');
  const loginTab = document.getElementById('loginTab');
  const registerTab = document.getElementById('registerTab');

  loginBox.classList.toggle('hidden', name !== 'login');
  registerBox.classList.toggle('hidden', name !== 'register');
  loginTab.classList.toggle('on', name === 'login');
  registerTab.classList.toggle('on', name === 'register');
}
</script>

</body>
</html>
