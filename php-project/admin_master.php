<?php
require __DIR__.'/config.php';

$pdo = db();
$admin = current_admin();
if (!$admin) {
    header('Location: admin.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $entity = $_POST['entity'] ?? '';
        $action = $_POST['action'] ?? '';

        if ($entity === 'faculty') {
            if ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                if ($name === '') throw new RuntimeException('กรุณากรอกชื่อคณะ');
                $st = $pdo->prepare('INSERT INTO faculties(name,is_active) VALUES(?,1)');
                $st->execute([$name]);
                $success = 'เพิ่มข้อมูลคณะแล้ว';
            } elseif ($action === 'toggle') {
                $id = (int)($_POST['id'] ?? 0);
                $st = $pdo->prepare('UPDATE faculties SET is_active=IF(is_active=1,0,1) WHERE id=?');
                $st->execute([$id]);
                $success = 'ปรับสถานะคณะแล้ว';
            }
        } elseif ($entity === 'member_type') {
            if ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                if ($name === '') throw new RuntimeException('กรุณากรอกชื่อประเภทสมาชิก');
                $allowOther = empty($_POST['allow_other_text']) ? 0 : 1;
                $st = $pdo->prepare('INSERT INTO member_types(name,allow_other_text,is_active) VALUES(?,?,1)');
                $st->execute([$name, $allowOther]);
                $success = 'เพิ่มประเภทสมาชิกแล้ว';
            } elseif ($action === 'toggle') {
                $id = (int)($_POST['id'] ?? 0);
                $st = $pdo->prepare('UPDATE member_types SET is_active=IF(is_active=1,0,1) WHERE id=?');
                $st->execute([$id]);
                $success = 'ปรับสถานะประเภทสมาชิกแล้ว';
            }
        }
    } catch (PDOException $e) {
        $error = 'ข้อมูลซ้ำหรือไม่สามารถบันทึกได้';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$faculties = $pdo->query('SELECT * FROM faculties ORDER BY name')->fetchAll();
$memberTypes = $pdo->query('SELECT * FROM member_types ORDER BY id')->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>จัดการข้อมูลหลัก</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <div class="brand">SRU Alumni Association Admin</div>
  <nav>
    <a href="admin_dashboard.php">แดชบอร์ด</a>
    <a href="admin.php">ใบสมัคร</a>

    <span class="nav-settings">
      <a href="admin_settings.php">ตั้งค่า ▾</a>
      <span class="nav-submenu">
        <a href="admin_receipt_settings.php">ผู้รับเงิน / ลายเซ็นใบเสร็จ</a>
        <a href="admin_member_card_settings.php">บัตรสมาชิก / ลายเซ็นนายกสมาคม</a>
        <a href="admin_master.php">คณะ / ประเภทสมาชิก</a>
        <a href="admin_users.php">ผู้ดูแลระบบหลังบ้าน</a>
      </span>
    </span>

    <a href="index.php">หน้าหลักของระบบ</a>
    <a href="admin.php?logout=1&amp;token=<?=h(csrf_token())?>">ออกจากระบบ</a>
  </nav>
</header>

<main class="container">
<div class="actions" style="margin-bottom:14px">
  <a class="btn alt" href="admin_settings.php">← กลับเมนูตั้งค่า</a>
</div>
<div class="note" style="margin-bottom:10px">ตั้งค่า → คณะ / ประเภทสมาชิก</div>
<?php if ($error): ?><div class="alert danger"><?=h($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert ok"><?=h($success)?></div><?php endif; ?>

<div class="grid-cards">
<div class="card">
<h2>จัดการข้อมูลคณะ</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="entity" value="faculty">
<input type="hidden" name="action" value="add">
<label>ชื่อคณะ<input name="name" required placeholder="ระบุชื่อคณะ"></label>
<button class="btn">เพิ่มคณะ</button>
</form>

<table style="margin-top:18px">
<tr><th>ชื่อคณะ</th><th>สถานะ</th><th>จัดการ</th></tr>
<?php foreach ($faculties as $f): ?>
<tr>
<td><?=h($f['name'])?></td>
<td><?=((int)$f['is_active']===1)?'ใช้งาน':'ปิดใช้งาน'?></td>
<td>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="entity" value="faculty">
<input type="hidden" name="action" value="toggle">
<input type="hidden" name="id" value="<?=h((string)$f['id'])?>">
<button class="btn small <?=((int)$f['is_active']===1)?'alt':''?>"><?=((int)$f['is_active']===1)?'ปิดใช้งาน':'เปิดใช้งาน'?></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2>จัดการประเภทสมาชิก</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="entity" value="member_type">
<input type="hidden" name="action" value="add">
<label>ชื่อประเภทสมาชิก<input name="name" required placeholder="ระบุประเภทสมาชิก"></label>
<label class="check"><input type="checkbox" name="allow_other_text" value="1"> ให้ผู้สมัครระบุข้อความเพิ่มเติมได้</label>
<button class="btn">เพิ่มประเภทสมาชิก</button>
</form>

<table style="margin-top:18px">
<tr><th>ประเภทสมาชิก</th><th>ระบุอื่น ๆ</th><th>สถานะ</th><th>จัดการ</th></tr>
<?php foreach ($memberTypes as $m): ?>
<tr>
<td><?=h($m['name'])?></td>
<td><?=((int)$m['allow_other_text']===1)?'ได้':'-'?></td>
<td><?=((int)$m['is_active']===1)?'ใช้งาน':'ปิดใช้งาน'?></td>
<td>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="entity" value="member_type">
<input type="hidden" name="action" value="toggle">
<input type="hidden" name="id" value="<?=h((string)$m['id'])?>">
<button class="btn small <?=((int)$m['is_active']===1)?'alt':''?>"><?=((int)$m['is_active']===1)?'ปิดใช้งาน':'เปิดใช้งาน'?></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>
</main>
</body>
</html>
