-- Migration V8: ปรับรหัสสมาชิกเดิมเป็นรูปแบบ 6 หลัก
-- หลักการ:
--   2 หลักแรก = ปีปฏิทิน พ.ศ. ของวันที่อนุมัติสมาชิก เช่น 2569 => 69
--   4 หลักถัดมา = running number ภายในปี เช่น 0001
-- ตัวอย่าง: 690001
--
-- รันไฟล์นี้ครั้งเดียว หลัง migrate_v7.sql
-- ระบบจะเปลี่ยนเฉพาะรหัสเดิมรูปแบบ ALUMNI-xxxxxx
-- และจะต่อ running จากรหัส 6 หลักที่มีอยู่แล้วในแต่ละปี เพื่อป้องกันเลขซ้ำ

-- เตรียม running ปัจจุบันจากรหัสสมาชิก 6 หลักที่มีอยู่แล้ว
INSERT INTO member_number_sequences(year2,last_number)
SELECT
  LEFT(member_no,2) AS year2,
  MAX(CAST(RIGHT(member_no,4) AS UNSIGNED)) AS last_number
FROM members
WHERE member_no REGEXP '^[0-9]{6}$'
GROUP BY LEFT(member_no,2)
ON DUPLICATE KEY UPDATE
  last_number=GREATEST(member_number_sequences.last_number,VALUES(last_number));

DELIMITER //

CREATE PROCEDURE migrate_legacy_member_numbers_v8()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_id BIGINT UNSIGNED;
    DECLARE v_year2 CHAR(2);
    DECLARE v_next INT UNSIGNED;

    DECLARE cur CURSOR FOR
        SELECT
            id,
            RIGHT(
                CAST(
                    YEAR(COALESCE(approved_at, CURRENT_TIMESTAMP)) + 543
                    AS CHAR
                ),
                2
            ) AS year2
        FROM members
        WHERE member_no REGEXP '^ALUMNI-[0-9]{6}$'
        ORDER BY
            YEAR(COALESCE(approved_at, CURRENT_TIMESTAMP)),
            approved_at,
            id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_id, v_year2;

        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        INSERT INTO member_number_sequences(year2,last_number)
        VALUES(v_year2,0)
        ON DUPLICATE KEY UPDATE
          last_number=member_number_sequences.last_number;

        SELECT last_number
        INTO v_next
        FROM member_number_sequences
        WHERE year2=v_year2;

        SET v_next = v_next + 1;

        IF v_next > 9999 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT='รหัสสมาชิกประจำปีครบ 9,999 รายการแล้ว';
        END IF;

        UPDATE member_number_sequences
        SET last_number=v_next
        WHERE year2=v_year2;

        UPDATE members
        SET member_no=CONCAT(
            v_year2,
            LPAD(CAST(v_next AS CHAR),4,'0')
        )
        WHERE id=v_id;
    END LOOP;

    CLOSE cur;
END//

DELIMITER ;

START TRANSACTION;
CALL migrate_legacy_member_numbers_v8();
COMMIT;

DROP PROCEDURE migrate_legacy_member_numbers_v8;

-- Sync running อีกครั้งหลังแปลงข้อมูลเดิม
INSERT INTO member_number_sequences(year2,last_number)
SELECT
  LEFT(member_no,2) AS year2,
  MAX(CAST(RIGHT(member_no,4) AS UNSIGNED)) AS last_number
FROM members
WHERE member_no REGEXP '^[0-9]{6}$'
GROUP BY LEFT(member_no,2)
ON DUPLICATE KEY UPDATE
  last_number=GREATEST(member_number_sequences.last_number,VALUES(last_number));
