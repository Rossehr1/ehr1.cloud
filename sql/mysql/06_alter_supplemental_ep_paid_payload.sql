-- Run on existing DBs created before payload_json (safe to run once).
SET NAMES utf8mb4;

SET @db := DATABASE();

-- Add payload_json if missing
SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'supplemental_ep_paid' AND COLUMN_NAME = 'payload_json') > 0,
    'SELECT 1',
    'ALTER TABLE supplemental_ep_paid ADD COLUMN payload_json JSON NULL COMMENT ''EP PAID xlsx row as object keyed by column header'' AFTER raw_note'
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Unique NPI (allows multiple NULL npi in MySQL; one row per non-null NPI)
SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'supplemental_ep_paid' AND INDEX_NAME = 'uk_supp_ep_paid_npi') > 0,
    'SELECT 1',
    'ALTER TABLE supplemental_ep_paid ADD UNIQUE KEY uk_supp_ep_paid_npi (npi)'
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
