# Dry-run / preflight for ehr1.cloud VPS deploy. Read-only remote checks (SSH BatchMode).
# Uses deploy/deploy-paths.json + optional deploy/.env.deploy (same as Deploy-Ehr1ToVps.ps1).
# Does not upload files or change permissions.
#
#   powershell -ExecutionPolicy Bypass -File .\deploy\Preflight-DeployEhr1Cloud.ps1
#   powershell -ExecutionPolicy Bypass -File .\deploy\Preflight-DeployEhr1Cloud.ps1 -Profile ehr1.cloud

param(
    [string] $Profile = "",
    [string] $IdentityFile = $(Join-Path $env:USERPROFILE ".ssh\ehr1_vps_ed25519")
)

$ErrorActionPreference = "Stop"
$DeployRoot = $PSScriptRoot
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

$pub = $cfg.RemotePublicHtml
$app = $cfg.RemoteAppDir
$dom = $cfg.Domain
$prof = if ($cfg.PSObject.Properties['DeployProfile']) { $cfg.DeployProfile } else { "" }

Write-Host "=== ehr1.cloud deploy preflight (dry-run) ===`n"
Write-Host "Profile:      $prof"
Write-Host "Domain:       $dom"
Write-Host "SSH:          ${sshUser}@${sshHost} port $sshPort"
Write-Host "Public HTML:  $pub"
Write-Host "App dir:      $app"
Write-Host "Identity:     $IdentityFile`n"

if (-not (Test-Path -LiteralPath $IdentityFile)) {
    Write-Error "Missing key: $IdentityFile`nCreate: ssh-keygen -t ed25519 -f `"$IdentityFile`"`nOr set EHR1_DEPLOY_SSH_IDENTITY_FILE in deploy/.env.deploy"
}

$target = "${sshUser}@${sshHost}"
$portStr = [string]$sshPort

# Bash: optional curl (nginx must answer on 127.0.0.1:80 for this vhost via Host header).
$remote = "echo REMOTE_OK; if [ -d `"$pub`" ]; then echo DIR_OK_PUB; else echo DIR_MISSING_PUB; fi; if [ -d `"$app`" ]; then echo DIR_OK_APP; else echo DIR_MISSING_APP; fi; if [ -f `"$app/includes/config.local.php`" ]; then echo CONFIG_LOCAL_PRESENT; else echo CONFIG_LOCAL_ABSENT; fi; if command -v php >/dev/null 2>&1; then php -v | head -n 1; else echo PHP_NOT_IN_PATH; fi; if command -v curl >/dev/null 2>&1; then curl -sS --max-time 3 -f -H 'Host: $($dom)' http://127.0.0.1/ >/dev/null && echo HTTP_HOME_OK || echo HTTP_HOME_FAIL; curl -sS --max-time 3 -f -H 'Host: $($dom)' http://127.0.0.1/ehr1-data/ping.txt >/dev/null && echo HTTP_APP_PING_OK || echo HTTP_APP_PING_FAIL; else echo CURL_SKIP; fi"

Write-Host "--- SSH BatchMode probe ---"
& ssh @(
    "-i", $IdentityFile,
    "-p", $portStr,
    "-o", "StrictHostKeyChecking=accept-new",
    "-o", "BatchMode=yes",
    "-o", "ConnectTimeout=15",
    $target,
    $remote
)
$sshExit = $LASTEXITCODE
Write-Host "`nssh exit: $sshExit"

Write-Host "`n--- Checklist (after preflight passes) ---"
Write-Host "  1. DNS: ehr1.cloud A record -> $($cfg.PublicDnsARecordIPv4) (or your live IP)."
Write-Host "  2. VPS: config.local.php under ehr1-data/includes (never committed)."
Write-Host "  3. Schema: php tools/install_schema.php (or targeted migrate_*.php) on server if DDL changed."
Write-Host "  4. Controlled full deploy from repo root:"
Write-Host "       powershell -ExecutionPolicy Bypass -File .\deploy\Deploy-Ehr1ToVps.ps1"
Write-Host "     Skip remote mkdir if layouts exist:"
Write-Host "       powershell -ExecutionPolicy Bypass -File .\deploy\Deploy-Ehr1ToVps.ps1 -SkipPrepare"
Write-Host "     Site root only or app only:"
Write-Host "       ... -SkipApp   or   ... -SkipSiteRoot"
Write-Host ""

if ($sshExit -ne 0) {
    exit $sshExit
}
