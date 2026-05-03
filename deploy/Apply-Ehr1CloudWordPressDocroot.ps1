# Install deploy/nginx-site-ehr1.cloud-wordpress-docroot on the VPS:
# WordPress docroot + server_name ehr1.cloud; symlinks ehr1-data from deploy-paths RemoteAppDir if missing.
# sites-enabled link MUST be named ehr1.cloud.conf (panel nginx uses include *.conf).
#
#   powershell -ExecutionPolicy Bypass -File .\deploy\Apply-Ehr1CloudWordPressDocroot.ps1

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

$src = Join-Path $DeployRoot "nginx-site-ehr1.cloud-wordpress-docroot"
if (-not (Test-Path -LiteralPath $src)) {
    Write-Error "Missing $src"
}
if (-not (Test-Path -LiteralPath $IdentityFile)) {
    Write-Error "Missing key: $IdentityFile"
}

$target = "${sshUser}@${sshHost}"
$port = [string]$sshPort
$appPath = $cfg.RemoteAppDir

Write-Host "Installing WordPress-docroot ehr1.cloud vhost to $target (port $port)...`n"

& scp @("-i", $IdentityFile, "-P", $port, "-o", "StrictHostKeyChecking=accept-new", $src, "${target}:/tmp/ehr1.cloud.wpdoc")
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$remote = @'
set -e
WP_ROOT=/home/user/htdocs/srv1632616.hstgr.cloud
APP_TARGET="APP_PLACEHOLDER"
if [ ! -e "$WP_ROOT/ehr1-data" ]; then
  ln -s "$APP_TARGET" "$WP_ROOT/ehr1-data"
  echo SYMLINK_OK
elif [ -L "$WP_ROOT/ehr1-data" ]; then
  echo SYMLINK_EXISTS
else
  echo "BLOCKED: $WP_ROOT/ehr1-data exists and is not a symlink" >&2
  exit 1
fi
install -m 644 /tmp/ehr1.cloud.wpdoc /etc/nginx/sites-available/ehr1.cloud
rm -f /etc/nginx/sites-enabled/ehr1.cloud /etc/nginx/sites-enabled/ehr1.cloud.conf
ln -sf /etc/nginx/sites-available/ehr1.cloud /etc/nginx/sites-enabled/ehr1.cloud.conf
nginx -t
systemctl reload nginx
echo NGINX_OK
'@.Replace('APP_PLACEHOLDER', $appPath.Replace('"', '\"'))

& ssh @("-i", $IdentityFile, "-p", $port, "-o", "StrictHostKeyChecking=accept-new", $target, $remote)
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nSmoke test..."
& ssh @("-i", $IdentityFile, "-p", $port, "-o", "StrictHostKeyChecking=accept-new", $target,
    "curl -sS -o /dev/null -w 'HOME %{http_code}\n' -H 'Host: ehr1.cloud' http://127.0.0.1/; curl -sS -f -H 'Host: ehr1.cloud' http://127.0.0.1/ehr1-data/ping.txt; echo")
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
