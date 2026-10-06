# SRU Alumni Association Membership System

ระบบรับสมัครและบริหารสมาชิกสมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี  
PHP 8.x + MariaDB/MySQL + PDO

## ความสามารถหลัก

- สมัครบัญชีด้วยข้อมูลเพียง 2 รายการ: หมายเลขบัตรประชาชน และหมายเลขโทรศัพท์มือถือ
- Username เริ่มต้นคือหมายเลขบัตรประชาชน
- รหัสผ่านคือหมายเลขโทรศัพท์มือถือ เพื่อให้เข้าใช้งานได้ง่าย และไม่มีการบังคับเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก
- ใบสมัครสมาชิกออนไลน์และแนบหลักฐานการชำระเงิน
- อนุมัติการชำระเงินและออกรหัสสมาชิกอัตโนมัติ
- รหัสสมาชิกเป็นตัวเลข 6 หลัก: ปีปฏิทิน พ.ศ. 2 หลัก + running number 4 หลัก เช่น `690001`
- ใบเสร็จอิเล็กทรอนิกส์พร้อม QR ตรวจสอบ
- บัตรสมาชิกดิจิทัลพร้อม QR ตรวจสอบด้วย token แบบสุ่ม
- สมาชิกอัปโหลด/เปลี่ยนรูปถ่ายบัตรสมาชิกได้
- ตั้งค่าลายเซ็นผู้รับเงินและลายเซ็นนายกสมาคม
- Dashboard เจ้าหน้าที่และการจัดการ Master Data
- PDO Prepared Statements, CSRF, password_hash/password_verify, Login Rate Limit
- Secure Session Cookies, Security Headers และ File Upload Validation

## การติดตั้งฐานข้อมูลใหม่

Import:

```text
sql/sru_alumni.sql
```

ไฟล์ schema ใหม่เป็นโครงสร้างปัจจุบันสำหรับติดตั้งใหม่ ไม่ต้องรัน migrate_v2-v8 ซ้ำสำหรับฐานข้อมูลที่สร้างใหม่จากไฟล์นี้

## การอัปเกรดฐานข้อมูลเดิม

สำรองฐานข้อมูลก่อนทุกครั้ง และรัน Migration ตามลำดับที่ฐานข้อมูลยังขาด

```text
sql/migrate_v2.sql
sql/migrate_v3.sql
sql/migrate_v4.sql
sql/migrate_v5.sql
sql/migrate_v6.sql
sql/migrate_v7.sql
sql/migrate_v8.sql
```

หากฐานข้อมูลปัจจุบันถึง V5 แล้ว ให้รัน `migrate_v6.sql` → `migrate_v7.sql` → `migrate_v8.sql` ตามลำดับ  
หากเคยรันถึง V7 แล้ว ให้รันเฉพาะ `migrate_v8.sql` เพื่อปรับรหัสสมาชิกเดิมจาก `ALUMNI-xxxxxx` เป็นรหัส 6 หลัก

### V6 เพิ่ม

- `users.must_change_password` คงไว้เพื่อรองรับโครงสร้างเดิม แต่ค่าเริ่มต้นเป็น `0`
- `members.verification_code` แบบสุ่ม 32 ตัวอักษร
- ตาราง `auth_attempts` สำหรับ Login Rate Limit
- V7 ปิดการบังคับเปลี่ยนรหัสผ่านสำหรับสมาชิกทั้งหมด
- V8 แปลงรหัสสมาชิกเดิม `ALUMNI-xxxxxx` เป็นรูปแบบปี พ.ศ. 2 หลัก + running 4 หลัก เช่น `690001`

## ตั้งค่าฐานข้อมูล Production

อย่าใส่รหัสผ่านจริงใน GitHub ให้สร้างไฟล์:

```text
config.local.php
```

จาก `config.local.example.php` แล้วกำหนดค่าเฉพาะบน Server

แนะนำ permission:

```bash
chmod 640 config.local.php
chmod 750 uploads
```

