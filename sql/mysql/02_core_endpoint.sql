-- NPPES endpoint file (one row per endpoint line; multiple per NPI allowed).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS core_npi_endpoint (
  endpoint_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  npi                 CHAR(10) NOT NULL,
  endpoint_type       VARCHAR(200) NULL,
  endpoint_type_desc  TEXT NULL,
  endpoint_url        TEXT NULL,
  affiliation         VARCHAR(10) NULL,
  endpoint_description TEXT NULL,
  affiliation_legal_business_name TEXT NULL,
  use_code            VARCHAR(80) NULL,
  use_description     TEXT NULL,
  content_type        VARCHAR(200) NULL,
  content_description TEXT NULL,
  source_batch_id     BIGINT UNSIGNED NULL,
  PRIMARY KEY (endpoint_id),
  KEY idx_endpoint_npi (npi),
  CONSTRAINT fk_endpoint_npi FOREIGN KEY (npi) REFERENCES core_npi_provider (npi)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_endpoint_batch FOREIGN KEY (source_batch_id) REFERENCES ref_source_batch (batch_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
