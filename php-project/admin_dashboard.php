<?php
require __DIR__.'/config.php';

$pdo = db();
$admin = current_admin();

if (!$admin) {
    header('Location: admin.php');
    exit;
}

$currentYear = (int)date('Y');
$selectedYear = (int)($_GET['year'] ?? $currentYear);
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = $currentYear;
}
$selectedFaculty = (int)($_GET['faculty_id'] ?? 0);

$faculties = $pdo->query(
    'SELECT id,name FROM faculties ORDER BY name'
)->fetchAll();

$years = $pdo->query(
    'SELECT DISTINCT YEAR(created_at) AS y
     FROM applications
     WHERE created_at IS NOT NULL
     ORDER BY y DESC'
)->fetchAll(PDO::FETCH_COLUMN);
$years = array_values(array_unique(array_map('intval', $years)));
if (!in_array($currentYear, $years, true)) {
    array_unshift($years, $currentYear);
}
rsort($years);

function appWhere(int $year, int $facultyId, string $alias='a'): array {
    $where = ["YEAR($alias.created_at)=:year"];
    $params = ['year'=>$year];

    if ($facultyId > 0) {
        $where[] = "$alias.faculty_id=:faculty_id";
        $params['faculty_id'] = $facultyId;
    }

    return [' WHERE '.implode(' AND ', $where), $params];
}

[$whereApps, $paramsApps] = appWhere($selectedYear, $selectedFaculty);

$st = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_applications,
        SUM(a.status="approved") AS approved,
        SUM(a.status="payment_review") AS payment_review,
        SUM(a.status="payment_invalid") AS payment_invalid,
        SUM(a.status="pending_payment") AS pending_payment
     FROM applications a'.$whereApps
);
$st->execute($paramsApps);
$summary = $st->fetch() ?: [];

$totalApplications = (int)($summary['total_applications'] ?? 0);
$approved = (int)($summary['approved'] ?? 0);
$paymentReview = (int)($summary['payment_review'] ?? 0);
$paymentInvalid = (int)($summary['payment_invalid'] ?? 0);
$pendingPayment = (int)($summary['pending_payment'] ?? 0);
$approvalRate = $totalApplications > 0 ? ($approved / $totalApplications) * 100 : 0;

$memberSql =
    'SELECT COUNT(*) AS active_members
     FROM members m
     JOIN applications a ON a.id=m.application_id
     WHERE m.status="active"
       AND YEAR(a.created_at)=:year';
$memberParams = ['year'=>$selectedYear];
if ($selectedFaculty > 0) {
    $memberSql .= ' AND a.faculty_id=:faculty_id';
    $memberParams['faculty_id'] = $selectedFaculty;
}
$st = $pdo->prepare($memberSql);
$st->execute($memberParams);
$activeMembers = (int)($st->fetchColumn() ?: 0);

$moneySql =
    'SELECT COALESCE(SUM(r.amount),0)
     FROM receipts r
     JOIN members m ON m.id=r.member_id
     JOIN applications a ON a.id=m.application_id
     WHERE r.status="active"
       AND m.status="active"
       AND YEAR(a.created_at)=:year';
$moneyParams = ['year'=>$selectedYear];
if ($selectedFaculty > 0) {
    $moneySql .= ' AND a.faculty_id=:faculty_id';
    $moneyParams['faculty_id'] = $selectedFaculty;
}
$st = $pdo->prepare($moneySql);
$st->execute($moneyParams);
$totalMoney = (float)$st->fetchColumn();

$cancelSql =
    'SELECT COUNT(*)
     FROM members m
     JOIN applications a ON a.id=m.application_id
     WHERE m.status="cancelled"
       AND YEAR(a.created_at)=:year';
$cancelParams = ['year'=>$selectedYear];
if ($selectedFaculty > 0) {
    $cancelSql .= ' AND a.faculty_id=:faculty_id';
    $cancelParams['faculty_id'] = $selectedFaculty;
}
$st = $pdo->prepare($cancelSql);
$st->execute($cancelParams);
$cancelledMembers = (int)($st->fetchColumn() ?: 0);

// สถานะใบสมัคร
$statuses = [
    ['key'=>'approved','label'=>'สมาชิกสมบูรณ์','value'=>$approved,'class'=>'ok'],
    ['key'=>'payment_review','label'=>'รอตรวจสอบหลักฐาน','value'=>$paymentReview,'class'=>'warn'],
    ['key'=>'payment_invalid','label'=>'หลักฐานไม่ถูกต้อง','value'=>$paymentInvalid,'class'=>'danger'],
    ['key'=>'pending_payment','label'=>'รอชำระค่าธรรมเนียม','value'=>$pendingPayment,'class'=>'muted'],
];

