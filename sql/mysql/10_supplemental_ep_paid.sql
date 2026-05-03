-- EP PAID workbook (supplemental payload per NPI; does not alter core_npi_provider).
-- Loader: tools/load_ep_paid.py — NPI gate + archive_supplemental_row for rejects (§0).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS supplemental_ep_paid (
  ep_paid_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  npi             CHAR(10) NULL COMMENT 'Must exist in core_npi_provider when stored active',
  payload_json    JSON NULL COMMENT 'Non-blank cells from source row; keys disambiguated if duplicate headers',
  source_batch_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (ep_paid_id),
  KEY idx_ep_paid_npi (npi),
  CONSTRAINT fk_ep_paid_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='EP PAID workbook — load staging table; becomes master-class after documented merge (EHR1-Full-Data.md)';
