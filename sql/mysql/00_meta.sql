-- EHR1: batch tracking for loads (MySQL / MariaDB, utf8mb4).
-- Apply after creating database: CREATE DATABASE ehr1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ref_source_batch (
  batch_id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_key          VARCHAR(64) NOT NULL COMMENT 'e.g. npidata, endpoint, pl, othername, ep_paid, dncs',
  file_name           VARCHAR(255) NOT NULL,
  file_effective_date DATE NULL COMMENT 'From CMS filename or manifest',
  loaded_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  row_count_expected  INT UNSIGNED NULL,
  row_count_loaded    INT UNSIGNED NULL,
  notes               VARCHAR(512) NULL,
  PRIMARY KEY (batch_id),
  KEY idx_ref_source_batch_source (source_key),
  KEY idx_ref_source_batch_loaded (loaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One row per import batch; ties staging/core rows to a file version';
