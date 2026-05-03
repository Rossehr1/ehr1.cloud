-- Optional: extra counters on ref_source_batch for import QA (§0.7 merge rules).
-- Safe to run once on existing DBs. If columns already exist, skip or ignore duplicate errors.

SET NAMES utf8mb4;

ALTER TABLE ref_source_batch
  ADD COLUMN row_count_skipped_invalid_npi INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Rows skipped: invalid or missing NPI'
    AFTER row_count_loaded,
  ADD COLUMN row_count_skipped_duplicate INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Rows skipped: exact duplicate within batch (in-loader dedupe)'
    AFTER row_count_skipped_invalid_npi;
