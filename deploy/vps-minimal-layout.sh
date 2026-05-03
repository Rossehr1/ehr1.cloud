#!/usr/bin/env bash
# Safe on CloudPanel: create docroot only; no apt; does not replace MySQL/Percona.
# On the VPS:  bash /path/to/vps-minimal-layout.sh
set -euo pipefail
DOCROOT="/var/www/ehr1.cloud/public_html"
mkdir -p "${DOCROOT}/ehr1-data"
if id www-data &>/dev/null; then
  chown -R www-data:www-data /var/www/ehr1.cloud
elif id nginx &>/dev/null; then
  chown -R nginx:nginx /var/www/ehr1.cloud
else
  chmod -R a+rX /var/www/ehr1.cloud
fi
if command -v mysql >/dev/null 2>&1; then
  mysql -e "CREATE DATABASE IF NOT EXISTS ehr1_data CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || true
fi
echo "OK: ${DOCROOT}/ehr1-data — point ehr1.cloud vhost (CloudPanel or nginx) at ${DOCROOT}"
