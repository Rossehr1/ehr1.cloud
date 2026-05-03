# EP PAID local import + verify (Docker MySQL + Python on Windows host).
# Run from this folder. Requires Docker Desktop, Python 3 with pymysql + openpyxl.
# Uses config.local.docker-host-php.example.php (127.0.0.1:3307) for the loader — not the in-container "db" host.
#
# Examples:
#   .\run-ep-paid-import-test.ps1 -ResetDb -MaxRows 5000          # fresh DB, schema+seed, import first 5k rows
#   .\run-ep-paid-import-test.ps1 -MaxRows 2000                    # import only (schema already applied)
#   .\run-ep-paid-import-test.ps1 -InstallSchema                  # reapply DDL+seed (destructive), then import
#   .\run-ep-paid-import-test.ps1 -ResetDb                         # full ~506k rows (long run)

param(
    [switch] $ResetDb,
    [switch] $InstallSchema,
    [int] $MaxRows = 0,
    [string] $EpPaidFile = "",
    [string] $LoaderConfig = ""
)

$ErrorActionPreference = "Stop"
$Here = $PSScriptRoot
$AppRoot = Resolve-Path (Join-Path $Here "..")
$RepoRoot = Resolve-Path (Join-Path $Here "..\..\..")
$ComposeFile = Join-Path $Here "docker-compose.yml"
$HostPhpConfig = if ($LoaderConfig -ne "") { $LoaderConfig } else { Join-Path $Here "config.local.docker-host-php.example.php" }

function Get-DockerExe {
    if (Get-Command docker -ErrorAction SilentlyContinue) { return "docker" }
    $bin = "C:\Program Files\Docker\Docker\resources\bin\docker.exe"
    if (Test-Path $bin) { return $bin }
    Write-Error "Docker not found. Install Docker Desktop or add docker to PATH."
}

$Docker = Get-DockerExe
if (-not (Test-Path -LiteralPath $HostPhpConfig)) {
    Write-Error "Missing loader config: $HostPhpConfig"
}
if (-not (Test-Path -LiteralPath $ComposeFile)) {
    Write-Error "Missing $ComposeFile"
}

Push-Location $Here
try {
    if ($ResetDb) {
        Write-Host "Resetting database volume..."
        & $Docker compose -f $ComposeFile down -v
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    }

    Write-Host "Starting web + db..."
    & $Docker compose -f $ComposeFile up -d --build
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

    Write-Host "Waiting for MySQL..."
    $ready = $false
    for ($i = 0; $i -lt 45; $i++) {
        & $Docker compose -f $ComposeFile exec -T db mysqladmin ping -h localhost -uroot -prootlocal 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 2
    }
    if (-not $ready) { Write-Error "MySQL did not become ready. Try: docker compose logs db" }

    if ($ResetDb -or -not (Test-Path (Join-Path $AppRoot "includes\config.local.php"))) {
        $ex = Join-Path $Here "config.local.docker.example.php"
        if (-not (Test-Path (Join-Path $AppRoot "includes\config.local.php"))) {
            Write-Host "Creating includes\config.local.php from Docker example (host=db) for the web app."
            Copy-Item -LiteralPath $ex -Destination (Join-Path $AppRoot "includes\config.local.php") -Force
        }
    }

    if ($ResetDb -or $InstallSchema) {
        Write-Host "Installing schema + seed (00–07, 10, 06, seeds — includes archive + supplemental_ep_paid)..."
        & $Docker compose -f $ComposeFile exec -T web php /var/www/html/ehr1-data/tools/install_schema.php
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    }

    $pyArgs = @(
        (Join-Path $RepoRoot "tools\load_ep_paid.py"),
        "--config", $HostPhpConfig
    )
    if ($EpPaidFile -ne "") {
        $pyArgs += "--file", $EpPaidFile
    }
    if ($MaxRows -gt 0) {
        $pyArgs += "--max-rows", "$MaxRows"
    }

    Write-Host "Running EP PAID loader from repo (host MySQL port 3307)..."
    & python @pyArgs
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

    Write-Host "`nCounts (supplemental / merged / archive / last ep_paid batch):"
    $q = @"
SELECT (SELECT COUNT(*) FROM supplemental_ep_paid) AS ep_paid_supplemental,
       (SELECT COUNT(*) FROM merged_ep_paid_npi) AS ep_paid_merged,
       (SELECT COUNT(*) FROM archive_supplemental_row) AS archived;
SELECT batch_id, row_count_loaded, row_count_skipped_invalid_npi, row_count_skipped_duplicate, notes
FROM ref_source_batch WHERE source_key = 'ep_paid' ORDER BY batch_id DESC LIMIT 1;
"@
    $q | & $Docker compose -f $ComposeFile exec -T db mysql -uehr1 -pehr1local -t ehr1_local
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

    Write-Host "`nSample active payload (first row):"
    $q2 = "SELECT ep_paid_id, npi, LEFT(JSON_PRETTY(payload_json), 800) AS payload_preview FROM supplemental_ep_paid ORDER BY ep_paid_id LIMIT 1;"
    $q2 | & $Docker compose -f $ComposeFile exec -T db mysql -uehr1 -pehr1local -t ehr1_local
    if ($LASTEXITCODE -ne 0) { Write-Warning "No active rows or query failed (expected if all rows archived)." }

    Write-Host "`nSample merged payload (first NPI):"
    $q3 = "SELECT npi, LEFT(JSON_PRETTY(payload_json), 600) AS payload_preview FROM merged_ep_paid_npi ORDER BY npi LIMIT 1;"
    $q3 | & $Docker compose -f $ComposeFile exec -T db mysql -uehr1 -pehr1local -t ehr1_local
    if ($LASTEXITCODE -ne 0) { Write-Warning "merged_ep_paid_npi missing or empty — run install_schema (11_merged_ep_paid.sql)." }

    $WebPort = if ($env:EHR1_LOCAL_WEB_PORT) { $env:EHR1_LOCAL_WEB_PORT } else { "8080" }
    Write-Host "`nDone. Open http://127.0.0.1:${WebPort}/ehr1-data/index.php — merged_ep_paid_npi is the per-NPI master-class EP PAID view."
} finally {
    Pop-Location
}
