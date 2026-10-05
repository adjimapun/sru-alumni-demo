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
$schemaReady = true;

try {
    $admins = $pdo->query(
        'SELECT id,full_name,username,is_active,created_at,updated_at
         FROM admins
         ORDER BY is_active DESC,id ASC'
    )->fetchAll();
} catch (PDOException $e) {
    $admins = [];
    $schemaReady = false;
    $error = 'ยังไม่พบโครงสร้างบัญชีผู้ดูแลระบบเวอร์ชันใหม่ กรุณารัน sql/migrate_v4.sql ก่อนใช้งานเมนูนี้';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaReady) {
    verify_csrf();

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($fullName === '') {
                throw new RuntimeException('กรุณาระบุชื่อเจ้าหน้าที่');
            }
            if (strlen($username) < 3) {
                throw new RuntimeException('Username ต้องมีอย่างน้อย 3 ตัวอักษร');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('Password ต้องมีอย่างน้อย 8 ตัวอักษร');
            }

            $st = $pdo->prepare(
                'INSERT INTO admins(full_name,username,password_hash,is_active)
                 VALUES(?,?,?,1)'
            );
            $st->execute([
                $fullName,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
            ]);

            $success = 'เพิ่มผู้ดูแลระบบหลังบ้านเรียบร้อยแล้ว';

        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('ข้อมูลผู้ดูแลระบบไม่ถูกต้อง');
            }
            if ($id === (int)$admin['id']) {
                throw new RuntimeException('ไม่สามารถปิดใช้งานบัญชีที่กำลังเข้าสู่ระบบอยู่');
            }

            $st = $pdo->prepare('SELECT id,is_active FROM admins WHERE id=?');
            $st->execute([$id]);
            $target = $st->fetch();

            if (!$target) {
                throw new RuntimeException('ไม่พบบัญชีผู้ดูแลระบบ');
            }

            if ((int)$target['is_active'] === 1) {
                $activeCount = (int)$pdo->query(
                    'SELECT COUNT(*) FROM admins WHERE is_active=1'
                )->fetchColumn();

                if ($activeCount <= 1) {
                    throw new RuntimeException('ต้องมีผู้ดูแลระบบที่เปิดใช้งานอย่างน้อย 1 บัญชี');
                }
            }

            $pdo->prepare(
                'UPDATE admins
                 SET is_active=IF(is_active=1,0,1)
                 WHERE id=?'
            )->execute([$id]);

            $success = 'ปรับสถานะบัญชีผู้ดูแลระบบแล้ว';

        } elseif ($action === 'reset_password') {
            $id = (int)($_POST['id'] ?? 0);
            $password = $_POST['new_password'] ?? '';

            if ($id <= 0) {
                throw new RuntimeException('ข้อมูลผู้ดูแลระบบไม่ถูกต้อง');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('Password ใหม่ต้องมีอย่างน้อย 8 ตัวอักษร');
            }

            $st = $pdo->prepare(
                'UPDATE admins SET password_hash=? WHERE id=?'
            );
            $st->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $id,
            ]);

            if ($st->rowCount() === 0) {
                $check = $pdo->prepare('SELECT id FROM admins WHERE id=?');
                $check->execute([$id]);
                if (!$check->fetch()) {
                    throw new RuntimeException('ไม่พบบัญชีผู้ดูแลระบบ');
                }
            }

            $success = 'กำหนดรหัสผ่านใหม่เรียบร้อยแล้ว';

        } else {
            throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        }

        $admins = $pdo->query(
            'SELECT id,full_name,username,is_active,created_at,updated_at
             FROM admins
             ORDER BY is_active DESC,id ASC'
        )->fetchAll();

    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            $error = 'Username นี้ถูกใช้งานแล้ว';
        } else {
            $error = 'ไม่สามารถบันทึกข้อมูลผู้ดูแลระบบได้';
        }
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
<title>ผู้ดูแลระบบหลังบ้าน - SRU Alumni Association Admin</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.admin-list-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.password-inline{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.password-inline input{width:180px;margin:0}
</style>
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

<div class="card">
  <div class="actions" style="justify-content:space-between;align-items:center">
    <div>
      <h1 style="margin:0">ผู้ดูแลระบบหลังบ้าน</h1>
      <div class="note" style="margin-top:5px">ตั้งค่า → ผู้ดูแลระบบหลังบ้าน</div>
    </div>
    <a class="btn alt" href="admin_settings.php">← กลับเมนูตั้งค่า</a>
  </div>

  <?php if ($error): ?><div class="alert danger"><?=h($error)?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert ok"><?=h($success)?></div><?php endif; ?>

  <?php if ($schemaReady): ?>
  <h2>เพิ่มเจ้าหน้าที่ / ผู้ดูแลระบบ</h2>

  <form method="post">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="action" value="add">

    <div class="form-grid">
      <label>
        ชื่อ-นามสกุลเจ้าหน้าที่
        <input name="full_name" required maxlength="255" placeholder="ชื่อผู้ดูแลระบบ">
      </label>

      <label>
        Username
        <input name="username" required minlength="3" maxlength="100" autocomplete="off">
      </label>

      <label>
        Password
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </label>
    </div>

    <button class="btn" type="submit" style="margin-top:12px">เพิ่มผู้ดูแลระบบ</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($schemaReady): ?>
<div class="card">
  <h2 style="margin-top:0">บัญชีผู้ดูแลระบบทั้งหมด</h2>
  <div class="note" style="margin-bottom:14px">
    บัญชีของคุณคือ <?=h((string)($admin['full_name'] ?? $admin['username']))?> · ไม่สามารถปิดใช้งานบัญชีตัวเองได้
  </div>

  <table>
    <tr>
      <th>ลำดับ</th>
      <th>ชื่อเจ้าหน้าที่</th>
      <th>Username</th>
      <th>สถานะ</th>
      <th>สร้างเมื่อ</th>
      <th>จัดการ</th>
    </tr>

    <?php foreach ($admins as $index => $row): ?>
    <tr>
      <td><?=number_format($index+1)?></td>
      <td>
        <?=h((string)($row['full_name'] ?: '-'))?>
        <?php if ((int)$row['id'] === (int)$admin['id']): ?>
          <span class="badge ok">บัญชีของฉัน</span>
        <?php endif; ?>
      </td>
      <td><?=h($row['username'])?></td>
      <td>
        <?php if ((int)$row['is_active'] === 1): ?>
          <span class="badge ok">เปิดใช้งาน</span>
        <?php else: ?>
          <span class="badge">ปิดใช้งาน</span>
        <?php endif; ?>
      </td>
      <td><?=h((string)$row['created_at'])?></td>
      <td>
        <div class="admin-list-actions">

          <?php if ((int)$row['id'] !== (int)$admin['id']): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?=h((string)$row['id'])?>">
            <button class="btn small <?=((int)$row['is_active']===1)?'danger':'alt'?>" type="submit">
              <?=((int)$row['is_active']===1)?'ปิดใช้งาน':'เปิดใช้งาน'?>
            </button>
          </form>
          <?php endif; ?>

          <form method="post" class="password-inline" onsubmit="return confirm('ยืนยันการกำหนดรหัสผ่านใหม่หรือไม่?');">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?=h((string)$row['id'])?>">
            <input
              type="password"
              name="new_password"
              minlength="8"
              required
              placeholder="รหัสผ่านใหม่ ≥ 8 ตัว"
              autocomplete="new-password"
            >
            <button class="btn alt small" type="submit">เปลี่ยนรหัสผ่าน</button>
          </form>

        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

</main>
</body>
</html>
