<?php
require __DIR__.'/config.php';

$pdo = db();
$error = '';
$success = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
    header('Location: admin.php');
    exit;
}

if (!current_admin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $st = $pdo->prepare('SELECT * FROM admins WHERE username=? LIMIT 1');
        $st->execute([trim($_POST['username'] ?? '')]);
        $a = $st->fetch();

        if ($a && password_verify($_POST['password'] ?? '', $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $a['id'];
            header('Location: admin.php');
            exit;
        }
        $error = 'เข้าสู่ระบบไม่สำเร็จ';
    }
    ?>
    <!doctype html>
    <html lang="th">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Admin Login</title>
      <link rel="stylesheet" href="assets/style.css">
    </head>
    <body>
    <main class="auth-wrap">
      <div class="card">
        <h1>เจ้าหน้าที่ / ผู้ดูแลระบบ</h1>
        <?php if ($error): ?><div class="alert danger"><?=h($error)?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <label>Username<input name="username" required></label>
          <label>Password<input name="password" type="password" required></label>
          <button class="btn">เข้าสู่ระบบ</button>
        </form>
        <div class="actions" style="margin-top:14px">
          <a class="btn alt" href="index.php">← กลับหน้าหลักของระบบ</a>
        </div>
      </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['decision'])) {
    verify_csrf();

    $paymentId = (int)$_POST['payment_id'];
    $decision = $_POST['decision'];
    $note = '';

    $pdo->beginTransaction();

    try {
        $st = $pdo->prepare(
            'SELECT p.*,a.user_id,a.status AS application_status
             FROM payments p
             JOIN applications a ON a.id=p.application_id
             WHERE p.id=? FOR UPDATE'
        );
        $st->execute([$paymentId]);
        $payment = $st->fetch();

        if (!$payment) throw new RuntimeException('ไม่พบรายการชำระเงิน');

        if ($decision === 'approve') {
            $st = $pdo->prepare('SELECT * FROM members WHERE application_id=? FOR UPDATE');
            $st->execute([$payment['application_id']]);
            $existingMember = $st->fetch();

            if ($existingMember) {
                throw new RuntimeException('ใบสมัครนี้ได้รับการอนุมัติและออกเลขสมาชิกแล้ว');
            }

            $pdo->prepare(
                'UPDATE payments
                 SET status="paid",reviewed_at=NOW(),reviewer_id=?,note=?
                 WHERE id=?'
            )->execute([$_SESSION['admin_id'], $note, $paymentId]);

            $pdo->prepare('UPDATE applications SET status="approved" WHERE id=?')
                ->execute([$payment['application_id']]);

            // members.id เป็นลำดับการอนุมัติการชำระเงินจริง
            $pdo->prepare(
                'INSERT INTO members(application_id,user_id,approved_payment_id,member_no)
                 VALUES(?,?,?,"PENDING")'
            )->execute([$payment['application_id'], $payment['user_id'], $paymentId]);

            $memberId = (int)$pdo->lastInsertId();
            $memberNo = 'ALUMNI-'.str_pad((string)$memberId, 6, '0', STR_PAD_LEFT);

            $pdo->prepare('UPDATE members SET member_no=? WHERE id=?')
                ->execute([$memberNo, $memberId]);

            $verificationCode = bin2hex(random_bytes(16));

            $pdo->prepare(
                'INSERT INTO receipts(member_id,payment_id,receipt_no,receipt_date,amount,verification_code)
                 VALUES(?,?,"PENDING",CURDATE(),?,?)'
            )->execute([$memberId, $paymentId, $payment['amount'], $verificationCode]);

            $receiptId = (int)$pdo->lastInsertId();
            $receiptNo = thai_year().'-'.str_pad((string)$receiptId, 4, '0', STR_PAD_LEFT);

            $pdo->prepare('UPDATE receipts SET receipt_no=? WHERE id=?')
                ->execute([$receiptNo, $receiptId]);

            $success = 'อนุมัติสมาชิกแล้ว เลขสมาชิก '.$memberNo.' และออกใบเสร็จ '.$receiptNo.' เรียบร้อย';
        } elseif ($decision === 'invalid') {
            $pdo->prepare(
                'UPDATE payments
                 SET status="invalid",reviewed_at=NOW(),reviewer_id=?,note=?
                 WHERE id=?'
            )->execute([$_SESSION['admin_id'], $note, $paymentId]);

            $pdo->prepare('UPDATE applications SET status="payment_invalid" WHERE id=?')
                ->execute([$payment['application_id']]);

            $success = 'ปรับสถานะหลักฐานเป็นไม่ถูกต้องแล้ว';
        } else {
            throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$rows = $pdo->query(
    'SELECT
        a.*,
        f.name AS faculty_name,
        mt.name AS member_type_name,
        m.member_no,
        r.receipt_no,
        (SELECT id FROM payments p WHERE p.application_id=a.id ORDER BY p.id DESC LIMIT 1) AS payment_id,
        (SELECT status FROM payments p WHERE p.application_id=a.id ORDER BY p.id DESC LIMIT 1) AS payment_status,
        (SELECT slip_path FROM payments p WHERE p.application_id=a.id ORDER BY p.id DESC LIMIT 1) AS slip_path
     FROM applications a
     LEFT JOIN faculties f ON f.id=a.faculty_id
     LEFT JOIN member_types mt ON mt.id=a.member_type_id
     LEFT JOIN members m ON m.application_id=a.id
     LEFT JOIN receipts r ON r.member_id=m.id
     ORDER BY a.id DESC'
)->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SRU Alumni Admin</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <div class="brand">SRU Alumni Admin</div>
  <nav>
    <a href="index.php">หน้าหลักของระบบ</a>
    <a href="admin.php">ใบสมัคร</a>
    <a href="admin_master.php">ข้อมูลคณะ / ประเภทสมาชิก</a>
    <a href="admin.php?logout=1">ออกจากระบบ</a>
  </nav>
</header>

<main class="container">
<div class="card">
<h1>จัดการใบสมัครและตรวจสอบการชำระเงิน</h1>

<?php if ($error): ?><div class="alert danger"><?=h($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert ok"><?=h($success)?></div><?php endif; ?>

<div class="actions" style="margin-bottom:16px">
  <a class="btn alt" href="admin_master.php">จัดการข้อมูลคณะ / ประเภทสมาชิก</a>
</div>

<table>
<tr>
  <th>ลำดับ</th>
  <th>เลขใบสมัคร</th>
  <th>ผู้สมัคร</th>
  <th>คณะ / สาขา</th>
  <th>ประเภทสมาชิก</th>
  <th>สถานะ</th>
  <th>หลักฐาน</th>
  <th>ดำเนินการ</th>
</tr>

<?php foreach ($rows as $index => $r): ?>
<tr>
  <td><?=h((string)($index + 1))?></td>
  <td>
    <?=h($r['application_no'])?>
    <?php if ($r['member_no']): ?><br><span class="badge ok"><?=h($r['member_no'])?></span><?php endif; ?>
  </td>

  <td><?=h(trim(($r['title_prefix'] ?? '').' '.($r['full_name'] ?? '')))?></td>

  <td>
    <?=h($r['faculty_name'] ?? '-')?>
    <?php if ($r['major']): ?><br><span class="note"><?=h($r['major'])?></span><?php endif; ?>
  </td>

  <td>
    <?=h($r['member_type_name'] ?? '-')?>
    <?php if ($r['member_type_other']): ?><br><span class="note"><?=h($r['member_type_other'])?></span><?php endif; ?>
  </td>

  <td><?=h(app_status_th($r['status']))?></td>

  <td>
    <?php if ($r['slip_path']): ?>
      <a href="<?=h($r['slip_path'])?>" target="_blank">เปิดสลิป</a>
    <?php else: ?>-<?php endif; ?>
    <?php if ($r['receipt_no']): ?><br><span class="note">ใบเสร็จ <?=h($r['receipt_no'])?></span><?php endif; ?>
  </td>

  <td>
    <?php if ($r['member_no']): ?>
      <span class="badge ok">อนุมัติแล้ว</span>
    <?php elseif ($r['payment_id']): ?>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="payment_id" value="<?=h((string)$r['payment_id'])?>">
        <button class="btn small" name="decision" value="approve">ยืนยัน / อนุมัติ</button>
        <button class="btn danger small" name="decision" value="invalid">หลักฐานไม่ถูกต้อง</button>
      </form>
    <?php else: ?>
      <span class="note">รอการชำระเงิน</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>

</table>
</div>
</main>
</body>
</html>
