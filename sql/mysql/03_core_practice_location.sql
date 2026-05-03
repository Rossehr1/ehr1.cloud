-- NPPES pl_pfile: secondary practice locations (CMS column names are long; shortened here).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS core_npi_practice_location (
  practice_location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  npi                  CHAR(10) NOT NULL,
  pl_address_line1     VARCHAR(200) NULL,
  pl_address_line2     VARCHAR(200) NULL,
  pl_city              VARCHAR(80) NULL,
  pl_state             VARCHAR(40) NULL,
  pl_postal_code       VARCHAR(20) NULL,
  pl_country           VARCHAR(10) NULL,
  pl_phone             VARCHAR(40) NULL,
  pl_phone_extension   VARCHAR(20) NULL,
  pl_fax               VARCHAR(40) NULL,
  source_batch_id      BIGINT UNSIGNED NULL,
  PRIMARY KEY (practice_location_id),
  KEY idx_pl_npi (npi),
  KEY idx_pl_state_city (pl_state, pl_city),
  CONSTRAINT fk_pl_npi FOREIGN KEY (npi) REFERENCES core_npi_provider (npi)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pl_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
