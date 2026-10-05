<?php
require __DIR__.'/config.php';

$u = require_login();
$pdo = db();
$error = '';
$success = '';

function load_member_card(PDO $pdo, int $userId): ?array {
    $st = $pdo->prepare(
        'SELECT
            m.id AS member_id,
            m.member_no,
            m.verification_code,
            m.approved_at,
            m.status AS member_status,
            a.id AS application_id,
            a.title_prefix,
            a.full_name,
            a.member_photo_path,
            a.grad_year,
            a.major,
            a.position,
            a.occupation,
            f.name AS faculty_name,
            mt.name AS member_type_name,
            a.member_type_other
         FROM members m
         JOIN applications a ON a.id=m.application_id
         LEFT JOIN faculties f ON f.id=a.faculty_id
         LEFT JOIN member_types mt ON mt.id=a.member_type_id
         WHERE m.user_id=? AND m.status="active"
         LIMIT 1'
    );
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

$member = load_member_card($pdo, (int)$u['id']);

if (!$member) {
    http_response_code(403);
    exit('ยังไม่สามารถออกบัตรสมาชิกได้ กรุณารอการอนุมัติการชำระเงินและสถานะสมาชิกสมบูรณ์');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $newPhoto = upload_image_file('member_photo', 'member_photo_');

        if (!$newPhoto) {
            throw new RuntimeException('กรุณาเลือกรูปถ่ายที่ต้องการอัปโหลด');
        }

        $oldPhoto = $member['member_photo_path'] ?? null;

        $pdo->prepare(
            'UPDATE applications
             SET member_photo_path=?, updated_at=NOW()
             WHERE id=?'
        )->execute([$newPhoto, $member['application_id']]);

        if ($oldPhoto && $oldPhoto !== $newPhoto) {
            delete_managed_upload($oldPhoto, 'member_photo_');
        }

        $success = 'อัปเดตรูปถ่ายสำหรับบัตรสมาชิกเรียบร้อยแล้ว';
        $member = load_member_card($pdo, (int)$u['id']);

    } catch (Throwable $e) {
        $error = safe_error_message($e);
    }
}

$cardSettings = member_card_settings();
$memberType = $member['member_type_name'] ?: '-';
if (!empty($member['member_type_other'])) {
    $memberType .= ' - '.$member['member_type_other'];
}

$displayOccupation = trim((string)($member['position'] ?? ''));
if ($displayOccupation === '') {
    $displayOccupation = trim((string)($member['occupation'] ?? ''));
}
if ($displayOccupation === '') {
    $displayOccupation = '-';
}

