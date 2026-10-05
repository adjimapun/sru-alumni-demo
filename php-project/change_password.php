<?php
require __DIR__.'/config.php';

$u = current_user();
if (!$u) {
    header('Location: index.php');
    exit;
}

$error = '';
$forced = isset($u['must_change_password']) && (int)$u['must_change_password'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $limit = auth_rate_limit_status('member_password_change', (string)$u['id']);
        if ($limit['blocked']) {
            $minutes = max(1, (int)ceil(((int)$limit['retry_after']) / 60));
            throw new RuntimeException('มีการยืนยันรหัสผ่านไม่สำเร็จหลายครั้ง กรุณารอประมาณ '.$minutes.' นาทีแล้วลองใหม่');
        }

        if (!password_verify($currentPassword, (string)$u['password_hash'])) {
            record_auth_attempt('member_password_change', (string)$u['id'], false);
            throw new RuntimeException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }

        if (!password_meets_policy($newPassword)) {
            throw new RuntimeException('รหัสผ่านใหม่ต้องมีอย่างน้อย 12 ตัวอักษร และมีทั้งตัวอักษรภาษาอังกฤษและตัวเลข');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน');
        }

        if (password_verify($newPassword, (string)$u['password_hash'])) {
            throw new RuntimeException('รหัสผ่านใหม่ต้องแตกต่างจากรหัสผ่านเดิม');
        }

        $st = db()->prepare(
            'UPDATE users
             SET password_hash=?, must_change_password=0
             WHERE id=?'
        );
        $st->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            (int)$u['id'],
        ]);

        record_auth_attempt('member_password_change', (string)$u['id'], true);
        session_regenerate_id(true);
        $_SESSION['_last_regeneration'] = time();

        header('Location: dashboard.php?password_changed=1');
        exit;

    } catch (Throwable $e) {
        $error = safe_error_message($e);
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เปลี่ยนรหัสผ่าน - SRU Alumni Association</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.security-note{
  background:#eef8ff;
  border-left:5px solid #075b8f;
  border-radius:10px;
  padding:14px;
  margin:12px 0 18px;
}
</style>
</head>
<body>
<main class="auth-wrap">
  <div class="card">
    <h1>เปลี่ยนรหัสผ่าน</h1>

    <?php if ($forced): ?>
      <div class="security-note">
        <b>เพื่อความปลอดภัย กรุณากำหนดรหัสผ่านใหม่ก่อนใช้งานระบบต่อ</b><br>
        <span class="note">บัญชีเดิมที่เคยใช้หมายเลขโทรศัพท์เป็นรหัสผ่านจะต้องเปลี่ยนเป็นรหัสผ่านส่วนตัว</span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert danger"><?=h($error)?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

      <label>
        รหัสผ่านปัจจุบัน
        <input type="password" name="current_password" autocomplete="current-password" required>
      </label>

      <label>
        รหัสผ่านใหม่
        <input
          type="password"
          name="new_password"
          minlength="12"
          autocomplete="new-password"
          required
        >
        <span class="note">อย่างน้อย 12 ตัวอักษร และต้องมีตัวอักษรภาษาอังกฤษกับตัวเลข</span>
      </label>

      <label>
        ยืนยันรหัสผ่านใหม่
        <input
          type="password"
          name="confirm_password"
          minlength="12"
          autocomplete="new-password"
          required
        >
      </label>

      <button class="btn big" type="submit">บันทึกรหัสผ่านใหม่</button>
    </form>

    <?php if (!$forced): ?>
      <div class="actions" style="margin-top:14px">
        <a class="btn alt" href="dashboard.php">← กลับหน้าหลักสมาชิก</a>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
