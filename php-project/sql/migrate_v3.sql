-- Migration V3: รองรับอนุมัติหลายรายการและยกเลิกการอนุมัติสมาชิก
-- รันไฟล์นี้ครั้งเดียว หลังจาก migrate_v2.sql
USE sru_alumni;

ALTER TABLE members
  ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER member_no,
  ADD COLUMN cancelled_at DATETIME NULL AFTER approved_at,
  ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at;

ALTER TABLE members
  ADD CONSTRAINT fk_member_cancel_admin
  FOREIGN KEY(cancelled_by) REFERENCES admins(id) ON DELETE SET NULL;

CREATE INDEX idx_member_status ON members(status);

ALTER TABLE receipts
  ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER verification_code,
  ADD COLUMN cancelled_at DATETIME NULL AFTER status;

CREATE INDEX idx_receipt_status ON receipts(status);

UPDATE members SET status='active' WHERE status IS NULL OR status='';
UPDATE receipts SET status='active' WHERE status IS NULL OR status='';

-- พฤติกรรมหลัง Migration:
-- 1) สมาชิกที่อนุมัติอยู่เดิมถือเป็น active
-- 2) เมื่อ Admin ยกเลิกการอนุมัติ ระบบจะเปลี่ยน members.status เป็น cancelled
-- 3) ใบเสร็จที่เกี่ยวข้องจะถูกเปลี่ยนเป็น cancelled โดยไม่ลบประวัติ
-- 4) ใบสมัครจะกลับไปสถานะ payment_review เพื่อให้ตรวจสอบ/อนุมัติใหม่ได้
