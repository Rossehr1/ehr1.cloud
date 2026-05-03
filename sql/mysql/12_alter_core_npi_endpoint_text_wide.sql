-- Widen core_npi_endpoint text columns (long URLs / descriptions from NPPES).
-- Apply on DBs created before 2026-05 if load_master_dataset failed on endpoint rows.
SET NAMES utf8mb4;
ALTER TABLE core_npi_endpoint
  MODIFY COLUMN endpoint_type VARCHAR(200) NULL,
  MODIFY COLUMN endpoint_type_desc TEXT NULL,
  MODIFY COLUMN endpoint_url TEXT NULL,
  MODIFY COLUMN affiliation VARCHAR(10) NULL,
  MODIFY COLUMN endpoint_description TEXT NULL,
  MODIFY COLUMN affiliation_legal_business_name TEXT NULL,
  MODIFY COLUMN use_code VARCHAR(80) NULL,
  MODIFY COLUMN use_description TEXT NULL,
  MODIFY COLUMN content_type VARCHAR(200) NULL,
  MODIFY COLUMN content_description TEXT NULL;