Runtime DB user ควรมีเฉพาะสิทธิ์ที่ระบบต้องใช้ เช่น SELECT, INSERT, UPDATE, DELETE  
การ ALTER/CREATE ตารางควรใช้บัญชีสำหรับ Migration แยกต่างหาก

## Environment Variables สำหรับ Production

แนะนำ:

```text
SRU_APP_ENV=production
SRU_FORCE_HTTPS=1
SRU_PUBLIC_BASE_URL=https://ชื่อโดเมนจริง
SRU_ALLOW_ADMIN_BOOTSTRAP=0
```

ถ้า Hestia/Nginx ทำ Reverse Proxy และส่ง `X-Forwarded-Proto` ที่เชื่อถือได้:

```text
SRU_TRUST_PROXY=1
```

### การสร้าง Admin ครั้งแรก

หน้า `create_admin.php` ถูกปิดเป็นค่าเริ่มต้น

ให้เปิดชั่วคราวด้วย:

```text
SRU_ALLOW_ADMIN_BOOTSTRAP=1
SRU_ADMIN_BOOTSTRAP_TOKEN=<สุ่มอย่างน้อย 24 ตัวอักษร>
```

สร้าง Admin เสร็จแล้วต้องปิด `SRU_ALLOW_ADMIN_BOOTSTRAP` และลบ Bootstrap Token ออกจาก Environment ทันที

## Web Server Security

Apache ใช้:
- `.htaccess`
- `uploads/.htaccess`
- `sql/.htaccess`
- `.user.ini`

สำหรับ HestiaCP/Nginx ให้เพิ่มกฎจาก:

```text
../deploy/nginx-production.conf
```

โดยปรับ path `/php-project/` ให้ตรงกับ URL จริงของเว็บไซต์ แล้ว Reload Nginx

ไฟล์ SQL, config, README และไฟล์ script ใน uploads ต้องไม่สามารถเปิดผ่าน Web ได้

## HTTPS / Session

ระบบรองรับ:
- Secure Cookie เมื่อ HTTPS
- HttpOnly
- SameSite=Lax
- Strict Session Mode
- Session ID Rotation
- Session Idle Timeout 2 ชั่วโมง
- HSTS เมื่อทำงานผ่าน HTTPS
- CSP / X-Frame-Options / nosniff / Referrer-Policy

Production ควรใช้งาน HTTPS เท่านั้น

## File Upload

ระบบตรวจ:
- ขนาดไฟล์
- MIME จากเนื้อหาไฟล์
- รูปภาพด้วย `getimagesize()`
- จำกัด dimension/pixel
- PDF ต้องมี magic header `%PDF-`
- ชื่อไฟล์สุ่ม
- uploads ไม่อนุญาตเปิดไฟล์โดยตรงจาก Web
- ไฟล์สลิป รูปสมาชิก ลายเซ็น และเกียรติบัตรถูกส่งผ่าน `secure_file.php` หลังตรวจสิทธิ์

## QR Verification

ใบเสร็จและบัตรสมาชิกใช้ token แบบสุ่มสำหรับ URL ตรวจสอบ  
ไม่ใช้ running member number เป็น public verification key

## ไฟล์ Diagnostic

ไฟล์ตรวจสอบฐานข้อมูลแบบ Public ถูกนำออกจาก Production แล้ว  
หากต้องตรวจ DB ให้ตรวจจาก CLI/SSH หรือสร้างไฟล์ชั่วคราวและลบทันทีหลังใช้งาน

## CDN

บัตรและใบเสร็จใช้ QRCode.js และ html2canvas จาก CDN  
หากระบบต้องทำงานในเครือข่ายปิด ควรดาวน์โหลด library มาเก็บใน Server และแก้ CSP ให้เป็น `self` เท่านั้น

## ก่อนเปิดใช้งานจริง

ดู Checklist เพิ่มเติมที่:

```text
../PRODUCTION_DEPLOY.md
```