// แยกตามคณะ
$facultySql =
    'SELECT
        COALESCE(f.name,"ไม่ระบุคณะ") AS faculty_name,
        COUNT(a.id) AS applications,
        SUM(a.status="approved") AS approved,
        COALESCE(SUM(CASE WHEN r.status="active" AND m.status="active" THEN r.amount ELSE 0 END),0) AS amount
     FROM applications a
     LEFT JOIN faculties f ON f.id=a.faculty_id
     LEFT JOIN members m ON m.application_id=a.id
     LEFT JOIN receipts r ON r.member_id=m.id
     WHERE YEAR(a.created_at)=:year';
$facultyParams = ['year'=>$selectedYear];

if ($selectedFaculty > 0) {
    $facultySql .= ' AND a.faculty_id=:faculty_id';
    $facultyParams['faculty_id'] = $selectedFaculty;
}

$facultySql .=
    ' GROUP BY a.faculty_id,f.name
      ORDER BY applications DESC, faculty_name';

$st = $pdo->prepare($facultySql);
$st->execute($facultyParams);
$facultyRows = $st->fetchAll();

$maxFacultyApps = 1;
foreach ($facultyRows as $row) {
    $maxFacultyApps = max($maxFacultyApps, (int)$row['applications']);
}

// แนวโน้มรายเดือน
$monthlySql =
    'SELECT
        MONTH(a.created_at) AS month_no,
        COUNT(a.id) AS applications,
        SUM(a.status="approved") AS approved,
        COALESCE(SUM(CASE WHEN r.status="active" AND m.status="active" THEN r.amount ELSE 0 END),0) AS amount
     FROM applications a
     LEFT JOIN members m ON m.application_id=a.id
     LEFT JOIN receipts r ON r.member_id=m.id
     WHERE YEAR(a.created_at)=:year';
$monthlyParams = ['year'=>$selectedYear];

if ($selectedFaculty > 0) {
    $monthlySql .= ' AND a.faculty_id=:faculty_id';
    $monthlyParams['faculty_id'] = $selectedFaculty;
}

$monthlySql .= ' GROUP BY MONTH(a.created_at) ORDER BY month_no';

$st = $pdo->prepare($monthlySql);
$st->execute($monthlyParams);
$monthlyRaw = [];
foreach ($st->fetchAll() as $row) {
    $monthlyRaw[(int)$row['month_no']] = $row;
}

$monthNames = [
    1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',
    5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',
    9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'
];

$monthly = [];
$maxMonthApps = 1;
for ($m=1; $m<=12; $m++) {
    $row = $monthlyRaw[$m] ?? ['applications'=>0,'approved'=>0,'amount'=>0];
    $apps = (int)$row['applications'];
    $maxMonthApps = max($maxMonthApps, $apps);
    $monthly[] = [
        'month'=>$m,
        'label'=>$monthNames[$m],
        'applications'=>$apps,
        'approved'=>(int)$row['approved'],
        'amount'=>(float)$row['amount'],
    ];
}

// ประเภทสมาชิก
$typeSql =
    'SELECT
        COALESCE(mt.name,"ไม่ระบุ") AS member_type,
        COUNT(a.id) AS total
     FROM applications a
     LEFT JOIN member_types mt ON mt.id=a.member_type_id
     WHERE YEAR(a.created_at)=:year';
$typeParams = ['year'=>$selectedYear];
if ($selectedFaculty > 0) {
    $typeSql .= ' AND a.faculty_id=:faculty_id';
    $typeParams['faculty_id'] = $selectedFaculty;
}
$typeSql .= ' GROUP BY a.member_type_id,mt.name ORDER BY total DESC';

$st = $pdo->prepare($typeSql);
$st->execute($typeParams);
$memberTypes = $st->fetchAll();

// ปีสำเร็จการศึกษาที่มีผู้สมัครมาก
$gradSql =
    'SELECT
        COALESCE(NULLIF(a.grad_year,""),"ไม่ระบุ") AS grad_year,
        COUNT(*) AS total
     FROM applications a
     WHERE YEAR(a.created_at)=:year';
