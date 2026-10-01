<?php
require __DIR__.'/config.php';

$u = require_login();

$sql = 'SELECT
          r.*,
          m.member_no,
          a.title_prefix,
          a.full_name,
          a.member_type_other,
          mt.name AS member_type_name,
          p.channel,
          p.paid_date,
          p.paid_time
        FROM receipts r
        JOIN members m ON m.id=r.member_id
        JOIN applications a ON a.id=m.application_id
        LEFT JOIN member_types mt ON mt.id=a.member_type_id
        JOIN payments p ON p.id=r.payment_id
        WHERE m.user_id=? AND m.status="active" AND r.status="active"
        ORDER BY r.id DESC
        LIMIT 1';

$st = db()->prepare($sql);
$st->execute([$u['id']]);
$r = $st->fetch();

if (!$r) {
    http_response_code(404);
    exit('ยังไม่มีใบเสร็จรับเงิน');
}

function thai_date_long(string $date): string {
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    $ts = strtotime($date);
    return (int)date('j',$ts).' '.$months[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543);
}

$memberTypeText = $r['member_type_name'] ?: '-';
if (!empty($r['member_type_other'])) {
    $memberTypeText .= ' - '.$r['member_type_other'];
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basePath = rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])), '/');
$verifyUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].$basePath.'/verify_receipt.php?code='.urlencode($r['verification_code']);
$downloadName = 'SRU-Alumni-Receipt-'.$r['receipt_no'].'.png';
$receiptSettings = receipt_settings();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ใบเสร็จรับเงิน <?=h($r['receipt_no'])?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;background:#eaf4f8;font-family:'Kanit',sans-serif;color:#123b59}
.page{max-width:1250px;margin:28px auto;padding:0 16px}
.toolbar{display:flex;gap:10px;justify-content:flex-end;margin-bottom:12px;flex-wrap:wrap}
.btn{border:0;border-radius:10px;padding:11px 18px;background:#075b8f;color:#fff;font-family:inherit;font-weight:500;cursor:pointer;text-decoration:none}
.btn.alt{background:#fff;color:#075b8f;border:1px solid #bcd5e1}

.receipt-card{
  position:relative;
  overflow:hidden;
  background:
    radial-gradient(circle at 100% 0,#17a8b828,transparent 34%),
    linear-gradient(180deg,#fff,#f8fdff);
  border:1px solid #b8d6e2;
  border-radius:24px;
  padding:34px 42px 30px;
  box-shadow:0 20px 55px #13425e24;
}

.receipt-card:before,
.receipt-card:after{
  content:'';
  position:absolute;
  height:12px;
  left:0;right:0;
  background:linear-gradient(90deg,#084c75,#0a8fa9,#39bbc0);
}
.receipt-card:before{top:0}
.receipt-card:after{bottom:0}

.top{
  display:grid;
  grid-template-columns:130px 1fr auto;
  gap:24px;
  align-items:center;
  padding-top:6px;
}

.emblem{
  width:115px;height:135px;
  border:3px solid #0c496d;
  border-radius:20px 20px 42px 42px;
  display:grid;
  place-items:center;
  font-weight:700;
  text-align:center;
  color:#0c496d;
  background:#fff;
  line-height:1.15;
}

.assoc h1{font-size:32px;margin:0;color:#073f65}
.assoc .en{font-size:18px;color:#2f6987}
.assoc .tag{font-size:14px;margin-top:8px;color:#557d93}
.doc-meta{text-align:right;font-size:16px}
.doc-meta b{color:#073f65}

.title{
  width:min(620px,80%);
  margin:26px auto 24px;
  text-align:center;
  background:linear-gradient(90deg,#0a6d91,#075b8f);
  color:#fff;
  padding:13px 24px;
  border-radius:60px;
}
.title strong{display:block;font-size:31px;font-weight:600;line-height:1}
.title span{font-size:17px;opacity:.92}

.content{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:22px;
}

.panel{
  background:#f4fbfe;
  border:1px solid #d7eaf1;
  border-radius:18px;
  padding:20px;
}

.panel h3{
  margin:0 0 14px;
  color:#064d76;
  display:flex;
  align-items:center;
  gap:9px;
}

.icon{
  width:34px;height:34px;border-radius:50%;
  display:grid;place-items:center;
  background:#075b8f;color:white;
  font-size:17px;
}.icon svg{width:19px;height:19px;display:block;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

.info-row{
  display:grid;
  grid-template-columns:150px 1fr;
  gap:8px;
  padding:4px 0;
}
.info-row .label{font-weight:500;color:#3d6277}

.payment-box{
  margin-top:15px;
  padding:16px;
  background:#fff;
  border:1px solid #9fd1e1;
  border-radius:14px;
}

.total{
  display:flex;
  justify-content:space-between;
  align-items:end;
  padding:18px;
  border:1px solid #85c9dd;
  border-radius:14px;
  margin-top:14px;
  background:linear-gradient(90deg,#f5fcff,#eaf9fd);
}
.total strong{font-size:39px;color:#063e63}
.total span{font-size:18px}

.verify{
  display:grid;
  grid-template-columns:108px 1fr;
  gap:14px;
  align-items:center;
  margin-top:14px;
  background:#fff;
  border:1px solid #9fd1e1;
  border-radius:14px;
  padding:12px;
}
#qrcode{width:100px;height:100px;display:grid;place-items:center}
.verify-code{font-family:monospace;font-size:12px;word-break:break-all;color:#5d7280}

.footer{
  display:flex;
  justify-content:space-between;
  align-items:flex-end;
  gap:20px;
  margin-top:22px;
  padding-bottom:10px;
}
.thank{font-size:15px;color:#527589}
.signature{text-align:center;min-width:260px}.signature-image{display:block;max-width:220px;max-height:78px;object-fit:contain;margin:0 auto 4px}
.signature .line{border-top:1px solid #7fa9bb;margin-top:38px;padding-top:7px}
.system-note{text-align:center;font-size:12px;color:#7691a0;margin-top:10px}

@media(max-width:850px){
  .top{grid-template-columns:90px 1fr}.emblem{width:84px;height:100px;font-size:12px}.doc-meta{grid-column:1/-1;text-align:left}
  .content{grid-template-columns:1fr}.title{width:100%}.assoc h1{font-size:24px}.receipt-card{padding:28px 20px}.info-row{grid-template-columns:130px 1fr}
}
@media print{
  body{background:#fff}.page{margin:0;max-width:none;padding:0}.toolbar{display:none}.receipt-card{box-shadow:none;border:0;border-radius:0}
}
</style>
</head>
<body>

<div class="page">
<div class="toolbar">
  <a class="btn alt" href="dashboard.php">กลับหน้าหลัก</a>
  <button class="btn alt" onclick="window.print()">พิมพ์ / บันทึก PDF</button>
  <button class="btn" id="downloadBtn" onclick="downloadReceipt()">ดาวน์โหลดใบเสร็จเป็นรูปภาพ PNG</button>
</div>

<div id="receiptCard" class="receipt-card">
  <div class="top">
    <div class="emblem">สมาคม<br>ศิษย์เก่า<br>ศ.มรส.</div>

    <div class="assoc">
      <h1>สมาคมศิษย์เก่า<br>มหาวิทยาลัยราชภัฏสุราษฎร์ธานี</h1>
      <div class="en">Suratthani Rajabhat University Alumni Association</div>
      <div class="tag">“ศิษย์เก่า คือ พลังสำคัญในการพัฒนามหาวิทยาลัยและสังคม”</div>
    </div>

    <div class="doc-meta">
      <div>เลขที่ใบเสร็จรับเงิน : <b><?=h($r['receipt_no'])?></b></div>
      <div>วันที่ออกใบเสร็จ : <b><?=h(thai_date_long($r['receipt_date']))?></b></div>
    </div>
  </div>

  <div class="title">
    <strong>ใบเสร็จรับเงิน</strong>
    <span>(อิเล็กทรอนิกส์)</span>
  </div>

  <div class="content">
    <div>
      <div class="panel">
        <h3><span class="icon" aria-hidden="true">
  <svg viewBox="0 0 24 24" focusable="false">
    <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0"/>
  </svg>
</span>ได้รับเงินจาก</h3>
        <div class="info-row"><div class="label">ชื่อ-นามสกุล</div><div><?=h(trim($r['title_prefix'].' '.$r['full_name']))?></div></div>
        <div class="info-row"><div class="label">เลขที่สมาชิก</div><div><b><?=h($r['member_no'])?></b></div></div>
        <div class="info-row"><div class="label">ประเภทสมาชิก</div><div><?=h($memberTypeText)?></div></div>
      </div>

      <div class="panel" style="margin-top:16px">
        <h3><span class="icon" aria-hidden="true">
  <svg viewBox="0 0 24 24" focusable="false">
    <path d="M3 9h18M5 9v8m4-8v8m6-8v8m4-8v8M3 17h18M2 20h20M12 3l9 4H3l9-4Z"/>
  </svg>
</span>ช่องทางการชำระเงิน</h3>
        <div><b>ธนาคารกรุงไทย จำกัด (มหาชน) สาขาขุนทะเล</b></div>
        <div>ชื่อบัญชี : สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี</div>
        <div>เลขที่บัญชี : <b>664-3-78504-9</b></div>
        <div class="note" style="margin-top:7px">ช่องทาง: <?=h($r['channel'])?></div>
      </div>
    </div>

    <div>
      <div class="panel">
        <h3><span class="icon">฿</span>รายการชำระเงิน</h3>
        <div class="payment-box">
          <div style="display:flex;justify-content:space-between;gap:14px">
            <div>ค่าสมาชิกสมาคมศิษย์เก่า<br>มหาวิทยาลัยราชภัฏสุราษฎร์ธานี</div>
            <b><?=number_format((float)$r['amount'],2)?> บาท</b>
          </div>
        </div>

        <div class="total">
          <div>
            <div>จำนวนเงินทั้งสิ้น</div>
            <small>(หนึ่งร้อยบาทถ้วน)</small>
          </div>
          <div><strong><?=number_format((float)$r['amount'],2)?></strong> <span>บาท</span></div>
        </div>

        <div class="verify">
          <div id="qrcode"></div>
          <div>
            <b>QR Code ตรวจสอบใบเสร็จ</b>
            <div style="font-size:13px">สแกนเพื่อตรวจสอบเลขที่ใบเสร็จ สมาชิก และยอดชำระ</div>
            <div class="verify-code"><?=h($r['verification_code'])?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="footer">
    <div class="thank">✓ ขอบคุณที่ร่วมเป็นส่วนหนึ่งในการพัฒนามหาวิทยาลัยของเรา</div>
    <div class="signature">
      <?php if (!empty($receiptSettings['signature_path'])): ?>
        <img
          class="signature-image"
          src="<?=h($receiptSettings['signature_path'])?>"
          alt="ลายเซ็นผู้รับเงิน"
        >
      <?php endif; ?>
      <div class="line">
        <b><?=h((string)$receiptSettings['payee_name'])?></b>
        <?php if (!empty($receiptSettings['payee_position'])): ?>
          <br><?=h((string)$receiptSettings['payee_position'])?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="system-note">
    ใบเสร็จนี้ออกโดยระบบอิเล็กทรอนิกส์ สามารถตรวจสอบความถูกต้องผ่าน QR Code ด้านบน
  </div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
new QRCode(document.getElementById('qrcode'), {
  text: <?=json_encode($verifyUrl, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
  width: 100,
  height: 100,
  correctLevel: QRCode.CorrectLevel.M
});

async function downloadReceipt(){
  const button = document.getElementById('downloadBtn');
  button.disabled = true;
  button.textContent = 'กำลังสร้างไฟล์...';

  try{
    if (document.fonts && document.fonts.ready) await document.fonts.ready;

    const canvas = await html2canvas(document.getElementById('receiptCard'), {
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
    button.textContent = 'ดาวน์โหลดใบเสร็จเป็นรูปภาพ PNG';
  }
}
</script>
</body>
</html>
