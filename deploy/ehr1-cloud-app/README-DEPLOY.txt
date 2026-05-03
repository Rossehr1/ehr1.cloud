EHR1 Data — deploy (VPS nginx + Hostinger)
==========================================

VPS (ehr1.cloud on your own server, nginx + php-fpm)
----------------------------------------------------
- Paths come from **deploy/deploy-paths.json** (e.g. `public_html` + `public_html/ehr1-data`).
- One-shot from repo root:
    powershell -ExecutionPolicy Bypass -File .\deploy\Deploy-Ehr1ToVps.ps1 -SkipPrepare
  (`-SkipPrepare` skips remote `mkdir`/`chown` if layouts already exist.)
- **Hostinger-style VPS** (default nginx returns 444 for unknown hosts): deploy files, then **once** enable the vhost:
    powershell -ExecutionPolicy Bypass -File .\deploy\Apply-Ehr1CloudNginxVhost.ps1
  (Uses **deploy/nginx-ehr1.cloud-dedicated.conf** so `ehr1.cloud` serves `/var/www/ehr1.cloud/public_html`.)
- **Nginx does not read `.htaccess`.** If `/ehr1-data` (no trailing slash) 404s, the dedicated conf includes the redirect to `/ehr1-data/`. Bare Debian bootstrap: **deploy/vps-bootstrap-debian.sh** also adds that block.
- Dry-run: **deploy/Preflight-DeployEhr1Cloud.ps1**
- Site root (homepage, `/assets/*`) is **deploy/ehr1-cloud-site-root/**; the PHP app is **deploy/ehr1-cloud-app/**.

Hostinger / hPanel (legacy quick path)
--------------------------------------
The steps below assume Hostinger paths unless you use the VPS flow above.

EHR1 Data — deploy to ehr1.cloud (Hostinger Business, PHP + MySQL)
================================================================

SSH deploy (automated SCP from this PC — no password in scripts)
----------------------------------------------------------------
1) One-time: In hPanel, open SSH Keys (or Advanced -> SSH) and ADD your PUBLIC key.
   Public key file on this PC (after keygen):
     %USERPROFILE%\.ssh\ehr1_hostinger_ed25519.pub
   Paste the full single line (starts with ssh-ed25519) into Hostinger.

2) Confirm the remote web root for ehr1.cloud. If uploads fail, SSH in and run:
     pwd
     ls
   Typical path (edit deploy-via-scp.ps1 if needed):
     /home/USER/domains/ehr1.cloud/public_html/ehr1-data

3) From PowerShell in the project:
     cd deploy\ehr1-cloud-app\tools
     .\deploy-via-scp.ps1

Password-only SSH cannot be scripted safely; use key auth above.

----------------------------------------------------------------

1) Create MySQL database and user in hPanel (Websites -> ehr1.cloud -> Databases).
   Note: database name, username, password, host (usually 127.0.0.1 or localhost).

2) Import SQL schema — either:

   A) phpMyAdmin: Databases -> your DB -> Import each file IN ORDER from project sql/mysql/:
     00_meta.sql ... 05_core_supplemental.sql, then optional 99_seed_test_data.sql

   B) SSH (after uploading sql/mysql/ next to the app): from ehr1-data directory run:
        php tools/install_schema.php
      (Uses mysqli; requires sql/mysql/*.sql under ehr1-data/sql/mysql/)

3) Upload this folder's CONTENTS to your site document root for ehr1.cloud, e.g.:
     public_html/ehr1-data/
   So the app URL is: https://ehr1.cloud/ehr1-data/

4) Ensure includes/config.local.php exists on the server with MySQL credentials
   (upload from your PC if you created it locally; never commit it to git).

5) PHP version: hPanel -> Advanced -> PHP Configuration -> PHP 8.1+ recommended.

6) Protect the URL: hPanel -> Directory privacy / password protect "ehr1-data"
   (obscure URL + login; do not rely on secrecy alone).

7) Visit https://ehr1.cloud/ehr1-data/ — you should see row counts and sample rows
   if seed SQL was imported.

Do NOT commit config.local.php to git (see project .gitignore).
