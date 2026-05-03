#!/bin/bash
ROOTPW=$(clpctl db:show:master-credentials 2>/dev/null | awk -F'|' '$2 ~ /Password/ {gsub(/^[ \t]+|[ \t]+$/,"",$3); print $3; exit}')
mysql -h127.0.0.1 -uroot -p"${ROOTPW}" -e "
SELECT 'core_npi_provider' AS tbl, COUNT(*) AS n FROM \`ehr1-data\`.core_npi_provider
UNION ALL SELECT 'core_npi_endpoint', COUNT(*) FROM \`ehr1-data\`.core_npi_endpoint
UNION ALL SELECT 'core_npi_practice_location', COUNT(*) FROM \`ehr1-data\`.core_npi_practice_location
UNION ALL SELECT 'core_npi_other_name', COUNT(*) FROM \`ehr1-data\`.core_npi_other_name
UNION ALL SELECT 'supplemental_dncs_ndfile', COUNT(*) FROM \`ehr1-data\`.supplemental_dncs_ndfile
UNION ALL SELECT 'supplemental_ep_paid', COUNT(*) FROM \`ehr1-data\`.supplemental_ep_paid
UNION ALL SELECT 'merged_ep_paid_npi', COUNT(*) FROM \`ehr1-data\`.merged_ep_paid_npi;
"
