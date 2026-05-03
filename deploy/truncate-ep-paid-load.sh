#!/bin/bash
set -euo pipefail
ROOTPW=$(clpctl db:show:master-credentials 2>/dev/null | awk -F'|' '$2 ~ /Password/ {gsub(/^[ \t]+|[ \t]+$/,"",$3); print $3; exit}')
mysql -h127.0.0.1 -uroot -p"${ROOTPW}" -e "
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM \`ehr1-data\`.archive_supplemental_row WHERE source_batch_id IN (
  SELECT batch_id FROM (SELECT batch_id FROM \`ehr1-data\`.ref_source_batch WHERE source_key='ep_paid') t
);
DELETE FROM \`ehr1-data\`.supplemental_ep_paid;
DELETE FROM \`ehr1-data\`.merged_ep_paid_npi;
DELETE FROM \`ehr1-data\`.ref_source_batch WHERE source_key='ep_paid';
SET FOREIGN_KEY_CHECKS=1;
"
echo OK
