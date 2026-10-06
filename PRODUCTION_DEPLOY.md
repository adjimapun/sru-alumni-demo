# SRU Alumni Association — Production Deployment Checklist

## 1. Backup

ก่อนแก้ฐานข้อมูล:

```bash
mysqldump --single-transaction --routines --triggers DATABASE_NAME > alumni_before_v6.sql
```

เก็บ Backup ไว้นอก Web Root

## 2. Database

ฐานข้อมูลเดิม:
- ถ้าถึง V4 แล้ว: รัน V5 → V6 → V7 → V8
- ถ้าถึง V5 แล้ว: รัน V6 → V7 → V8
- ถ้าเคยรันถึง V7 แล้ว: รัน V8
- V8 จะปรับรหัสสมาชิกเดิม `ALUMNI-xxxxxx` เป็นเลข 6 หลัก เช่น `690001`
- ห้ามรัน migration ที่เพิ่ม Column/Table เดิมซ้ำ

ฐานข้อมูลใหม่:
- Import `php-project/sql/sru_alumni.sql` อย่างเดียว

## 3. Credentials

สร้าง `php-project/config.local.php` บน Server เท่านั้น  
ห้าม commit ไฟล์นี้ และห้ามเก็บรหัสผ่านจริงไว้ใน repository

ตั้ง permission:

```bash
chmod 640 php-project/config.local.php
chmod 750 php-project/uploads
```

## 4. PHP

แนะนำ PHP 8.2/8.3 พร้อม Extensions:
- PDO MySQL
- fileinfo
- GD หรือ Imagick สำหรับงานรูปในอนาคต
- OpenSSL

ตรวจให้ `display_errors=Off` บน Production

## 5. HTTPS

กำหนด Environment:

```text
SRU_APP_ENV=production
SRU_FORCE_HTTPS=1
SRU_PUBLIC_BASE_URL=https://YOUR-DOMAIN
```

หากใช้ Reverse Proxy ของ Hestia/Nginx:

```text
SRU_TRUST_PROXY=1
```

เปิด `SRU_TRUST_PROXY` เฉพาะเมื่อ Proxy เป็นขององค์กรและกำหนด Header ให้เอง

## 6. Nginx/HestiaCP

นำกฎใน `deploy/nginx-production.conf` ไปใส่ใน Nginx template/config ของโดเมน  
แก้ `/php-project/` ให้ตรงกับ URL จริง แล้ว:

```bash
nginx -t
systemctl reload nginx
```

ตรวจว่า URL ต่อไปนี้ต้องได้ 403/404:
- /php-project/sql/migrate_v6.sql
- /php-project/config.local.php
- /php-project/.user.ini
- /php-project/uploads/test.php

## 7. Apache

ถ้ามี Apache หลัง Nginx ต้องเปิด AllowOverride ที่อนุญาต .htaccess  
ตรวจ `php-project/.htaccess`, `sql/.htaccess`, `uploads/.htaccess`

## 8. First Admin

ค่าเริ่มต้น `create_admin.php` ถูกปิด

กรณีติดตั้งใหม่ เปิดชั่วคราว:

```text
SRU_ALLOW_ADMIN_BOOTSTRAP=1
SRU_ADMIN_BOOTSTRAP_TOKEN=<random-long-secret>
```

สร้าง Admin เสร็จแล้ว:
- เปลี่ยน `SRU_ALLOW_ADMIN_BOOTSTRAP=0`
- ลบ `SRU_ADMIN_BOOTSTRAP_TOKEN`
- ทดสอบว่า create_admin.php คืน 404

## 9. Passwords

สมาชิกสมัครบัญชีด้วยหมายเลขบัตรประชาชนและหมายเลขโทรศัพท์มือถือเท่านั้น  
Username = หมายเลขบัตรประชาชน  
Password = หมายเลขโทรศัพท์มือถือ  
ระบบไม่บังคับเปลี่ยนรหัสผ่านเมื่อ Login ครั้งแรก เพื่อให้เข้าใช้งานได้ง่าย

หากสมาชิกเลือกเปลี่ยนรหัสผ่านเองภายหลัง ระบบยังรองรับรหัสผ่านส่วนตัวได้  
Admin ใหม่/Reset Password ใช้นโยบายรหัสผ่านอย่างน้อย 12 ตัวอักษร และมีตัวอักษรภาษาอังกฤษกับตัวเลข

## 10. Rate Limit

V6 สร้างตาราง `auth_attempts`

ค่าเริ่มต้น:
- บัญชี + IP: 5 ครั้ง / 15 นาที
- IP รวม: 30 ครั้ง / 15 นาที

## 11. Upload Security

ตรวจว่า Web Server เขียน `uploads` ได้ แต่ URL `/php-project/uploads/...` ต้องตอบ 403  
ระบบอ่านไฟล์ผ่าน `secure_file.php` หลังตรวจสิทธิ์เท่านั้น  
ห้าม chmod 777 หากไม่จำเป็น

## 12. Security Test ก่อน Go-live

ทดสอบอย่างน้อย:
- SQL Injection ใน Login/Search/Form
- XSS ในชื่อ/ที่อยู่/ข้อมูล Master
- CSRF ในฟอร์ม Admin และ Member
- Login brute force
- Session cookie: Secure, HttpOnly, SameSite
- เปิด URL SQL/config แล้วต้องไม่ได้
- อัปโหลด .php/.phtml/.phar ต้องไม่ผ่าน
- เปลี่ยนนามสกุล PHP เป็น JPG ต้องไม่ผ่าน MIME/Image validation
- QR สมาชิกต้องใช้ token 32 ตัว ไม่ใช่เลขสมาชิก
- ผู้ใช้ A ต้องเปิดข้อมูล/บัตรของผู้ใช้ B ไม่ได้
- Admin ที่ถูกปิดใช้งานต้องเข้าไม่ได้
- Approved application ต้องแก้ข้อมูลหลักไม่ได้

## 13. Backup/Monitoring

- Backup DB รายวัน
- Backup uploads ตามนโยบายองค์กร
- เก็บ Web/PHP error log
- Monitor 4xx/5xx, login failure และพื้นที่ disk
- Patch Ubuntu/PHP/Nginx/MariaDB ตามรอบ
- ทบทวนบัญชี Admin เป็นระยะ

## 14. Secrets

หลัง Go-live:
- เปลี่ยน DB Password ที่เคยถูกใช้ระหว่าง Development
- ใช้ DB user เฉพาะระบบ
- ห้ามส่ง Password/Token ผ่าน Chat, Git หรือ Screenshot
