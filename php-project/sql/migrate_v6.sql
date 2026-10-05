-- Migration V6: Production Security Hardening
-- รันไฟล์นี้ครั้งเดียว หลัง migrate_v5.sql

ALTER TABLE users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash;

ALTER TABLE members
  ADD COLUMN verification_code CHAR(32) NULL AFTER member_no;

UPDATE members
SET verification_code = LOWER(REPLACE(UUID(),'-',''))
WHERE verification_code IS NULL OR verification_code='';

ALTER TABLE members
  MODIFY COLUMN verification_code CHAR(32) NOT NULL;

CREATE UNIQUE INDEX idx_member_verification_code
  ON members(verification_code);

CREATE TABLE auth_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(30) NOT NULL,
  identity_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  was_success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auth_identity(scope,identity_hash,ip_hash,attempted_at),
  INDEX idx_auth_ip(scope,ip_hash,attempted_at),
  INDEX idx_auth_time(attempted_at)
) ENGINE=InnoDB;
