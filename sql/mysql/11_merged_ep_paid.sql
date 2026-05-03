-- One row per NPI: latest EP PAID supplemental row (MAX(ep_paid_id)) wins.
-- Rebuilt by tools/merge_ep_paid_to_npi.py (and after each load_ep_paid.py run).
-- Does not alter core_npi_provider; joins for master-class reporting (EHR1-Full-Data.md).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS merged_ep_paid_npi (
  npi                CHAR(10) NOT NULL COMMENT 'Aligns with core_npi_provider.npi',
  payload_json       JSON NOT NULL COMMENT 'JSON from winning supplemental_ep_paid row',
  source_ep_paid_id  BIGINT UNSIGNED NOT NULL COMMENT 'supplemental_ep_paid.ep_paid_id',
  source_batch_id    BIGINT UNSIGNED NULL,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (npi),
  KEY idx_merged_ep_paid_batch (source_batch_id),
  CONSTRAINT fk_merged_ep_paid_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='EP PAID merged to master by NPI (latest supplemental row per NPI)';
