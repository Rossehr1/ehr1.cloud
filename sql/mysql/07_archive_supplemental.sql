-- Off-master supplemental rows: always persisted when NPI gate fails (§0 in EHR1-Full-Data.md).
-- Not part of the active dataset; routine reports must not JOIN this table.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS archive_supplemental_row (
  archive_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_batch_id    BIGINT UNSIGNED NULL COMMENT 'Import batch if known',
  source_file_name   VARCHAR(255) NULL COMMENT 'Redundant copy when batch unavailable',
  npi_raw            VARCHAR(32) NULL COMMENT 'NPI as supplied (may be invalid or wrong length)',
  reject_reason      VARCHAR(64) NOT NULL COMMENT 'e.g. NPI_MISSING, NPI_NOT_IN_MASTER, INVALID_NPI_FORMAT',
  reject_detail      VARCHAR(512) NULL,
  source_line_number INT UNSIGNED NULL COMMENT '1-based row in source file when applicable',
  payload_json       JSON NOT NULL COMMENT 'Full source row or normalized field map',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (archive_id),
  KEY idx_archive_batch (source_batch_id),
  KEY idx_archive_reason (reject_reason),
  KEY idx_archive_npi_raw (npi_raw),
  KEY idx_archive_created (created_at),
  CONSTRAINT fk_archive_supplemental_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Supplemental imports that failed master NPI gate; excluded from active queries';