$gradParams = ['year'=>$selectedYear];
if ($selectedFaculty > 0) {
    $gradSql .= ' AND a.faculty_id=:faculty_id';
    $gradParams['faculty_id'] = $selectedFaculty;
}
$gradSql .= ' GROUP BY a.grad_year ORDER BY total DESC LIMIT 6';

$st = $pdo->prepare($gradSql);
$st->execute($gradParams);
$gradYears = $st->fetchAll();

// รายการล่าสุด
$latestSql =
    'SELECT
        a.application_no,
        a.title_prefix,
        a.full_name,
        a.status,
        a.created_at,
        f.name AS faculty_name
     FROM applications a
     LEFT JOIN faculties f ON f.id=a.faculty_id
     WHERE YEAR(a.created_at)=:year';
$latestParams = ['year'=>$selectedYear];
if ($selectedFaculty > 0) {
    $latestSql .= ' AND a.faculty_id=:faculty_id';
    $latestParams['faculty_id'] = $selectedFaculty;
}
$latestSql .= ' ORDER BY a.id DESC LIMIT 8';

$st = $pdo->prepare($latestSql);
$st->execute($latestParams);
$latestApplications = $st->fetchAll();

$selectedFacultyName = 'ทุกคณะ';
if ($selectedFaculty > 0) {
    foreach ($faculties as $faculty) {
        if ((int)$faculty['id'] === $selectedFaculty) {
            $selectedFacultyName = $faculty['name'];
            break;
        }
    }
}

