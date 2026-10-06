-- Migration V5: บัตรสมาชิกดิจิทัล + รหัสสมาชิกแบบ ปีปฏิทิน พ.ศ. 2 หลัก + running number 4 หลัก
-- ตัวอย่าง พ.ศ. 2569 + running 0001 = 690001
-- รันไฟล์นี้ครั้งเดียว หลัง migrate_v4.sql

ALTER TABLE applications
  ADD COLUMN member_photo_path VARCHAR(255) NULL AFTER member_type_other;

CREATE TABLE member_number_sequences (
  year2 CHAR(2) PRIMARY KEY,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE member_card_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  president_name VARCHAR(255) NULL,
  president_position VARCHAR(255) NOT NULL DEFAULT 'นายกสมาคมศิษย์เก่า มรส.',
  president_signature_path VARCHAR(255) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_member_card_settings_admin
    FOREIGN KEY(updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO member_card_settings(
  id,
  president_name,
  president_position,
  president_signature_path
) VALUES (
  1,
  NULL,
  'นายกสมาคมศิษย์เก่า มรส.',
  NULL
);

-- ตั้งค่า running เริ่มต้นจากรหัสสมาชิกแบบใหม่ที่มีอยู่แล้ว (ถ้ามี)
INSERT INTO member_number_sequences(year2,last_number)
SELECT
  LEFT(member_no,2) AS year2,
  MAX(CAST(RIGHT(member_no,4) AS UNSIGNED)) AS last_number
FROM members
WHERE member_no REGEXP '^[0-9]{6}$'
GROUP BY LEFT(member_no,2)
ON DUPLICATE KEY UPDATE
  last_number=GREATEST(member_number_sequences.last_number,VALUES(last_number));