$basePath = rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])), '/');
$verifyUrl = public_base_url().$basePath.'/verify_member.php?code='.urlencode((string)$member['verification_code']);
$downloadName = 'SRU-Alumni-Member-'.$member['member_no'].'.png';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>บัตรสมาชิก <?=h($member['member_no'])?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{
  margin:0;
  background:#e9f1f5;
  font-family:'Kanit',sans-serif;
  color:#102c48;
}
.page{
  max-width:1020px;
  margin:24px auto 40px;
  padding:0 16px;
}
.toolbar{
  display:flex;
  gap:10px;
  justify-content:flex-end;
  flex-wrap:wrap;
  margin-bottom:14px;
}
.btn{
  border:0;
  border-radius:10px;
  padding:11px 18px;
  background:#0b3a67;
  color:#fff;
  text-decoration:none;
  font-family:inherit;
  font-weight:600;
  cursor:pointer;
}
.btn.alt{
  background:#fff;
  color:#0b3a67;
  border:1px solid #bed0dc;
}
.alert{
  padding:13px 15px;
  border-radius:11px;
  margin-bottom:14px;
}
.alert.ok{background:#eaf8f0;color:#116946;border-left:5px solid #15905f}
.alert.danger{background:#fff0ef;color:#91231b;border-left:5px solid #ba3026}
.photo-editor{
  background:#fff;
  border-radius:16px;
  padding:18px;
  margin-bottom:16px;
  box-shadow:0 8px 28px rgba(21,56,81,.08);
}
.photo-editor form{
  display:flex;
  gap:12px;
  flex-wrap:wrap;
  align-items:end;
}
.photo-editor label{
  flex:1 1 380px;
  font-size:14px;
}
.photo-editor input[type=file]{
  width:100%;
  margin-top:6px;
  padding:10px;
  border:1px solid #d7e3ea;
  border-radius:9px;
  background:#fff;
}
.note{font-size:12px;color:#6c8190;margin-top:6px}

.member-card{
  width:min(920px,100%);
  min-height:990px;
  margin:auto;
  position:relative;
  overflow:hidden;
  border-radius:34px;
  background:
    radial-gradient(circle at 75% 64%,rgba(52,146,177,.18),transparent 32%),
    linear-gradient(180deg,#fefefe 0%,#f9fcfd 56%,#edf6f8 100%);
  box-shadow:0 24px 70px rgba(9,42,73,.22);
  border:1px solid #d6e0e7;
}
.card-header{
  height:225px;
  position:relative;
  display:flex;
  align-items:center;
  gap:24px;
  padding:26px 38px;
  background:
    linear-gradient(135deg,#132e50 0%,#0b365f 62%,#092644 100%);
  color:#fff;
}
.card-header:after{
  content:'';
  position:absolute;
  top:0;
  right:0;
  width:260px;
  height:100%;
  background:
    linear-gradient(135deg,transparent 0 42%,#bd2430 43% 49%,transparent 50%);
  opacity:.95;
}
.card-logo{
  width:148px;
  height:165px;
  position:relative;
  z-index:2;
  display:grid;
  place-items:center;
}
.card-logo img{
  max-width:145px;
  max-height:160px;
  object-fit:contain;
  display:block;
}
.card-brand{
  position:relative;
  z-index:2;
  flex:1;
}
.card-brand h1{
  margin:0;
  font-size:34px;
  line-height:1.2;
  font-weight:600;
}
.card-brand .en{
  font-size:19px;
  margin-top:6px;
  opacity:.94;
  letter-spacing:.3px;
}
.card-brand .tagline{
  margin-top:8px;
  font-size:13px;
  opacity:.82;
}
.card-mark{
  position:absolute;
  right:36px;
  top:30px;
  z-index:3;
  font-style:italic;
  font-size:23px;
  line-height:1;
  text-align:right;
  transform:rotate(-7deg);
}
.card-body{
  position:relative;
  padding:38px 42px 155px;
  min-height:765px;
}
.member-card:before{
  content:'';
  position:absolute;
  left:-90px;
  right:-90px;
  bottom:70px;
  height:230px;
  background:
    linear-gradient(180deg,transparent,rgba(58,142,168,.09)),
    radial-gradient(ellipse at 25% 100%,rgba(38,122,146,.20),transparent 45%),
    radial-gradient(ellipse at 70% 100%,rgba(35,110,135,.18),transparent 42%);
  z-index:0;
}
.card-grid{
  display:grid;
  grid-template-columns:285px 1fr;
  gap:30px;
  position:relative;
  z-index:2;
}
.photo-wrap{
  width:285px;
}
.member-photo{
  width:285px;
  height:350px;
  border:5px solid #fff;
  outline:3px solid #183d5f;
  border-radius:24px;
  overflow:hidden;
  background:linear-gradient(145deg,#dcecf3,#f8fbfd);
  box-shadow:0 10px 26px rgba(18,57,84,.14);
  display:grid;
  place-items:center;
}
.member-photo img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}
.photo-placeholder{
  padding:20px;
  text-align:center;
  color:#6b8394;
  font-size:15px;
}
.signature-area{
  margin-top:26px;
  text-align:center;
  min-height:135px;
}
.president-signature{
  max-width:180px;
  max-height:70px;
  object-fit:contain;
  display:block;
  margin:0 auto 3px;
}
.signature-line{
  border-top:1px dashed #8298a7;
  padding-top:7px;
  margin-top:34px;
  font-size:14px;
  line-height:1.45;
}
.president-signature + .signature-line{margin-top:4px}
.detail-area{
  min-width:0;
}
.card-title{
  font-size:31px;
  font-weight:700;
  color:#102c48;
  line-height:1.18;
}
.card-title-en{
  font-size:17px;
  color:#49677e;
  letter-spacing:1.2px;
  margin-top:4px;
}
.member-name{
  margin-top:26px;
  font-size:38px;
  font-weight:700;
  color:#122e4d;
  line-height:1.2;
}
.member-meta{
  margin-top:26px;
  display:grid;
  gap:13px;
}
.meta-row{
  display:grid;
  grid-template-columns:175px 1fr;
  gap:10px;
  align-items:start;
  font-size:20px;
}
.meta-row .label{
  color:#35566f;
  font-weight:500;
}
.meta-row .value{
  color:#102c48;
  font-weight:500;
  word-break:break-word;
}
.member-number{
  font-size:27px;
  font-weight:700;
  letter-spacing:1px;
}
.qr-panel{
  position:absolute;
  right:38px;
  top:37px;
  width:155px;
  border:3px solid #142e4d;
  border-radius:16px;
  overflow:hidden;
  background:#fff;
  text-align:center;
  z-index:3;
}
.qr-box{
  width:132px;
  height:132px;
  margin:9px auto 3px;
  display:grid;
  place-items:center;
}
.qr-label{
  background:#102c48;
  color:#fff;
  padding:7px 5px;
  font-size:12px;
  line-height:1.25;
}
.detail-area.has-qr{
  padding-right:170px;
}
.status-row{
  margin-top:18px;
  display:flex;
  align-items:center;
  gap:10px;
}
.status-badge{
  display:inline-flex;
  align-items:center;
  gap:7px;
  color:#fff;
  background:linear-gradient(90deg,#a71f2a,#cf3340);
  padding:7px 15px;
  border-radius:999px;
  font-weight:600;
  font-size:15px;
}
.status-dot{
  width:22px;
  height:22px;
  border-radius:50%;
  display:grid;
  place-items:center;
  background:#fff;
  color:#ad2630;
  font-weight:700;
}
.bottom-ribbon{
  position:absolute;
  left:0;
  right:0;
  bottom:0;
  height:128px;
  z-index:4;
  background:
    linear-gradient(166deg,transparent 0 18%,#b7202d 19% 52%,#0d2d4d 53% 100%);
  color:#fff;
}
.bottom-ribbon .motto{
  position:absolute;
  left:48px;
  bottom:35px;
  font-size:25px;
  font-weight:500;
}
.bottom-ribbon .university{
  position:absolute;
  right:36px;
  bottom:25px;
  text-align:right;
  font-size:16px;
  line-height:1.35;
  letter-spacing:.7px;
}
@media(max-width:820px){
  .member-card{border-radius:22px}
  .card-header{height:auto;min-height:190px;padding:24px}
  .card-logo{width:105px;height:125px}
  .card-logo img{max-width:102px;max-height:120px}
  .card-brand h1{font-size:25px}
  .card-brand .en{font-size:14px}
  .card-mark{display:none}
  .card-body{padding:26px 24px 150px}
  .card-grid{grid-template-columns:1fr}
  .photo-wrap{width:100%}
  .member-photo{width:230px;height:285px;margin:auto}
  .detail-area.has-qr{padding-right:0}
  .qr-panel{position:static;width:165px;margin:20px auto}
  .meta-row{grid-template-columns:135px 1fr;font-size:16px}
  .member-name{font-size:28px}
  .card-title{font-size:25px}
  .bottom-ribbon .motto{left:22px;font-size:18px}
  .bottom-ribbon .university{right:20px;font-size:12px}
}
@media print{
  body{background:#fff}
  .toolbar,.photo-editor,.alert{display:none!important}
  .page{margin:0;padding:0;max-width:none}
  .member-card{box-shadow:none}
}
</style>
</head>
<body>

<div class="page">

  <?php if ($error): ?>
    <div class="alert danger"><?=h($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert ok"><?=h($success)?></div>
  <?php endif; ?>

  <div class="toolbar">
    <a class="btn alt" href="dashboard.php">← กลับหน้าสมาชิก</a>
    <button class="btn alt" type="button" onclick="window.print()">พิมพ์ / บันทึก PDF</button>
    <button class="btn" id="downloadBtn" type="button" onclick="downloadMemberCard()">ดาวน์โหลดบัตรสมาชิก PNG</button>
  </div>

  <div class="photo-editor">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>
        อัปโหลด / เปลี่ยนรูปถ่ายบนบัตรสมาชิก
        <input
          type="file"
          name="member_photo"
          accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
          required
        >
        <div class="note">แนะนำรูปหน้าตรงแนวตั้ง ภาพคมชัด พื้นหลังเรียบ ขนาดไม่เกิน 5 MB</div>
      </label>
      <button class="btn" type="submit">บันทึกรูปถ่าย</button>
    </form>
  </div>

  <div id="memberCard" class="member-card">

    <div class="card-header">
      <div class="card-logo">
        <img src="assets/alumni-logo.png" alt="โลโก้สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี">
      </div>

      <div class="card-brand">
        <h1>สมาคมศิษย์เก่า<br>มหาวิทยาลัยราชภัฏสุราษฎร์ธานี</h1>
        <div class="en">SRU ALUMNI ASSOCIATION</div>
        <div class="tagline">เชื่อมคน • เชื่อมโอกาส • สร้างชุมชนศิษย์เก่าที่เข้มแข็ง</div>
      </div>

      <div class="card-mark">SRU<br>Alumni</div>
    </div>

    <div class="card-body">

      <div class="qr-panel">
        <div id="memberQr" class="qr-box"></div>
        <div class="qr-label">
          DIGITAL MEMBER<br>
          สแกนเพื่อตรวจสอบสมาชิก
        </div>
      </div>

      <div class="card-grid">

        <div class="photo-wrap">
          <div class="member-photo">
            <?php if (!empty($member['member_photo_path'])): ?>
              <img src="<?=h($member['member_photo_path'])?>" alt="รูปสมาชิก">
            <?php else: ?>
              <div class="photo-placeholder">
                กรุณาอัปโหลดรูปถ่าย<br>เพื่อใช้บนบัตรสมาชิก
              </div>
            <?php endif; ?>
          </div>

          <div class="signature-area">
            <?php if (!empty($cardSettings['president_signature_path'])): ?>
              <img
                class="president-signature"
                src="<?=h($cardSettings['president_signature_path'])?>"
                alt="ลายเซ็นนายกสมาคม"
              >
            <?php endif; ?>

            <div class="signature-line">
              <?php if (!empty($cardSettings['president_name'])): ?>
                (<?=h($cardSettings['president_name'])?>)<br>
              <?php endif; ?>
              <?=h((string)$cardSettings['president_position'])?>
            </div>
          </div>
        </div>

        <div class="detail-area has-qr">
          <div class="card-title">บัตรสมาชิกสมาคมศิษย์เก่า</div>
          <div class="card-title-en">ALUMNI MEMBERSHIP CARD</div>

          <div class="member-name">
            <?=h(trim($member['title_prefix'].' '.$member['full_name']))?>
          </div>

          <div class="member-meta">
            <div class="meta-row">
              <div class="label">รหัสสมาชิก :</div>
              <div class="value member-number"><?=h($member['member_no'])?></div>
            </div>

            <div class="meta-row">
              <div class="label">รุ่นที่สำเร็จการศึกษา :</div>
              <div class="value"><?=h((string)($member['grad_year'] ?: '-'))?></div>
            </div>

            <div class="meta-row">
              <div class="label">คณะ :</div>
              <div class="value"><?=h((string)($member['faculty_name'] ?: '-'))?></div>
            </div>

            <div class="meta-row">
              <div class="label">สาขาวิชา :</div>
              <div class="value"><?=h((string)($member['major'] ?: '-'))?></div>
            </div>

            <div class="meta-row">
              <div class="label">ตำแหน่ง/อาชีพ :</div>
              <div class="value"><?=h($displayOccupation)?></div>
            </div>

            <div class="meta-row">
              <div class="label">ประเภทสมาชิก :</div>
              <div class="value"><?=h($memberType)?></div>
            </div>
          </div>

          <div class="status-row">
            <span class="label">สถานะสมาชิก :</span>
            <span class="status-badge">
              <span class="status-dot">✓</span>
              สมาชิกปกติ
            </span>
          </div>
        </div>

      </div>
    </div>

    <div class="bottom-ribbon">
      <div class="motto">ศิษย์เก่า มรส. คือ พลังของแผ่นดิน</div>
      <div class="university">SURATTHANI<br>RAJABHAT UNIVERSITY</div>
    </div>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
new QRCode(document.getElementById('memberQr'), {
  text: <?=json_encode($verifyUrl, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
  width: 124,
  height: 124,
  correctLevel: QRCode.CorrectLevel.M
});

async function downloadMemberCard(){
  const button = document.getElementById('downloadBtn');
  button.disabled = true;
  button.textContent = 'กำลังสร้างบัตร...';

  try{
    if(document.fonts && document.fonts.ready){
      await document.fonts.ready;
    }

    const card = document.getElementById('memberCard');
    const canvas = await html2canvas(card, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true
    });

    const link = document.createElement('a');
    link.download = <?=json_encode($downloadName, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
    link.href = canvas.toDataURL('image/png');
    link.click();
  } finally {
    button.disabled = false;
    button.textContent = 'ดาวน์โหลดบัตรสมาชิก PNG';
  }
}
</script>

</body>
</html>
