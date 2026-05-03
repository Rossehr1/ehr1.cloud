# Push deploy/nginx-ehr1.cloud-dedicated.conf to the VPS and enable it (HTTP :80 for ehr1.cloud).
# Uses deploy-paths.json + deploy/.env.deploy for SSH (same as Deploy-Ehr1ToVps.ps1).
#
#   powershell -ExecutionPolicy Bypass -File .\deploy\Apply-Ehr1CloudNginxVhost.ps1

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

$src = Join-Path $DeployRoot "nginx-ehr1.cloud-dedicated.conf"
if (-not (Test-Path -LiteralPath $src)) {
    Write-Error "Missing $src"
}
if (-not (Test-Path -LiteralPath $IdentityFile)) {
    Write-Error "Missing key: $IdentityFile"
}

$target = "${sshUser}@${sshHost}"
$port = [string]$sshPort
Write-Host "Installing nginx vhost to $target (port $port)...`n"

& scp @("-i", $IdentityFile, "-P", $port, "-o", "StrictHostKeyChecking=accept-new", $src, "${target}:/tmp/ehr1.cloud.conf")
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$remote = @'
set -e
install -m 644 /tmp/ehr1.cloud.conf /etc/nginx/sites-available/ehr1.cloud.conf
ln -sf /etc/nginx/sites-available/ehr1.cloud.conf /etc/nginx/sites-enabled/ehr1.cloud.conf
nginx -t
systemctl reload nginx
echo NGINX_OK
'@.Replace("`r`n", "`n")

& ssh @("-i", $IdentityFile, "-p", $port, "-o", "StrictHostKeyChecking=accept-new", $target, $remote)
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nSmoke test (localhost:80, Host: ehr1.cloud)..."
& ssh @("-i", $IdentityFile, "-p", $port, "-o", "StrictHostKeyChecking=accept-new", $target, "curl -sS -f -H 'Host: ehr1.cloud' http://127.0.0.1/ | head -c 200; echo; curl -sS -f -H 'Host: ehr1.cloud' http://127.0.0.1/ehr1-data/ping.txt; echo")
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Write-Host "`nDone. Add TLS (listen 443) when ready; see comments in nginx-ehr1.cloud-dedicated.conf."
