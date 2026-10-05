<?php
require __DIR__.'/config.php';

if (!admin_bootstrap_enabled()) {
    http_response_code(404);
    exit('Not Found');
}

$bootstrapSecret = (string)(getenv('SRU_ADMIN_BOOTSTRAP_TOKEN') ?: '');
if (strlen($bootstrapSecret) < 24) {
    http_response_code(503);
    exit('ยังไม่ได้กำหนด SRU_ADMIN_BOOTSTRAP_TOKEN ที่ปลอดภัย');
}

$pdo = db();
$count = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $count === 0) {
    verify_csrf();

    $limit = auth_rate_limit_status('admin_bootstrap', 'bootstrap');
    if ($limit['blocked']) {
        http_response_code(429);
        $error = 'มีการทดลองใช้งานหลายครั้งเกินกำหนด กรุณารอสักครู่แล้วลองใหม่';
    } else {
        $token = (string)($_POST['bootstrap_token'] ?? '');

        if (!hash_equals($bootstrapSecret, $token)) {
            record_auth_attempt('admin_bootstrap', 'bootstrap', false);
            $error = 'Bootstrap token ไม่ถูกต้อง';
        } else {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($fullName === '') {
                $error = 'กรุณาระบุชื่อผู้ดูแลระบบ';
            } elseif (strlen($username) < 3 || strlen($username) > 100) {
                $error = 'Username ต้องมีความยาว 3-100 ตัวอักษร';
            } elseif (strlen($password) < 12) {
                $error = 'Password ต้องมีอย่างน้อย 12 ตัวอักษร';
            } else {
                try {
                    $st = $pdo->prepare(
                        'INSERT INTO admins(full_name,username,password_hash,is_active)
                         VALUES(?,?,?,1)'
                    );
                    $st->execute([
                        $fullName,
                        $username,
                        password_hash($password, PASSWORD_DEFAULT),
                    ]);

                    record_auth_attempt('admin_bootstrap', 'bootstrap', true);
                    $msg = 'สร้าง Admin สำเร็จ กรุณาปิด SRU_ALLOW_ADMIN_BOOTSTRAP และนำ token ออกจาก Environment ทันที';
                    $count = 1;
                } catch (Throwable $e) {
                    $error = safe_error_message($e, 'ไม่สามารถสร้างผู้ดูแลระบบได้');
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create First Admin</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth-wrap">
  <div class="card">
    <h1>สร้างผู้ดูแลระบบครั้งแรก</h1>

    <?php if ($msg): ?>
      <div class="alert ok"><?=h($msg)?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert danger"><?=h($error)?></div>
    <?php endif; ?>

    <?php if ($count > 0): ?>
      <p>มีบัญชี Admin แล้ว ระบบปิดการสร้างบัญชีผ่านหน้านี้</p>
    <?php else: ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

        <label>
          Bootstrap Token
          <input type="password" name="bootstrap_token" required autocomplete="off">
        </label>

        <label>
          ชื่อ-นามสกุล
          <input name="full_name" required maxlength="255">
        </label>

        <label>
          Username
          <input name="username" required minlength="3" maxlength="100" autocomplete="off">
        </label>

        <label>
          Password
          <input type="password" name="password" minlength="12" required autocomplete="new-password">
        </label>

        <button class="btn">สร้าง Admin</button>
      </form>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
