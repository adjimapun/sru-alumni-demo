<?php
declare(strict_types=1);

$result = null;
$message = '';
$detail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string)($_POST['host'] ?? ''));
    $dbname = trim((string)($_POST['dbname'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($host === '' || $dbname === '' || $username === '') {
        $result = false;
        $message = 'กรุณากรอก Host, Database Name และ Username ให้ครบ';
    } else {
        try {
            $dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4';

            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $check = $pdo->query('SELECT 1 AS connection_test')->fetch();

            if ($check && (int)$check['connection_test'] === 1) {
                $result = true;
                $message = 'เชื่อมต่อฐานข้อมูลสำเร็จ';
                $detail = 'สามารถเชื่อมต่อ MySQL และทดสอบคำสั่ง SELECT 1 ได้สำเร็จ';
            } else {
                $result = false;
                $message = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ';
                $detail = 'เชื่อมต่อได้ แต่ผลการทดสอบ SELECT 1 ไม่ถูกต้อง';
            }
        } catch (Throwable $e) {
            $result = false;
            $message = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ';
            $detail = $e->getMessage();
        }
    }
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database Connection Check</title>
<style>
*{box-sizing:border-box}
body{
  margin:0;
  min-height:100vh;
  padding:24px;
  display:grid;
  place-items:center;
  font-family:system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  background:#f4f8fb;
  color:#173149;
}
.card{
  width:min(680px,100%);
  background:#fff;
  border-radius:20px;
  padding:28px;
  box-shadow:0 14px 40px rgba(23,49,73,.12);
}
h1{
  margin:0 0 6px;
  text-align:center;
  font-size:26px;
}
.sub{
  text-align:center;
  color:#6d7f90;
  font-size:14px;
  margin-bottom:24px;
}
label{
  display:block;
  margin-top:14px;
  font-weight:600;
  font-size:14px;
}
input{
  width:100%;
  margin-top:6px;
  padding:11px 12px;
  border:1px solid #dce8ef;
  border-radius:10px;
  font:inherit;
  outline:none;
}
input:focus{
  border-color:#0a8fa9;
  box-shadow:0 0 0 3px rgba(10,143,169,.12);
}
button{
  width:100%;
  margin-top:20px;
  padding:12px 16px;
  border:0;
  border-radius:10px;
  background:#075b8f;
  color:#fff;
  font:inherit;
  font-weight:700;
  cursor:pointer;
}
.result{
  margin-top:22px;
  padding:16px;
  border-radius:12px;
  font-weight:700;
}
.result.ok{
  background:#eefaf4;
  color:#0f6846;
  border:1px solid #ccebdc;
}
.result.fail{
  background:#fff0ef;
  color:#8d1c14;
  border:1px solid #f2c8c4;
}
.detail{
  display:block;
  margin-top:7px;
  font-weight:400;
  font-size:13px;
  line-height:1.6;
  word-break:break-word;
}
.note{
  margin-top:20px;
  padding:13px 15px;
  background:#f8fafb;
  border:1px solid #e5edf2;
  border-radius:10px;
  color:#6d7f90;
  font-size:13px;
  line-height:1.7;
}
</style>
</head>
<body>
<div class="card">
  <h1>ตรวจสอบการเชื่อมต่อฐานข้อมูล</h1>
  <div class="sub">Standalone PHP — ไม่บันทึกรหัสผ่านลงไฟล์หรือฐานข้อมูล</div>

  <form method="post" autocomplete="off">
    <label>
      Database Host
      <input name="host" value="<?=h((string)($_POST['host'] ?? ''))?>" placeholder="เช่น 172.18.9.34" required>
    </label>

    <label>
      Database Name
      <input name="dbname" value="<?=h((string)($_POST['dbname'] ?? ''))?>" placeholder="ชื่อฐานข้อมูล" required>
    </label>

    <label>
      Database Username
      <input name="username" value="<?=h((string)($_POST['username'] ?? ''))?>" placeholder="ชื่อผู้ใช้ฐานข้อมูล" required>
    </label>

    <label>
      Database Password
      <input type="password" name="password" placeholder="รหัสผ่านฐานข้อมูล">
    </label>

    <button type="submit">ทดสอบการเชื่อมต่อ</button>
  </form>

  <?php if ($result !== null): ?>
    <div class="result <?=$result ? 'ok' : 'fail'?>">
      <?=$result ? '✓' : '✕'?> <?=h($message)?>
      <?php if ($detail !== ''): ?>
        <span class="detail"><?=h($detail)?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="note">
    อัปโหลดไฟล์นี้ไปยัง Web Server แล้วเปิดผ่าน Browser จากนั้นกรอกค่าฐานข้อมูลและกด
    “ทดสอบการเชื่อมต่อ” หากสำเร็จจะแสดงข้อความสีเขียว
    หากไม่สำเร็จจะแสดงข้อความ Error สำหรับตรวจสอบ
    แนะนำให้ลบไฟล์ออกจาก Server หลังใช้งานเสร็จ
  </div>
</div>
</body>
</html>
