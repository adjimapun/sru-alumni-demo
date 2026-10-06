-- Migration V7: ยกเลิกการบังคับเปลี่ยนรหัสผ่านสมาชิก
-- ใช้สำหรับฐานข้อมูลที่เคยรัน migrate_v6.sql เวอร์ชันเดิมแล้ว
-- รันได้หลัง V6

UPDATE users
SET must_change_password = 0
WHERE must_change_password <> 0;

ALTER TABLE users
  MODIFY COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;
