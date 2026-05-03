-- NPPES othername_pfile.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS core_npi_other_name (
  other_name_id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  npi                            CHAR(10) NOT NULL,
  provider_other_organization_name VARCHAR(200) NULL,
  provider_other_organization_name_type_code VARCHAR(10) NULL,
  source_batch_id                BIGINT UNSIGNED NULL,
  PRIMARY KEY (other_name_id),
  KEY idx_other_name_npi (npi),
  CONSTRAINT fk_other_name_npi FOREIGN KEY (npi) REFERENCES core_npi_provider (npi)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_other_name_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
