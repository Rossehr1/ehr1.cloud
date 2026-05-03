-- Demo link: individual 1003000002 under org 1003000003 (requires seed NPIs present).
SET NAMES utf8mb4;

INSERT IGNORE INTO core_npi_relationship (parent_npi, child_npi, relationship_type, notes)
VALUES (
  '1003000003',
  '1003000002',
  'manual',
  'Demo: physician associated with Test Clinic LLC'
);
