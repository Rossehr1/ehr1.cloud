-- EP PAID Data explorer: ordered column list (refreshed by tools/ep_paid_sync.py load).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ep_paid_column_manifest (
  ordinal INT UNSIGNED NOT NULL,
  header_name VARCHAR(768) NOT NULL,
  PRIMARY KEY (ordinal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='EP PAID explorer columns; DELETE+INSERT on each load';
