-- Links practice/group NPI (parent) to individual provider NPIs (children).
-- NPI is always the key; relationships are explicit (manual/import) or inferred later.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS core_npi_relationship (
  relationship_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_npi        CHAR(10) NOT NULL COMMENT 'Practice or group NPI (typically org entity)',
  child_npi         CHAR(10) NOT NULL COMMENT 'Individual provider NPI',
  relationship_type ENUM('manual','imported','cms_inferred','same_practice_address') NOT NULL DEFAULT 'manual',
  notes             VARCHAR(512) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (relationship_id),
  UNIQUE KEY uq_parent_child (parent_npi, child_npi),
  KEY idx_rel_parent (parent_npi),
  KEY idx_rel_child (child_npi),
  CONSTRAINT fk_rel_parent_npi FOREIGN KEY (parent_npi) REFERENCES core_npi_provider (npi)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rel_child_npi FOREIGN KEY (child_npi) REFERENCES core_npi_provider (npi)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Practice/group NPI to individual NPI membership';
