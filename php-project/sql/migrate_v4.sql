-- Migration V4: ตั้งค่าผู้รับเงิน/ลายเซ็น และจัดการผู้ดูแลระบบหลังบ้าน
-- รันบน Database ของระบบที่เลือกอยู่เท่านั้น (ไม่มี USE แบบ hard-code)
-- รันไฟล์นี้ครั้งเดียว หลังจาก migrate_v3.sql
ALTER TABLE admins
  ADD COLUMN full_name VARCHAR(255) NULL AFTER id,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE INDEX idx_admin_active ON admins(is_active);

UPDATE admins
SET is_active=1
WHERE is_active IS NULL;

CREATE TABLE receipt_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  payee_name VARCHAR(255) NOT NULL,
  payee_position VARCHAR(255) NULL,
  signature_path VARCHAR(255) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_settings_admin
    FOREIGN KEY(updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO receipt_settings(
  id,
  payee_name,
  payee_position,
  signature_path
) VALUES (
  1,
  'ผู้รับเงิน',
  'สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี',
  NULL
);

-- หลังรัน Migration นี้:
-- 1) เมนูตั้งค่าใบเสร็จสามารถแก้ชื่อผู้รับเงิน ตำแหน่ง และอัปโหลดลายเซ็นได้
-- 2) สามารถเพิ่ม/ปิดใช้งานผู้ดูแลระบบหลังบ้าน และกำหนดรหัสผ่านใหม่ได้
-- 3) Admin เดิมทั้งหมดจะถูกตั้งค่าเป็นบัญชีที่เปิดใช้งาน
