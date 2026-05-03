# Copy repo tools/ (Python EP PAID loaders, etc.) to the VPS for CLI runs against production MySQL
# once includes/config.local.php has valid credentials (or pass --config to scripts).
# Does not upload Data Originals (large; keep on ETL workstation or sync separately).
#
# Target on VPS: /var/www/ehr1.cloud/etl-tools  (alongside public_html)
#
#   powershell -ExecutionPolicy Bypass -File .\deploy\Push-EtlToolsToVps.ps1

param(
    [string] $Profile = "",
    [string] $IdentityFile = $(Join-Path $env:USERPROFILE ".ssh\ehr1_vps_ed25519")
)

$ErrorActionPreference = "Stop"
$DeployRoot = $PSScriptRoot
$RepoRoot = Resolve-Path (Join-Path $DeployRoot "..")
$sec = & (Join-Path $DeployRoot "Read-DeploySecrets.ps1") -DeployRoot $DeployRoot
if (-not $PSBoundParameters.ContainsKey('IdentityFile') -and $sec.IdentityFile) {
    $IdentityFile = $sec.IdentityFile
}
$cfg = & (Join-Path $DeployRoot "Read-DeployPaths.ps1") -DeployRoot $DeployRoot -Profile $Profile
$sshHost = if ($sec.RemoteHost) { $sec.RemoteHost } else { $cfg.RemoteHost }
$sshPort = [int]$cfg.Port
if ($sec.Port) {
    $pSec = 0
    if ([int]::TryParse($sec.Port, [ref]$pSec) -and $pSec -gt 0) { $sshPort = $pSec }
}
$sshUser = if ($sec.User) { $sec.User } else { $cfg.User }

$target = "${sshUser}@${sshHost}"
$port = [string]$sshPort
$localTools = Join-Path $RepoRoot "tools"
if (-not (Test-Path -LiteralPath $localTools)) {
    Write-Error "Missing tools folder: $localTools"
}

Write-Host "Ensuring remote etl-tools dir on $target...`n"
& ssh @("-i", $IdentityFile, "-p", $port, "-o", "StrictHostKeyChecking=accept-new", $target, "mkdir -p /var/www/ehr1.cloud/etl-tools")
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$files = @(Get-ChildItem -LiteralPath $localTools -File | Where-Object {
        $_.Extension -match '^\.(py|txt)$' -and $_.Name -notmatch 'pycache'
    })
foreach ($f in $files) {
    Write-Host "  $($f.Name)"
    & scp @("-i", $IdentityFile, "-P", $port, "-o", "StrictHostKeyChecking=accept-new", $f.FullName, "${target}:/var/www/ehr1.cloud/etl-tools/")
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "`nDone. On VPS: cd /var/www/ehr1.cloud/etl-tools && python3 -m venv .venv && .venv/bin/pip install -r requirements-ep-paid.txt"
Write-Host "Then run loaders with config path to ehr1-data/includes/config.local.php (after real DB credentials are set).`n"
