<?php
require __DIR__.'/config.php';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ติดต่อเรา - SRU Alumni Association</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');

:root{
  --p:#075b8f;
  --p2:#0a8fa9;
  --bg:#f4f8fb;
  --ink:#173149;
  --mut:#6d7f90;
  --line:#dce8ef;
}

*{box-sizing:border-box}
body{
  margin:0;
  font-family:'Kanit',sans-serif;
  background:var(--bg);
  color:var(--ink);
}

a{color:inherit}

header{
  background:linear-gradient(120deg,#064c78,#0786a6);
  color:#fff;
  padding:16px 5%;
  display:flex;
  align-items:center;
  gap:14px;
  position:sticky;
  top:0;
  z-index:20;
}

.crest{
  width:58px;
  height:58px;
  display:grid;
  place-items:center;
  flex:0 0 auto;
}

.crest img{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
}

header small{
  display:block;
  opacity:.84;
}

.shell{
  display:grid;
  grid-template-columns:230px 1fr;
  max-width:1280px;
  margin:22px auto;
  gap:20px;
  padding:0 16px;
}

.side,.card{
  background:#fff;
  border-radius:18px;
  box-shadow:0 8px 28px #173b5512;
}

.side{
  padding:18px;
  height:max-content;
  position:sticky;
  top:105px;
}

.side-title{font-weight:600}
.note{font-size:12px;color:var(--mut)}
.side .note{margin:6px 0 12px}

.menu{
  display:block;
  text-decoration:none;
  padding:11px 12px;
  border-radius:10px;
  margin:5px 0;
  color:#536c80;
}

.menu:hover,.menu.on{
  background:#eaf6fb;
  color:var(--p);
  font-weight:600;
}

.content{min-width:0}

.hero{
  background:linear-gradient(125deg,#075b8f,#09a2b4);
  color:#fff;
  border-radius:18px;
  padding:25px;
  margin-bottom:18px;
}

.hero h1{
  margin:0 0 5px;
  font-size:28px;
  font-weight:600;
}

.breadcrumb{
  font-size:13px;
  opacity:.9;
}

.card{
  padding:25px;
  margin-bottom:18px;
}

.contact-grid{
  display:grid;
  grid-template-columns:1fr;
  gap:18px;
}

.contact-box{
  border:1px solid var(--line);
  border-radius:16px;
  padding:20px;
  background:#fbfdfe;
}

.contact-box h2{
  margin:0 0 14px;
  color:var(--p);
  font-size:19px;
}

.contact-box.featured{
  position:relative;
  overflow:hidden;
  border:2px solid #0a8fa9;
  background:linear-gradient(135deg,#f0fbff,#ffffff 58%,#eef9fb);
  padding:26px;
  box-shadow:0 14px 34px #075b8f20;
}

.contact-box.featured::before{
  content:"ช่องทางหลัก";
  position:absolute;
  top:16px;
  right:16px;
  background:linear-gradient(120deg,#075b8f,#0a8fa9);
  color:#fff;
  border-radius:999px;
  padding:6px 12px;
  font-size:12px;
  font-weight:600;
}

.contact-box.featured h2{
  margin:0 110px 16px 0;
  font-size:23px;
  color:#064c78;
}

.contact-box.secondary{
  max-width:820px;
}

.contact-subtitle{
  color:#526c7f;
  margin:-4px 0 18px;
  font-size:13px;
}

.line-contact{
  display:flex;
  align-items:center;
  gap:18px;
  margin-top:16px;
  padding:16px;
  border:1px solid #dbeaf0;
  background:#fff;
  border-radius:14px;
}

.line-qr{
  width:142px;
  height:142px;
  object-fit:contain;
  border:1px solid #e2e8ec;
  border-radius:12px;
  padding:6px;
  background:#fff;
}

.line-title{
  font-size:18px;
  font-weight:600;
  color:var(--p);
  margin-bottom:4px;
}

.line-id{
  color:#526c7f;
  font-size:13px;
  margin-top:5px;
}

.info-row{
  display:grid;
  grid-template-columns:110px 1fr;
  gap:10px;
  margin:10px 0;
  align-items:start;
}

.info-row .label{
  color:#5f7485;
  font-weight:500;
}

.link{
  color:var(--p);
  text-decoration:none;
  font-weight:500;
}

.link:hover{text-decoration:underline}

.support{
  margin-top:16px;
  padding:16px;
  border-left:5px solid var(--p2);
  background:#eef9fb;
  border-radius:10px;
}

.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:18px;
}

.btn{
  display:inline-block;
  text-decoration:none;
  border-radius:10px;
  padding:10px 16px;
  background:var(--p);
  color:#fff;
  font-weight:600;
}

.btn.alt{
  background:#eaf4f8;
  color:var(--p);
}

.site-footer{
  max-width:1280px;
  margin:6px auto 22px;
  padding:0 16px;
}

.site-footer-inner{
  background:#fff;
  border:1px solid var(--line);
  border-radius:16px;
  padding:15px 20px;
  text-align:center;
  color:#5f7485;
  font-size:12px;
  box-shadow:0 6px 20px #173b550b;
}

.site-footer-inner b{
  color:var(--p);
  font-weight:600;
}

@media(max-width:900px){
  .shell{grid-template-columns:1fr}
  .side{display:none}
  .contact-grid{grid-template-columns:1fr}
}

@media(max-width:650px){
  header{padding:13px 16px}
  header b{font-size:14px}
  header small{font-size:11px}
  .crest{width:52px;height:52px}
  .shell{padding:0 12px;margin:14px auto}
  .hero,.card{padding:20px}
  .info-row{grid-template-columns:1fr;gap:2px}
  .contact-box.featured{padding:20px}
  .contact-box.featured h2{margin-right:0;padding-top:34px;font-size:20px}
  .contact-box.featured::before{left:20px;right:auto;top:16px}
  .line-contact{flex-direction:column;text-align:center}
  .line-qr{width:160px;height:160px}
}
</style>
</head>
<body>

<header>
  <div class="crest">
    <img src="assets/alumni-logo.png" alt="โลโก้สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี">
  </div>
  <div>
    <b>สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี</b>
    <small>Suratthani Rajabhat University Alumni Association</small>
  </div>
</header>

<div class="shell">

<aside class="side">
  <div class="side-title">SRU Alumni Association Digital Service</div>
  <p class="note">ช่องทางบริการและติดต่อสอบถาม</p>

  <a class="menu" href="index.php">⌂ หน้าหลัก</a>
  <a class="menu on" href="contact.php">☎ ติดต่อเรา</a>
  <a class="menu" href="admin.php">⚙ สำหรับเจ้าหน้าที่</a>
</aside>

<main class="content">

  <div class="hero">
    <h1>ติดต่อเรา</h1>
    <div class="breadcrumb">หน้าหลัก / ติดต่อเรา</div>
  </div>

  <section class="card">

    <div class="contact-grid">

      <div class="contact-box featured">
        <h2>สำหรับการติดต่อเกี่ยวการรับสมัครสมาชิกสมาคมศิษย์เก่า</h2>
        <p class="contact-subtitle">
          ช่องทางสำหรับสอบถามข้อมูลการสมัครสมาชิก เอกสารประกอบ และสถานะที่เกี่ยวข้องกับการรับสมัครสมาชิกสมาคมศิษย์เก่า
        </p>

        <div class="info-row">
          <div class="label">หน่วยงาน</div>
          <div><b>กองพัฒนานักศึกษา สำนักงานอธิการบดี</b></div>
        </div>

        <div class="info-row">
          <div class="label">สถานที่</div>
          <div>...</div>
        </div>

        <div class="info-row">
          <div class="label">โทรศัพท์</div>
          <div>...</div>
        </div>

        <div class="info-row">
          <div class="label">ผู้ประสานงาน</div>
          <div>...</div>
        </div>
      </div>

      <div class="contact-box secondary">
        <h2>สอบถามปัญหาการใช้งานระบบ</h2>
        <p class="contact-subtitle">
          สำหรับปัญหาด้านการเข้าใช้งานระบบ การแสดงผล หรือข้อขัดข้องทางเทคนิค
        </p>

        <div class="info-row">
          <div class="label">หน่วยงาน</div>
          <div>
            งานศูนย์คอมพิวเตอร์ สำนักวิทยบริการและเทคโนโลยีสารสนเทศ
          </div>
        </div>

        <div class="info-row">
          <div class="label">โทรศัพท์</div>
          <div>
            <a class="link" href="tel:077913333">077-913333</a> ต่อ 5118
          </div>
        </div>

        <div class="line-contact">
          <img
            class="line-qr"
            src="https://quickchart.io/qr?text=https%3A%2F%2Fline.me%2FR%2Fti%2Fp%2F%40626ogasn&size=240"
            alt="QR Code LINE OA SRU360"
          >

          <div>
            <div class="line-title">LINE OA : SRU360</div>
            <div>
              <a
                class="link"
                href="https://line.me/R/ti/p/@626ogasn"
                target="_blank"
                rel="noopener noreferrer"
              >เพิ่มเพื่อน LINE OA SRU360</a>
            </div>
            <div class="line-id">ID : @626ogasn</div>
          </div>
        </div>
      </div>

    </div>

    <div class="actions">
      <a class="btn" href="index.php">← กลับหน้าหลัก</a>
    </div>

  </section>

</main>

</div>

<footer class="site-footer">
  <div class="site-footer-inner">
    ออกแบบและพัฒนาระบบโดย
    <b>งานศูนย์คอมพิวเตอร์ สำนักวิทยบริการและเทคโนโลยีสารสนเทศ มหาวิทยาลัยราชภัฏสุราษฎร์ธานี</b>
  </div>
</footer>

</body>
</html>
