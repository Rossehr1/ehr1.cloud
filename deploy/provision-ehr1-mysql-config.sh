#!/bin/bash
# One-time: parse CloudPanel MySQL root from clpctl, reset app user password, write config.local.php.
# Run on VPS as root:  bash /tmp/provision-ehr1-mysql-config.sh
# Do not commit real credentials; script reads clpctl only on the server.
set -euo pipefail

ROOTPW=$(clpctl db:show:master-credentials 2>/dev/null | awk -F'|' '$2 ~ /Password/ {gsub(/^[ \t]+|[ \t]+$/,"",$3); print $3; exit}')
if [ -z "${ROOTPW:-}" ]; then
  echo "Could not read MySQL root password from clpctl."
  exit 1
fi

APP_PW=$(openssl rand -hex 24)
ROOT_MYSQL=(mysql -h127.0.0.1 -uroot -p"${ROOTPW}")

if ! "${ROOT_MYSQL[@]}" -e "ALTER USER 'ehr1-user'@'localhost' IDENTIFIED BY '${APP_PW}';" 2>/dev/null; then
  if ! "${ROOT_MYSQL[@]}" -e "ALTER USER 'ehr1-user'@'%' IDENTIFIED BY '${APP_PW}';" 2>/dev/null; then
    echo "ALTER USER for ehr1-user failed. Run: SELECT user,host FROM mysql.user WHERE user='ehr1-user';"
    exit 1
  fi
fi
"${ROOT_MYSQL[@]}" -e "FLUSH PRIVILEGES;"

CFG=/var/www/ehr1.cloud/public_html/ehr1-data/includes/config.local.php
cat >"$CFG" <<EOF
<?php
return [
    'environment' => 'production',
    'show_errors' => false,
    'http_base_path' => '/ehr1-data',
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'ehr1-data',
        'user'    => 'ehr1-user',
        'pass'    => '${APP_PW}',
        'charset' => 'utf8mb4',
    ],
];
EOF
chown www-data:www-data "$CFG"
chmod 640 "$CFG"

echo "OK: wrote $CFG (database ehr1-data, user ehr1-user)."
