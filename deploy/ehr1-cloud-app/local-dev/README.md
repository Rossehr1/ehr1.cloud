# Local environment (Docker)

Run the EHR1 Data PHP app and a MySQL 8 instance on your machine before deploying to the **VPS** (`ehr1.cloud`).

**Production** uses **`deploy/deploy-paths.json`** and **`README-DEPLOY.txt`**. Legacy Hostinger-only notes: **`deploy/EHR1-HOSTINGER-ehr1-cloud.txt`**.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/macOS) or Docker Engine + Compose on Linux.

## Setup

### Quick start (PowerShell)

From **`deploy\ehr1-cloud-app\local-dev`**:

```powershell
.\start-local.ps1 -WipeVolume -InstallSchema   # first time or after auth/DB issues
.\start-local.ps1 -InstallSchema                # containers already up; run SQL only
.\start-local.ps1                               # start / rebuild, then open the URL below
```

`-WipeVolume` runs `docker compose down -v` (deletes the local MySQL volume).  
`-InstallSchema` runs `tools/install_schema.php` inside the `web` container.

### Manual setup

1. Open a terminal in **this folder** (`deploy/ehr1-cloud-app/local-dev`).

2. Create app config (once):

   ```powershell
   copy config.local.docker.example.php ..\includes\config.local.php
   ```

   `config.local.php` is gitignored.

   - **Browsing http://localhost:8080/...** (recommended): PHP runs **inside** the `web` container. Use **`local-dev/config.local.docker.example.php`** — database **`host` must be `db`**, port **3306** (internal Docker network).
   - **Running `php` on Windows** (install_schema, loaders) while only `db` is in Docker: use **`config.local.docker-host-php.example.php`** instead — **`host` `127.0.0.1`**, port **3307**.

   If you **already** use `includes/config.local.php` for **production**, **back it up** first. Swap in Docker config only while testing locally.

3. Start containers:

   ```powershell
   docker compose up --build
   ```

4. Install schema and seed (once, or after wiping the DB volume):

   ```powershell
   docker compose exec web php /var/www/html/ehr1-data/tools/install_schema.php
   ```

5. Open in a browser (**http** only on this port):

   - **Home (landing):** http://127.0.0.1:8080/  
   - **Data app:** http://127.0.0.1:8080/ehr1-data/index.php  
   - **Data explorer:** http://127.0.0.1:8080/ehr1-data/reports/index.php  

   If port **8080** is already in use, set **`EHR1_LOCAL_WEB_PORT=8081`** (or another free port) in your environment before `docker compose up` or `start-local.ps1`, then open that port in the URL.

## Home page works but nothing else (local vs production)

**Local Docker:** The compose file mounts **`deploy/ehr1-cloud-site-root/`** at **`/`** (same as production `public_html`) and the PHP app at **`/ehr1-data/`**. If you still saw only a generic home page and dead links, you were on an older compose that only mounted the app — run **`docker compose up -d --build`** again from **`local-dev`**.

**Production (`ehr1.cloud`):** If the HTML home from **`deploy-site-root`** loads but **`/ehr1-data/`**, **`/certified-backup.html`**, or **`/ehr1-data/ping.txt`** 404, run **`deploy-site-root.ps1`** then **`deploy-via-scp.ps1`**, then see **`deploy/EHR1-HOSTINGER-ehr1-cloud.txt`** if paths still fail (vhost / DNS).

## This site can't be reached (local Docker)

Chrome / Edge **"This site can't be reached"** on **localhost** almost always means **nothing is accepting that TCP port** (not a PHP/DB error).

1. **Use `http://` — not `https://`** for **127.0.0.1:8080**. There is no TLS certificate on the dev port unless you add one.

2. **Docker Desktop must be running** (whale icon idle / running). Then in `local-dev`:

   ```powershell
   docker compose ps
   ```

   You want **`web`** and **`db`** **Up**, and **`web`** showing **`0.0.0.0:8080->80/tcp`** (or whichever host port you set). If **`web`** is missing or **Exited**, run:

   ```powershell
   docker compose logs --tail 80 web
   ```

3. **Project on Google Drive / OneDrive (`I:\My Drive\...`)**  
   Docker **bind mounts** from cloud-synced paths often **fail or behave empty** on Windows, so Apache never serves and the port stays closed. **Copy or clone the repo to a normal folder** (e.g. `C:\Dev\EHR1-Data`) and run **`start-local.ps1`** from **`local-dev`** there.

4. **Port conflict**  
   Another app may be using **8080**. Change the published port:

   ```powershell
   $env:EHR1_LOCAL_WEB_PORT = "8081"
   .\start-local.ps1
   ```

   Then open **http://127.0.0.1:8081/ehr1-data/index.php** .

## MySQL from the host

- Port **3307** maps to MySQL **3306** in the container.
- User `ehr1` / password `ehr1local`, database `ehr1_local`, root password `rootlocal` (see `docker-compose.yml`).

## Not connecting / database errors

1. **Confirm which PHP is running:**  
   In the browser you must use config with **`host` = `db`**.  
   If you copied a wrong **`host`** (e.g. Docker's **`db`** while running CLI PHP on Windows), connections will fail (`db` is not a hostname outside Docker).

2. **Test from the web container:**

   ```powershell
   docker compose exec web php /var/www/html/ehr1-data/tools/test_db_connection.php
   ```

   You should see `OK` and a table list (may be empty before `install_schema`).

3. **MySQL auth / stale volume:** This stack sets **`mysql_native_password`** for compatibility with PHP. If you created the DB volume **before** that change, recreate it:

   ```powershell
   docker compose down -v
   docker compose up --build
   ```

   Then copy the right config again and run `install_schema` (see above).

4. **503 “Configuration missing”:** `includes/config.local.php` is missing or not visible in the container (path/share issue). Confirm the file exists under `deploy/ehr1-cloud-app/includes/` on your PC.

## Stop / reset

- `docker compose down` — stop containers.  
- `docker compose down -v` — also delete the MySQL volume (fresh DB next `up`).

## EP PAID import test (Docker)

After the stack is up (`start-local.ps1` or `docker compose up`):

1. **Loader DB host:** the Python loader runs on Windows and must use **`config.local.docker-host-php.example.php`** (`127.0.0.1`, port **3307**). The file under `includes/config.local.php` uses **`db`** — that is only valid **inside** the `web` container.
2. **Quick script:** from **`local-dev`**, run **`.\run-ep-paid-import-test.ps1 -ResetDb -MaxRows 5000`** for a fresh DB, full `install_schema` (**`07_archive`**, **`10_supplemental_ep_paid`**, **`11_merged_ep_paid`**), import, and **`merged_ep_paid_npi`** rebuild. Omit **`-MaxRows`** for the full workbook (~500k+ data rows, long run).
3. **Expectations:** Docker seed NPIs are synthetic **`100300000x`**. Real EP PAID NPIs **archive** as **`NPI_NOT_IN_MASTER`** until **`npidata`** (or inserts) populate **`core_npi_provider`**. After a successful load, **`merged_ep_paid_npi`** has at most one row per NPI (latest **`supplemental_ep_paid`** row). Open **http://127.0.0.1:8080/ehr1-data/index.php** and Data explorer (**EP PAID merged** column when merged rows exist).
4. **Workbook-only smoke test (no Docker):** from repo root, `python tools/smoke_test_ep_paid_xlsx.py`.

## Notes

- The `sql/mysql` tree is bind-mounted read-only from the repo root (`EHR1 Data/sql/mysql`).
- Production deploy scripts (`tools/deploy-via-scp.ps1`) do not upload `includes/config.local.php`; keep production credentials only on the server.
