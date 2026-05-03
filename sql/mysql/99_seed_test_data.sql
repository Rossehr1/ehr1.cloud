-- Small synthetic test rows (not real providers). Apply after 00–05 DDL.
-- mysql -u user -p ehr1 < 99_seed_test_data.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE core_npi_relationship;
TRUNCATE TABLE supplemental_dncs_ndfile;
TRUNCATE TABLE supplemental_ep_paid;
TRUNCATE TABLE core_npi_other_name;
TRUNCATE TABLE core_npi_practice_location;
TRUNCATE TABLE core_npi_endpoint;
TRUNCATE TABLE core_npi_provider;
TRUNCATE TABLE ref_source_batch;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO ref_source_batch (source_key, file_name, file_effective_date, row_count_loaded, notes)
VALUES
  ('npidata', 'npidata_seed_test.csv', '2026-03-08', 3, 'Synthetic test batch'),
  ('endpoint', 'endpoint_seed_test.csv', '2026-03-08', 2, 'Synthetic test batch'),
  ('pl', 'pl_seed_test.csv', '2026-03-08', 2, 'Synthetic test batch'),
  ('othername', 'othername_seed_test.csv', '2026-03-08', 1, 'Synthetic test batch');

SET @b_npi = (SELECT batch_id FROM ref_source_batch WHERE source_key = 'npidata' LIMIT 1);
SET @b_ep = (SELECT batch_id FROM ref_source_batch WHERE source_key = 'endpoint' LIMIT 1);
SET @b_pl = (SELECT batch_id FROM ref_source_batch WHERE source_key = 'pl' LIMIT 1);
SET @b_on = (SELECT batch_id FROM ref_source_batch WHERE source_key = 'othername' LIMIT 1);

INSERT INTO core_npi_provider (
  npi, entity_type_code, provider_organization_name, provider_last_name, provider_first_name,
  mailing_city, mailing_state, mailing_postal_code,
  practice_city, practice_state, practice_postal_code,
  healthcare_provider_taxonomy_code_1, source_batch_id
) VALUES
  ('1003000001', 1, 'Test General Hospital', NULL, NULL,
   'Indianapolis', 'IN', '462041234',
   'Indianapolis', 'IN', '462041234',
   '282N00000X', @b_npi),
  ('1003000002', 1, NULL, 'Doe', 'Jane',
   'Chicago', 'IL', '606011234',
   'Chicago', 'IL', '606011234',
   '207Q00000X', @b_npi),
  ('1003000003', 2, 'Test Clinic LLC', NULL, NULL,
   'Phoenix', 'AZ', '850012345',
   'Phoenix', 'AZ', '850012345',
   '261QX0200X', @b_npi);

INSERT INTO core_npi_endpoint (
  npi, endpoint_type, endpoint_type_desc, endpoint_url, source_batch_id
) VALUES
  ('1003000001', 'DIRECT', 'Direct Messaging Address', 'direct@example.org', @b_ep),
  ('1003000002', 'URL', 'Practice website', 'https://example.org/janedoe', @b_ep);

INSERT INTO core_npi_practice_location (
  npi, pl_address_line1, pl_city, pl_state, pl_postal_code, source_batch_id
) VALUES
  ('1003000001', '200 Secondary Ave', 'Indianapolis', 'IN', '46205', @b_pl),
  ('1003000003', '400 West Rd Ste 10', 'Phoenix', 'AZ', '85008', @b_pl);

INSERT INTO core_npi_other_name (
  npi, provider_other_organization_name, provider_other_organization_name_type_code, source_batch_id
) VALUES
  ('1003000003', 'Test Clinic Alternate Name', '3', @b_on);

INSERT INTO supplemental_ep_paid (npi, payload_json, source_batch_id)
VALUES ('1003000002', JSON_OBJECT('note', 'placeholder — load EP PAID xlsx with tools/ep_paid_sync.py'), @b_npi);

INSERT INTO supplemental_dncs_ndfile (npi, payload_json, source_batch_id)
VALUES ('1003000001', JSON_OBJECT('note', 'placeholder — profile DNCS export'), @b_npi);
