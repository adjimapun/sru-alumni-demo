CREATE DATABASE IF NOT EXISTS sru_alumni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sru_alumni;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  citizen_hash CHAR(64) NOT NULL UNIQUE,
  citizen_last4 CHAR(4) NOT NULL,
  phone VARCHAR(20) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE faculties (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE member_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  allow_other_text TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO member_types(id,name,allow_other_text,is_active) VALUES
(1,'สมาชิกสามัญ',0,1),
(2,'สมาชิกกิตติมศักดิ์',0,1),
(3,'สมาชิกประเภทอื่น ๆ',1,1);

CREATE TABLE applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  application_no VARCHAR(30) UNIQUE NULL,
  title_prefix VARCHAR(100) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  nickname VARCHAR(100) NULL,
  gender VARCHAR(40) NULL,
  birth_date DATE NULL,
  student_code VARCHAR(50) NULL,
  entry_year VARCHAR(10) NULL,
  faculty_id BIGINT UNSIGNED NULL,
  major VARCHAR(255) NULL,
  degree VARCHAR(100) NULL,
  grad_year VARCHAR(10) NULL,
  address TEXT NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(255) NULL,
  line_id VARCHAR(100) NULL,
  workplace VARCHAR(255) NULL,
  position VARCHAR(255) NULL,
  occupation VARCHAR(255) NULL,
  member_type_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
  member_type_other VARCHAR(255) NULL,
  member_photo_path VARCHAR(255) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending_payment',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_faculty FOREIGN KEY(faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
  CONSTRAINT fk_app_member_type FOREIGN KEY(member_type_id) REFERENCES member_types(id) ON DELETE RESTRICT,
  INDEX idx_app_faculty(faculty_id),
  INDEX idx_app_member_type(member_type_id),
  INDEX idx_app_status(status)
) ENGINE=InnoDB;

CREATE TABLE admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admin_active(is_active)
) ENGINE=InnoDB;

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

INSERT INTO receipt_settings(id,payee_name,payee_position,signature_path)
VALUES(
  1,
  'ผู้รับเงิน',
  'สมาคมศิษย์เก่ามหาวิทยาลัยราชภัฏสุราษฎร์ธานี',
  NULL
);


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
  id,president_name,president_position,president_signature_path
) VALUES (
  1,NULL,'นายกสมาคมศิษย์เก่า มรส.',NULL
);

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  paid_date DATE NULL,
  paid_time TIME NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  channel VARCHAR(100) NOT NULL DEFAULT 'โอนผ่านบัญชีธนาคาร',
  slip_path VARCHAR(255) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  note VARCHAR(500) NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_app FOREIGN KEY(application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_admin FOREIGN KEY(reviewer_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_pay_app(application_id),
  INDEX idx_pay_status(status)
) ENGINE=InnoDB;

CREATE TABLE members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  approved_payment_id BIGINT UNSIGNED NOT NULL UNIQUE,
  member_no VARCHAR(30) NOT NULL UNIQUE,
  verification_code CHAR(32) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  CONSTRAINT fk_member_app FOREIGN KEY(application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_payment FOREIGN KEY(approved_payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
  CONSTRAINT fk_member_cancel_admin FOREIGN KEY(cancelled_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_member_status(status)
) ENGINE=InnoDB;

CREATE TABLE receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL UNIQUE,
  receipt_no VARCHAR(40) NOT NULL UNIQUE,
  receipt_date DATE NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  verification_code CHAR(32) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  cancelled_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_payment FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  INDEX idx_receipt_verify(verification_code),
  INDEX idx_receipt_status(status)
) ENGINE=InnoDB;

CREATE TABLE trainings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  activity_date DATE NULL,
  organizer VARCHAR(255) NULL,
  location VARCHAR(255) NULL,
  hours DECIMAL(6,2) NULL,
  certificate_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_training_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_training_member(member_id),
  INDEX idx_training_date(activity_date)
) ENGINE=InnoDB;


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
