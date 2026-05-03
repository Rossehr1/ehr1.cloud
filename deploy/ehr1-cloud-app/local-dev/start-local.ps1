# Start local EHR1 Data stack (Docker). Run from this folder or anywhere:
#   .\deploy\ehr1-cloud-app\local-dev\start-local.ps1
#
# First time: creates includes/config.local.php from the Docker example if missing.
# Optional: -WipeVolume  runs  docker compose down -v  first (fresh MySQL, fixes old auth).
# Optional: -InstallSchema  runs tools/install_schema.php after the web container is up.

param(
    [switch] $WipeVolume,
    [switch] $InstallSchema
)

$ErrorActionPreference = "Stop"
$Here = $PSScriptRoot
$AppRoot = Resolve-Path (Join-Path $Here "..")
$ConfigLocal = Join-Path $AppRoot "includes\config.local.php"
$Example = Join-Path $Here "config.local.docker.example.php"

function Assert-Docker {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        $bin = "C:\Program Files\Docker\Docker\resources\bin\docker.exe"
        if (Test-Path $bin) {
            $script:Docker = $bin
            return
        }
        Write-Error "Docker not found. Install Docker Desktop and ensure 'docker' is on PATH, or restart your terminal after install."
    }
    $script:Docker = "docker"
}

Assert-Docker
Push-Location $Here

$WebPort = if ($env:EHR1_LOCAL_WEB_PORT) { $env:EHR1_LOCAL_WEB_PORT } else { "8080" }

try {
    if (-not (Test-Path $Example)) {
        Write-Error "Missing $Example"
    }

    if (-not (Test-Path $ConfigLocal)) {
        Write-Host "Creating includes\config.local.php from Docker example (host=db)."
        Copy-Item -LiteralPath $Example -Destination $ConfigLocal -Force
    }

    $rootStr = $AppRoot.Path
    if ($rootStr -match 'My Drive|Google Drive') {
        Write-Warning "Project path is on Google Drive / cloud sync. Docker bind mounts often fail from I:\My Drive\..., so the web container may not start or port 8080 stays closed. Copy the repo to a normal folder (e.g. C:\Dev\EHR1-Data) and run this script again."
    }

    if ($WipeVolume) {
        Write-Host "Removing containers and named volume (fresh database)..."
        & $Docker compose down -v
    }

    Write-Host "Building and starting web + db (host port $WebPort)..."
    & $Docker compose up -d --build
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

    Write-Host "Waiting for MySQL to accept connections..."
    $ready = $false
    for ($i = 0; $i -lt 40; $i++) {
        & $Docker compose exec -T db mysqladmin ping -h localhost -uroot -prootlocal 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 2
    }
    if (-not $ready) {
        Write-Warning "MySQL did not respond in time. Check: docker compose logs db"
    }

    Write-Host "`nContainer status:"
    & $Docker compose ps

    try {
        $tn = Test-NetConnection -ComputerName 127.0.0.1 -Port $WebPort -WarningAction SilentlyContinue
        if (-not $tn.TcpTestSucceeded) {
            Write-Warning "Nothing is listening on TCP port $WebPort. See README 'This site cannot be reached'. Try: docker compose logs web"
        }
    } catch { }

    Write-Host "`nDB connection test (inside web container):"
    & $Docker compose exec -T web php /var/www/html/ehr1-data/tools/test_db_connection.php
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "DB test failed. See local-dev\README.md (Not connecting). Try: .\start-local.ps1 -WipeVolume"
        exit $LASTEXITCODE
    }

    if ($InstallSchema) {
        Write-Host "`nInstalling schema..."
        & $Docker compose exec -T web php /var/www/html/ehr1-data/tools/install_schema.php
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    }

    Write-Host "`nDone. Home: http://127.0.0.1:$WebPort/   Data app: http://127.0.0.1:$WebPort/ehr1-data/index.php   (use http, not https)"
    if (-not $InstallSchema) {
        Write-Host "First-time DB:  .\start-local.ps1 -InstallSchema   (or add -InstallSchema next time with stack already up)"
    }
} finally {
    Pop-Location
}
