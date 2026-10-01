# SRU Alumni Membership — PHP + MariaDB/MySQL

ระบบรับสมัครสมาชิกสมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี

## ความสามารถล่าสุด
- สมัครบัญชีด้วยเลขบัตรประชาชน 13 หลัก + เบอร์โทรศัพท์มือถือ
- Username = เลขบัตรประชาชน
- Password เริ่มต้น = เบอร์โทรศัพท์มือถือ และจัดเก็บด้วย password_hash()
- ใบสมัครสมาชิกแบบหน้าเดียว
- เพิ่มคำนำหน้าชื่อ
- ตัดช่อง "รุ่น" ออกจากแบบฟอร์ม
- คณะเป็น Combo box จากตาราง faculties
- Admin เพิ่ม/เปิด/ปิดข้อมูลคณะได้จาก admin_master.php
- แยกช่อง "สาขา" ออกจากคณะ
- ประเภทสมาชิกเป็น Master Data และ Admin เพิ่ม/เปิด/ปิดได้
- สมาชิกประเภทอื่น ๆ สามารถระบุรายละเอียดเพิ่มเติมได้
- ช่องทางชำระเงินกำหนดตายตัวเป็น "โอนผ่านบัญชีธนาคาร" ทั้งหน้าแบบฟอร์มและฝั่ง Server
- แนบสลิป JPG/PNG/PDF
- เจ้าหน้าที่ตรวจสลิปและอนุมัติ
- เลขสมาชิก ALUMNI-xxxxxx ออกตามลำดับการอนุมัติการชำระเงิน
- เมื่ออนุมัติ ระบบออกใบเสร็จอิเล็กทรอนิกส์อัตโนมัติ
- ใบเสร็จมี QR Code สำหรับตรวจสอบความถูกต้อง
- ผู้ใช้ดาวน์โหลดใบเสร็จเป็น PNG ได้ และ Print/Save as PDF ได้
- ประวัติการอบรมและพัฒนาศักยภาพ + แนบเกียรติบัตร
- ใช้ Font Kanit
- PDO Prepared Statements + CSRF + password_hash/password_verify

## ติดตั้งฐานข้อมูลใหม่
1. คัดลอกโฟลเดอร์ php-project ไปไว้ที่ C:\xampp\htdocs\sru_alumni
2. เปิด Apache และ MySQL
3. Import ไฟล์ sql/sru_alumni.sql ผ่าน phpMyAdmin
4. แก้ config.php หาก DB Username/Password แตกต่างจากค่าเริ่มต้น
5. เปิด http://localhost/sru_alumni/
6. สร้าง Admin ครั้งแรกที่ http://localhost/sru_alumni/create_admin.php
7. หลังสร้าง Admin ให้ลบหรือเปลี่ยนชื่อ create_admin.php
8. เข้า Admin ที่ http://localhost/sru_alumni/admin.php
9. เพิ่มข้อมูลคณะได้ที่ http://localhost/sru_alumni/admin_master.php

## กรณีมีฐานข้อมูลเวอร์ชันเดิม
ให้ Backup ฐานข้อมูลก่อน แล้ว Import:
sql/migrate_v2.sql

Migration จะเพิ่มตาราง faculties, member_types และคอลัมน์ใหม่ โดยเก็บคอลัมน์เก่า generation / faculty_major / member_type ไว้เพื่อไม่ทำลายข้อมูลย้อนหลัง แต่โค้ดใหม่จะไม่ใช้งานคอลัมน์ดังกล่าว

## การดาวน์โหลดใบเสร็จเป็นรูปภาพ
receipt.php ใช้ html2canvas ผ่าน CDN เพื่อสร้าง PNG ใน Browser และใช้ QRCode.js เพื่อสร้าง QR ตรวจสอบใบเสร็จ

หากระบบ Production ต้องทำงานในเครือข่ายปิด แนะนำดาวน์โหลด JavaScript libraries เหล่านี้มาเก็บไว้ใน Server เองแทน CDN

## Production
ควรเปิด HTTPS, ใช้ DB User เฉพาะระบบ, ทำ Backup, จำกัดสิทธิ์โฟลเดอร์ uploads, เปิด Secure/HttpOnly/SameSite Cookie, เพิ่ม Rate Limit/OTP/Reset Password และจัดทำนโยบาย Retention/Access Control ให้สอดคล้องกับ PDPA
