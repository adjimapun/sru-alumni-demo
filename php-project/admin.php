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
                 SET status="paid",reviewed_at=NOW(),reviewer_id=?
                 WHERE id=?'
            )->execute([$_SESSION['admin_id'], $paymentId]);

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
                 SET status="invalid",reviewed_at=NOW(),reviewer_id=?
                 WHERE id=?'
            )->execute([$_SESSION['admin_id'], $paymentId]);

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

// ตัวกรองข้อมูล: คณะ / สถานะ / ชื่อ-สกุล
$filterFaculty = (int)($_GET['faculty_id'] ?? $_POST['filter_faculty'] ?? 0);
$filterStatus = trim($_GET['status'] ?? $_POST['filter_status'] ?? '');
$filterName = trim($_GET['name'] ?? $_POST['filter_name'] ?? '');

$allowedStatuses = ['pending_payment','payment_review','payment_invalid','approved'];
if ($filterStatus !== '' && !in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = '';
}

$faculties = $pdo->query('SELECT id,name FROM faculties ORDER BY name')->fetchAll();

$sql = 'SELECT
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
        LEFT JOIN receipts r ON r.member_id=m.id';

$where = [];
$params = [];

if ($filterFaculty > 0) {
    $where[] = 'a.faculty_id = :faculty_id';
    $params['faculty_id'] = $filterFaculty;
}

if ($filterStatus !== '') {
    $where[] = 'a.status = :status';
    $params['status'] = $filterStatus;
}

if ($filterName !== '') {
    $where[] = 'CONCAT(COALESCE(a.title_prefix,"")," ",a.full_name) LIKE :name';
    $params['name'] = '%'.$filterName.'%';
}

if ($where) {
    $sql .= ' WHERE '.implode(' AND ', $where);
}

$sql .= ' ORDER BY a.id DESC';

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function admin_status_class(string $status): string {
    return match ($status) {
        'approved' => 'status-badge status-approved',
        'payment_invalid' => 'status-badge status-invalid',
        'payment_review' => 'status-badge status-review',
        default => 'status-badge status-pending',
    };
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SRU Alumni Admin</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.filter-box{background:#f7fafc;border:1px solid #e1ebf0;border-radius:14px;padding:16px;margin:16px 0 20px}
.filter-grid{display:grid;grid-template-columns:1fr 1fr 1.4fr auto;gap:12px;align-items:end}
.filter-grid label{margin:0}
.filter-actions{display:flex;gap:8px;align-items:center}
.status-badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:13px;font-weight:600;white-space:nowrap}
.status-approved{background:#e8f7ef;color:#0b6b43;border:1px solid #b9e4cd}
.status-invalid{background:#fff0ef;color:#a1261d;border:1px solid #f2c7c3}
.status-review{background:#fff7df;color:#8a6200;border:1px solid #efd995}
.status-pending{background:#eef4f7;color:#536c80;border:1px solid #d7e4ea}
.result-count{font-size:13px;color:#6d7f90;margin-top:8px}
@media(max-width:900px){.filter-grid{grid-template-columns:1fr 1fr}.filter-actions{grid-column:1/-1}}
@media(max-width:600px){.filter-grid{grid-template-columns:1fr}.filter-actions{grid-column:auto}}
</style>
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

<div class="filter-box">
  <form method="get">
    <div class="filter-grid">
      <label>
        คณะ
        <select name="faculty_id">
          <option value="0">ทุกคณะ</option>
          <?php foreach ($faculties as $faculty): ?>
            <option value="<?=h((string)$faculty['id'])?>" <?=$filterFaculty === (int)$faculty['id'] ? 'selected' : ''?>>
              <?=h($faculty['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        สถานะ
        <select name="status">
          <option value="">ทุกสถานะ</option>
          <option value="pending_payment" <?=$filterStatus==='pending_payment'?'selected':''?>>รอชำระค่าธรรมเนียม</option>
          <option value="payment_review" <?=$filterStatus==='payment_review'?'selected':''?>>รอตรวจสอบหลักฐาน</option>
          <option value="payment_invalid" <?=$filterStatus==='payment_invalid'?'selected':''?>>หลักฐานไม่ถูกต้อง</option>
          <option value="approved" <?=$filterStatus==='approved'?'selected':''?>>สมาชิกสมบูรณ์</option>
        </select>
      </label>

      <label>
        ชื่อ-สกุล
        <input type="search" name="name" value="<?=h($filterName)?>" placeholder="ค้นหาชื่อ-นามสกุล">
      </label>

      <div class="filter-actions">
        <button class="btn" type="submit">ค้นหา</button>
        <a class="btn alt" href="admin.php">ล้างตัวกรอง</a>
      </div>
    </div>
  </form>
  <div class="result-count">พบข้อมูล <?=number_format(count($rows))?> รายการ</div>
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

  <td><span class="<?=h(admin_status_class((string)$r['status']))?>"><?=h(app_status_th($r['status']))?></span></td>

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
        <input type="hidden" name="filter_faculty" value="<?=h((string)$filterFaculty)?>">
        <input type="hidden" name="filter_status" value="<?=h($filterStatus)?>">
        <input type="hidden" name="filter_name" value="<?=h($filterName)?>">
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
