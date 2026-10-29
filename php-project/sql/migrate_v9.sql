-- Migration V9: application-number sequence support.
-- Run once after migrate_v8.sql on an existing database.

CREATE TABLE application_number_sequences (
  year4 CHAR(4) PRIMARY KEY,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO application_number_sequences(year4,last_number)
SELECT
  SUBSTRING(application_no,5,4),
  0
FROM applications
WHERE application_no REGEXP '^APP-[0-9]{4}-[0-9]{5}$'
GROUP BY SUBSTRING(application_no,5,4);
