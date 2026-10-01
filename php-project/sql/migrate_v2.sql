-- Migration สำหรับฐานข้อมูลเดิมของ SRU Alumni
-- รันไฟล์นี้ครั้งเดียวบนฐานข้อมูลที่สร้างจากเวอร์ชันก่อนหน้า
USE sru_alumni;

CREATE TABLE IF NOT EXISTS faculties (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  allow_other_text TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO member_types(id,name,allow_other_text,is_active) VALUES
(1,'สมาชิกสามัญ',0,1),
(2,'สมาชิกกิตติมศักดิ์',0,1),
(3,'สมาชิกประเภทอื่น ๆ',1,1);

ALTER TABLE applications
  ADD COLUMN title_prefix VARCHAR(100) NULL AFTER application_no,
  ADD COLUMN faculty_id BIGINT UNSIGNED NULL AFTER entry_year,
  ADD COLUMN major VARCHAR(255) NULL AFTER faculty_id,
  ADD COLUMN member_type_id BIGINT UNSIGNED NULL AFTER occupation,
  ADD COLUMN member_type_other VARCHAR(255) NULL AFTER member_type_id;

UPDATE applications a
JOIN member_types mt ON mt.name = a.member_type
SET a.member_type_id = mt.id
WHERE a.member_type_id IS NULL;

ALTER TABLE applications
  ADD CONSTRAINT fk_app_faculty FOREIGN KEY(faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_app_member_type FOREIGN KEY(member_type_id) REFERENCES member_types(id) ON DELETE RESTRICT;

CREATE INDEX idx_app_faculty ON applications(faculty_id);
CREATE INDEX idx_app_member_type ON applications(member_type_id);

ALTER TABLE payments
  MODIFY channel VARCHAR(100) NOT NULL DEFAULT 'โอนผ่านบัญชีธนาคาร';

ALTER TABLE members
  ADD COLUMN approved_payment_id BIGINT UNSIGNED NULL AFTER user_id;

UPDATE members m
JOIN receipts r ON r.member_id = m.id
SET m.approved_payment_id = r.payment_id
WHERE m.approved_payment_id IS NULL;

ALTER TABLE members
  ADD CONSTRAINT fk_member_payment FOREIGN KEY(approved_payment_id) REFERENCES payments(id) ON DELETE RESTRICT;

CREATE UNIQUE INDEX uq_member_approved_payment ON members(approved_payment_id);

ALTER TABLE receipts
  ADD COLUMN verification_code CHAR(32) NULL AFTER amount;

UPDATE receipts
SET verification_code = LOWER(MD5(CONCAT(id,'-',receipt_no,'-',created_at)))
WHERE verification_code IS NULL;

CREATE UNIQUE INDEX uq_receipt_verification_code ON receipts(verification_code);

-- หมายเหตุ:
-- generation, faculty_major และ member_type เดิมยังคงไว้เพื่อไม่ทำลายข้อมูลเก่า
-- โค้ดเวอร์ชันใหม่จะไม่ใช้คอลัมน์เหล่านี้