function dashboardStatusClass(string $status): string {
    return match ($status) {
        'approved' => 'pill ok',
        'payment_invalid' => 'pill danger',
        'payment_review' => 'pill warn',
        default => 'pill muted',
    };
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard ผู้บริหาร - SRU Alumni</title>
<link rel="stylesheet" href="assets/style.css">
<style>
:root{
  --navy:#075b8f;
  --teal:#0a8fa9;
  --ink:#173149;
  --muted:#6d7f90;
  --line:#dce8ef;
  --bg:#f4f8fb;
  --green:#138a5b;
  --amber:#b77a00;
  --red:#b42318;
}
body{background:var(--bg)}
.dashboard-wrap{max-width:1450px;margin:22px auto;padding:0 18px}
.dash-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:18px}
.dash-head h1{margin:0 0 4px;font-size:28px}
.subtle{color:var(--muted);font-size:13px}
.filter-panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px;margin-bottom:18px;box-shadow:0 8px 24px #173b550b}
.filter-form{display:grid;grid-template-columns:180px minmax(260px,1fr) auto;gap:12px;align-items:end}
.filter-form label{margin:0}
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:18px}
.kpi{background:#fff;border:1px solid #e2edf3;border-radius:18px;padding:18px;box-shadow:0 8px 24px #173b550b;position:relative;overflow:hidden}
.kpi:after{content:'';position:absolute;left:0;top:0;bottom:0;width:5px;background:#9fb8c8}
.kpi.primary:after{background:var(--navy)}.kpi.ok:after{background:var(--green)}.kpi.warn:after{background:#e4a11b}.kpi.danger:after{background:var(--red)}.kpi.money:after{background:var(--teal)}
.kpi .label{font-size:13px;color:var(--muted)}
.kpi .value{font-size:30px;font-weight:700;margin:5px 0 2px;color:var(--ink)}
.kpi .meta{font-size:12px;color:var(--muted)}
.layout-2{display:grid;grid-template-columns:1.25fr .75fr;gap:18px;margin-bottom:18px}
.panel{background:#fff;border:1px solid #e2edf3;border-radius:18px;padding:20px;box-shadow:0 8px 24px #173b550b}
.panel h2{margin:0 0 4px;font-size:19px}
.panel-title{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px}
.status-row{display:grid;grid-template-columns:190px 1fr 58px;gap:12px;align-items:center;margin:13px 0}
.progress{height:11px;background:#edf3f6;border-radius:999px;overflow:hidden}
.progress > span{display:block;height:100%;border-radius:999px;background:#7d9db1}
.progress > span.ok{background:#33a474}.progress > span.warn{background:#e4a11b}.progress > span.danger{background:#d65b52}.progress > span.muted{background:#93a9b8}
.pill{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.pill.ok{background:#e8f7ef;color:#0b6b43}.pill.warn{background:#fff7df;color:#8a6200}.pill.danger{background:#fff0ef;color:#a1261d}.pill.muted{background:#eef4f7;color:#536c80}
.faculty-list{display:grid;gap:11px}
.faculty-row{display:grid;grid-template-columns:minmax(180px,1.2fr) 1.6fr 90px 95px;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #edf2f5}
.faculty-row:last-child{border-bottom:0}
.faculty-bar{height:9px;background:#edf3f6;border-radius:999px;overflow:hidden}
.faculty-bar span{display:block;height:100%;background:linear-gradient(90deg,var(--navy),var(--teal));border-radius:999px}
.money{text-align:right;font-weight:600}
.month-chart{display:grid;grid-template-columns:repeat(12,1fr);gap:8px;align-items:end;height:240px;padding:18px 8px 0}
.month-col{height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:6px}
.month-bars{height:175px;width:100%;display:flex;align-items:flex-end;justify-content:center;gap:3px}
.month-bar{width:12px;min-height:2px;border-radius:7px 7px 2px 2px;background:var(--navy)}
.month-bar.approved{background:var(--green)}
.month-label{font-size:11px;color:var(--muted)}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--muted)}
.dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px;background:var(--navy)}.dot.green{background:var(--green)}
.mini-list{display:grid;gap:10px}
.mini-item{display:flex;justify-content:space-between;gap:14px;padding:10px 0;border-bottom:1px solid #edf2f5}.mini-item:last-child{border-bottom:0}
.recent-table{overflow:auto}
.recent-table table{min-width:820px}
.quick-links{display:flex;gap:8px;flex-wrap:wrap}
.empty{padding:22px;text-align:center;color:var(--muted);background:#f8fbfc;border-radius:12px}
@media(max-width:1200px){.kpi-grid{grid-template-columns:repeat(3,1fr)}.layout-2{grid-template-columns:1fr}.faculty-row{grid-template-columns:minmax(180px,1.1fr) 1.5fr 80px 90px}}
@media(max-width:760px){.dash-head{display:block}.quick-links{margin-top:12px}.filter-form{grid-template-columns:1fr}.kpi-grid{grid-template-columns:1fr 1fr}.faculty-row{grid-template-columns:1fr}.faculty-row .money{text-align:left}.month-chart{overflow-x:auto;grid-template-columns:repeat(12,52px)}}
@media(max-width:480px){.kpi-grid{grid-template-columns:1fr}.dashboard-wrap{padding:0 10px}.panel{padding:16px}}
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
        <a href="admin_master.php">คณะ / ประเภทสมาชิก</a>
        <a href="admin_users.php">ผู้ดูแลระบบหลังบ้าน</a>
      </span>
    </span>

    <a href="index.php">หน้าหลักของระบบ</a>
    <a href="admin.php?logout=1">ออกจากระบบ</a>
  </nav>
</header>

<main class="dashboard-wrap">

<div class="dash-head">
  <div>
    <h1>Dashboard การรับสมัครสมาชิกสมาคมศิษย์เก่า</h1>
    <div class="subtle">
      ภาพรวมสำหรับผู้บริหาร · ปี <?=h((string)($selectedYear+543))?> · <?=h($selectedFacultyName)?>
    </div>
  </div>
  <div class="quick-links">
    <a class="btn" href="admin.php">จัดการใบสมัคร</a>
    <a class="btn alt" href="admin_settings.php">ตั้งค่าระบบ</a>
  </div>
</div>

<div class="filter-panel">
<form method="get" class="filter-form">
  <label>
    ปีที่สมัคร
    <select name="year">
      <?php foreach ($years as $year): ?>
      <option value="<?=h((string)$year)?>" <?=$selectedYear===$year?'selected':''?>>
        พ.ศ. <?=h((string)($year+543))?>
      </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>
    คณะ / วิทยาลัย
    <select name="faculty_id">
      <option value="0">ทุกคณะ / วิทยาลัย</option>
      <?php foreach ($faculties as $faculty): ?>
      <option value="<?=h((string)$faculty['id'])?>" <?=$selectedFaculty===(int)$faculty['id']?'selected':''?>>
        <?=h($faculty['name'])?>
      </option>
      <?php endforeach; ?>
    </select>
  </label>

  <div>
    <button class="btn" type="submit">แสดงข้อมูล</button>
    <a class="btn alt" href="admin_dashboard.php">รีเซ็ต</a>
  </div>
</form>
</div>

<section class="kpi-grid">
  <div class="kpi primary">
    <div class="label">ผู้สมัครทั้งหมด</div>
    <div class="value"><?=number_format($totalApplications)?></div>
    <div class="meta">ใบสมัครในปีที่เลือก</div>
  </div>

  <div class="kpi ok">
    <div class="label">สมาชิกที่อนุมัติ</div>
    <div class="value"><?=number_format($activeMembers)?></div>
    <div class="meta">สมาชิกสถานะใช้งานปัจจุบัน</div>
  </div>

  <div class="kpi warn">
    <div class="label">รอตรวจสอบหลักฐาน</div>
    <div class="value"><?=number_format($paymentReview)?></div>
    <div class="meta">ควรดำเนินการโดยเจ้าหน้าที่</div>
  </div>

  <div class="kpi danger">
    <div class="label">หลักฐานไม่ถูกต้อง</div>
    <div class="value"><?=number_format($paymentInvalid)?></div>
    <div class="meta">รอผู้สมัครแก้ไขหลักฐาน</div>
  </div>

  <div class="kpi money">
    <div class="label">ยอดเงินสมาชิกที่อนุมัติแล้ว</div>
    <div class="value"><?=number_format($totalMoney,2)?></div>
    <div class="meta">บาท · เฉพาะใบเสร็จที่มีผล</div>
  </div>

  <div class="kpi">
    <div class="label">อัตราการอนุมัติ</div>
    <div class="value"><?=number_format($approvalRate,1)?>%</div>
    <div class="meta">อนุมัติ ÷ ผู้สมัครทั้งหมด</div>
  </div>
</section>

<div class="layout-2">

<section class="panel">
  <div class="panel-title">
    <div>
      <h2>สถานะใบสมัคร</h2>
      <div class="subtle">เห็นคอขวดของกระบวนการสมัครและตรวจสอบ</div>
    </div>
    <a class="btn alt small" href="admin.php">ดูรายการ</a>
  </div>

  <?php if ($totalApplications > 0): ?>
    <?php foreach ($statuses as $item): ?>
      <?php $pct = ($item['value'] / $totalApplications) * 100; ?>
      <div class="status-row">
        <div><?=h($item['label'])?></div>
        <div class="progress"><span class="<?=h($item['class'])?>" style="width:<?=h(number_format($pct,2,'.',''))?>%"></span></div>
        <div style="text-align:right"><b><?=number_format($item['value'])?></b></div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty">ยังไม่มีข้อมูลในช่วงที่เลือก</div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-title">
    <div>
      <h2>สัญญาณที่ผู้บริหารควรติดตาม</h2>
      <div class="subtle">ตัวเลขที่ช่วยเห็นความเสี่ยงและงานค้าง</div>
    </div>
  </div>

  <div class="mini-list">
    <div class="mini-item">
      <span>รอชำระค่าธรรมเนียม</span>
      <b><?=number_format($pendingPayment)?> รายการ</b>
    </div>
    <div class="mini-item">
      <span>รอตรวจสอบหลักฐาน</span>
      <b><?=number_format($paymentReview)?> รายการ</b>
    </div>
    <div class="mini-item">
      <span>หลักฐานไม่ถูกต้อง</span>
      <b><?=number_format($paymentInvalid)?> รายการ</b>
    </div>
    <div class="mini-item">
      <span>ยกเลิกการอนุมัติ</span>
      <b><?=number_format($cancelledMembers)?> รายการ</b>
    </div>
    <div class="mini-item">
      <span>รายได้เฉลี่ยต่อสมาชิกที่อนุมัติ</span>
      <b><?=number_format($activeMembers ? $totalMoney/$activeMembers : 0,2)?> บาท</b>
    </div>
  </div>
</section>

</div>

<div class="layout-2">

<section class="panel">
  <div class="panel-title">
    <div>
      <h2>ผู้สมัครและสมาชิกแยกตามคณะ / วิทยาลัย</h2>
      <div class="subtle">เปรียบเทียบจำนวนผู้สมัคร การอนุมัติ และยอดเงิน</div>
    </div>
  </div>

  <?php if ($facultyRows): ?>
  <div class="faculty-list">
    <?php foreach ($facultyRows as $row): ?>
      <?php
        $apps = (int)$row['applications'];
        $facultyApproved = (int)$row['approved'];
        $rate = $apps > 0 ? ($facultyApproved/$apps)*100 : 0;
        $width = ($apps/$maxFacultyApps)*100;
      ?>
      <div class="faculty-row">
        <div>
          <b><?=h($row['faculty_name'])?></b>
          <div class="subtle"><?=number_format($apps)?> ผู้สมัคร · อนุมัติ <?=number_format($facultyApproved)?></div>
        </div>
        <div class="faculty-bar"><span style="width:<?=h(number_format($width,2,'.',''))?>%"></span></div>
        <div><b><?=number_format($rate,1)?>%</b><div class="subtle">อนุมัติ</div></div>
        <div class="money"><?=number_format((float)$row['amount'],0)?> ฿</div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">ยังไม่มีข้อมูลคณะในช่วงที่เลือก</div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-title">
    <div>
      <h2>ประเภทสมาชิก</h2>
      <div class="subtle">สัดส่วนผู้สมัครตามประเภทสมาชิก</div>
    </div>
  </div>

  <div class="mini-list">
    <?php foreach ($memberTypes as $row): ?>
    <div class="mini-item">
      <span><?=h($row['member_type'])?></span>
      <b><?=number_format((int)$row['total'])?> คน</b>
    </div>
    <?php endforeach; ?>

    <?php if (!$memberTypes): ?>
      <div class="empty">ยังไม่มีข้อมูล</div>
    <?php endif; ?>
  </div>

  <div style="margin-top:22px">
    <h2 style="font-size:17px">ปีสำเร็จการศึกษาที่พบมาก</h2>
    <div class="mini-list">
      <?php foreach ($gradYears as $row): ?>
      <div class="mini-item">
        <span>ปี <?=h($row['grad_year'])?></span>
        <b><?=number_format((int)$row['total'])?> คน</b>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

</div>

<section class="panel" style="margin-bottom:18px">
  <div class="panel-title">
    <div>
      <h2>แนวโน้มการสมัครและการอนุมัติรายเดือน</h2>
      <div class="subtle">พ.ศ. <?=h((string)($selectedYear+543))?> · แท่งสีน้ำเงิน = สมัคร · สีเขียว = อนุมัติ</div>
    </div>
    <div class="legend">
      <span><i class="dot"></i>ผู้สมัคร</span>
      <span><i class="dot green"></i>อนุมัติ</span>
    </div>
  </div>

  <div class="month-chart">
    <?php foreach ($monthly as $row): ?>
      <?php
        $hApp = max(2, ($row['applications']/$maxMonthApps)*165);
        $hApproved = max(2, ($row['approved']/$maxMonthApps)*165);
      ?>
      <div class="month-col" title="<?=h($row['label'])?>: สมัคร <?=number_format($row['applications'])?> / อนุมัติ <?=number_format($row['approved'])?> / <?=number_format($row['amount'],2)?> บาท">
        <div class="month-bars">
          <div class="month-bar" style="height:<?=h(number_format($hApp,1,'.',''))?>px"></div>
          <div class="month-bar approved" style="height:<?=h(number_format($hApproved,1,'.',''))?>px"></div>
        </div>
        <div class="month-label"><?=h($row['label'])?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-title">
    <div>
      <h2>ผู้สมัครล่าสุด</h2>
      <div class="subtle">ตรวจสอบความเคลื่อนไหวล่าสุดของระบบ</div>
    </div>
    <a class="btn alt small" href="admin.php">ดูใบสมัครทั้งหมด</a>
  </div>

  <div class="recent-table">
  <table>
    <tr>
      <th>เลขใบสมัคร</th>
      <th>ชื่อ-สกุล</th>
      <th>คณะ / วิทยาลัย</th>
      <th>วันที่สมัคร</th>
      <th>สถานะ</th>
    </tr>

    <?php foreach ($latestApplications as $row): ?>
    <tr>
      <td><?=h($row['application_no'] ?? '-')?></td>
      <td><?=h(trim(($row['title_prefix'] ?? '').' '.($row['full_name'] ?? '')))?></td>
      <td><?=h($row['faculty_name'] ?? '-')?></td>
      <td><?=h(date('d/m/Y', strtotime($row['created_at'])))?></td>
      <td><span class="<?=h(dashboardStatusClass((string)$row['status']))?>"><?=h(app_status_th((string)$row['status']))?></span></td>
    </tr>
    <?php endforeach; ?>

    <?php if (!$latestApplications): ?>
    <tr><td colspan="5" style="text-align:center;color:#6d7f90">ยังไม่มีข้อมูล</td></tr>
    <?php endif; ?>
  </table>
  </div>
</section>

</main>
</body>
</html>
