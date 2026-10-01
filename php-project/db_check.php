<?php
declare(strict_types=1);
require __DIR__.'/config.php';

$status = db_connection_status();
http_response_code($status['ok'] ? 200 : 503);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตรวจสอบการเชื่อมต่อฐานข้อมูล</title>
<style>
body{
    margin:0;
    font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
    background:#f4f8fb;
    color:#173149;
}
.wrap{
    min-height:100vh;
    display:grid;
    place-items:center;
    padding:24px;
}
.card{
    width:min(560px,100%);
    background:#fff;
    border-radius:18px;
    padding:28px;
    box-shadow:0 12px 36px rgba(23,49,73,.10);
    text-align:center;
}
.status{
    font-size:22px;
    font-weight:700;
    margin:10px 0;
}
.ok{color:#138a5b}
.fail{color:#b42318}
.note{
    margin-top:14px;
    color:#6d7f90;
    font-size:14px;
    line-height:1.7;
}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="status <?=$status['ok'] ? 'ok' : 'fail'?>">
      <?=$status['ok'] ? '✓' : '✕'?> <?=h($status['message'])?>
    </div>

    <div class="note">
      <?=$status['ok']
        ? 'ระบบสามารถติดต่อ MySQL และทดสอบคำสั่ง SELECT 1 ได้สำเร็จ'
        : 'กรุณาตรวจสอบ Host, Database, Username, Password, Firewall และสิทธิ์ของผู้ใช้ฐานข้อมูล'?>
    </div>
  </div>
</div>
</body>
</html>
