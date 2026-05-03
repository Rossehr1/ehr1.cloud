-- Placeholders for non-NPPES sources (expand columns when you profile each file).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS supplemental_ep_paid (
  ep_paid_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  npi             CHAR(10) NULL,
  raw_note        VARCHAR(500) NULL COMMENT 'Deprecated; use payload_json',
  payload_json    JSON NULL COMMENT 'EP PAID xlsx row as object keyed by column header',
  source_batch_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (ep_paid_id),
  UNIQUE KEY uk_supp_ep_paid_npi (npi),
  CONSTRAINT fk_ep_paid_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='EP PAID Complete xlsx — merged by NPI';

CREATE TABLE IF NOT EXISTS supplemental_dncs_ndfile (
  ndfile_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  npi             CHAR(10) NULL,
  payload_json    JSON NULL COMMENT 'Or normalize into columns after profiling DNCS export',
  source_batch_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (ndfile_id),
  KEY idx_dncs_npi (npi),
  CONSTRAINT fk_dncs_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='ndfiles-from-dncs-data-section — schema TBD';
