#!/usr/bin/env bash
# Run on the VPS as root once (Debian/Ubuntu). Called by Deploy-Ehr1ToVps.ps1 -RemoteBootstrap or:
#   bash /path/to/vps-bootstrap-debian.sh
# Creates docroot under /var/www/ehr1.cloud, nginx site, MariaDB database ehr1_data.
# After this: create MySQL app user + GRANT, add includes/config.local.php, TLS (certbot).

set -euo pipefail

# WARNING: Do not run this on hosts using CloudPanel (or other panels) if apt would replace
# your existing MySQL (e.g. Percona). Use vps-minimal-layout.sh + panel vhost instead.

export DEBIAN_FRONTEND=noninteractive

DOCROOT="/var/www/ehr1.cloud/public_html"
SITE="ehr1.cloud"
DB_NAME="ehr1_data"

echo "==> apt update / install nginx MariaDB PHP UFW"
# Prefer IPv4 when IPv6 to Ubuntu mirrors fails on some VPS networks
APT_OPTS=(-o Acquire::ForceIPv4=true)
apt-get update -qq "${APT_OPTS[@]}"
# php-mysql pulls mysqli for the default PHP; php-mysqli is often a virtual package only
apt-get install -y "${APT_OPTS[@]}" nginx mariadb-server php-fpm php-mysql php-xml php-mbstring php-curl ufw

echo "==> Start PHP-FPM (detect unit name)"
PHP_SVC="$(ls /lib/systemd/system/php*-fpm.service 2>/dev/null | head -n1 || true)"
if [[ -z "${PHP_SVC}" ]]; then
  echo "ERROR: No /lib/systemd/system/php*-fpm.service (php-fpm package missing?)." >&2
  exit 1
fi
PHP_UNIT="$(basename "${PHP_SVC}")"
systemctl enable --now "${PHP_UNIT}"
systemctl restart "${PHP_UNIT}"

PHP_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"
if [[ -z "${PHP_SOCK}" ]]; then
  echo "ERROR: No /run/php/php*-fpm.sock after php-fpm start." >&2
  exit 1
fi
echo "    Using ${PHP_SOCK} (unit ${PHP_UNIT})"

echo "==> MariaDB + database ${DB_NAME}"
systemctl enable --now mariadb
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> docroot + permissions"
mkdir -p "${DOCROOT}/ehr1-data"
chown -R www-data:www-data /var/www/ehr1.cloud

echo "==> nginx site ${SITE}"
NGINX_SITE="/etc/nginx/sites-available/${SITE}.conf"
# Do not include snippets/fastcgi-php.conf here — it hardcodes another socket on many distros.
cat >"${NGINX_SITE}" <<NGX
server {
    listen 80;
    listen [::]:80;
    server_name ${SITE} www.${SITE};
    root ${DOCROOT};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:${PHP_SOCK};
    }

    location ~ /\.ht {
        deny all;
    }
}
NGX

ln -sf "${NGINX_SITE}" "/etc/nginx/sites-enabled/${SITE}.conf"
if [[ -e /etc/nginx/sites-enabled/default ]]; then
  rm -f /etc/nginx/sites-enabled/default
fi
nginx -t
systemctl reload nginx

echo "==> UFW"
ufw allow OpenSSH
if ufw app list 2>/dev/null | grep -q 'Nginx Full'; then
  ufw allow 'Nginx Full'
else
  ufw allow 80/tcp
  ufw allow 443/tcp
fi
ufw --force enable

echo ""
echo "Bootstrap done."
echo "  1) MySQL app user:  mysql -e \"CREATE USER 'ehr1_app'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD'; GRANT ALL ON ${DB_NAME}.* TO 'ehr1_app'@'localhost'; FLUSH PRIVILEGES;\""
echo "  2) Add ${DOCROOT}/ehr1-data/includes/config.local.php (db.host 127.0.0.1, db.name ${DB_NAME})."
echo "  3) After DNS for ${SITE} -> this server: certbot --nginx -d ${SITE} -d www.${SITE}"
echo "  4) From PC: powershell -File deploy/Deploy-Ehr1ToVps.ps1"
