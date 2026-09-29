# SRU Alumni Membership — PHP + MariaDB/MySQL

โปรเจกต์ PHP แบบไม่พึ่ง Framework สำหรับระบบรับสมัครสมาชิกสมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี

## ความสามารถ
- สมัครบัญชีด้วยเลขบัตรประชาชน 13 หลัก + เบอร์โทรศัพท์มือถือ
- Username = เลขบัตรประชาชน
- Password เริ่มต้น = เบอร์โทรศัพท์มือถือ (จัดเก็บด้วย password_hash ไม่เก็บเป็นข้อความ)
- ใบสมัครสมาชิกแบบหน้าเดียว
- ชำระเงิน/แนบสลิปอยู่ในใบสมัครเดียวกัน และกลับมาแนบภายหลังได้
- สถานะ รอชำระเงิน / รอตรวจสอบ / หลักฐานไม่ถูกต้อง / สมาชิกสมบูรณ์
- เจ้าหน้าที่ตรวจสลิปและอนุมัติ
- ออกเลขสมาชิก ALUMNI-xxxxxx อัตโนมัติ
- E-Receipt แบบพิมพ์/Save as PDF
- ประวัติการอบรมและพัฒนาศักยภาพ + แนบเกียรติบัตร
- ใช้ Font Kanit

## ติดตั้งบน XAMPP
1. คัดลอกโฟลเดอร์ php-project ไปไว้ที่ C:\xampp\htdocs\sru_alumni
2. เปิด Apache และ MySQL
3. เข้า phpMyAdmin และ Import ไฟล์ sql/sru_alumni.sql
4. หาก MySQL ใช้ user/password ต่างจาก root/ว่าง ให้แก้ config.php
5. เปิด http://localhost/sru_alumni/
6. สร้าง Admin ครั้งแรกที่ http://localhost/sru_alumni/create_admin.php
7. หลังสร้าง Admin แล้ว ให้ลบหรือเปลี่ยนชื่อ create_admin.php
8. หน้า Admin: http://localhost/sru_alumni/admin.php

## สิทธิ์โฟลเดอร์
Web server ต้องเขียนโฟลเดอร์ uploads/ ได้

Linux:
chmod 775 uploads

## Production
ควรเปิด HTTPS, ตั้งค่า DB user เฉพาะระบบ, ทำ Backup, จำกัดสิทธิ์ uploads, เพิ่ม OTP/Reset Password และพิจารณาเข้ารหัสข้อมูลส่วนบุคคลที่มีความอ่อนไหว

หมายเหตุ: ไม่รวม QR Payment จริง เนื่องจากต้องใช้ QR/PromptPay ที่สมาคมยืนยันอย่างเป็นทางการ
