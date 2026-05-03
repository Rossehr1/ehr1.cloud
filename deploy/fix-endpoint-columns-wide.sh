#!/bin/bash
set -euo pipefail
ROOTPW=$(clpctl db:show:master-credentials 2>/dev/null | awk -F'|' '$2 ~ /Password/ {gsub(/^[ \t]+|[ \t]+$/,"",$3); print $3; exit}')
mysql -h127.0.0.1 -uroot -p"${ROOTPW}" -e "
ALTER TABLE \`ehr1-data\`.core_npi_endpoint
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
TRUNCATE TABLE \`ehr1-data\`.core_npi_endpoint;
"
echo OK
