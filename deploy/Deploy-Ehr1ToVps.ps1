# One-shot deploy — optional SSH settings in deploy/.env.deploy (see env.deploy.example; file is gitignored).
# New clone + new VPS: from deploy/, run Prepare-NewVpsDeploy.ps1 first (checklist + paths).
# From repo:  powershell -ExecutionPolicy Bypass -File .\deploy\Deploy-Ehr1ToVps.ps1
# Optional CloudPanel / existing stack:  -RemoteMinimalLayout  (mkdir + chown + CREATE DATABASE only; no apt)
#              ... -RemoteBootstrap -BootstrapOnly   # install stack only (Debian/Ubuntu), then deploy separately
#              ... -RemoteBootstrap                  # install stack then prepare + upload in one run

param(
    [string] $Profile = "",
    [string] $IdentityFile = $(Join-Path $env:USERPROFILE ".ssh\ehr1_vps_ed25519"),
    [string] $WebUser = "www-data",
    [string] $WebGroup = "www-data",
    [switch] $SkipPrepare,
    [switch] $SkipSiteRoot,
    [switch] $SkipApp,
    [switch] $PushLocalConfig,
    [switch] $RemoteBootstrap,
    [switch] $BootstrapOnly,
    [switch] $RemoteMinimalLayout
)

$ErrorActionPreference = "Stop"
if ($BootstrapOnly) { $RemoteBootstrap = $true }
if ($RemoteBootstrap -and $RemoteMinimalLayout) {
    Write-Error "Use either -RemoteBootstrap or -RemoteMinimalLayout, not both."
}
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

if (-not (Test-Path -LiteralPath $IdentityFile)) {
    Write-Error "Missing SSH private key: $IdentityFile`nCreate: ssh-keygen -t ed25519 -f `"$IdentityFile`"`nOr set EHR1_DEPLOY_SSH_IDENTITY_FILE in deploy/.env.deploy (see deploy/env.deploy.example)."
}

function Invoke-RemoteSsh {
    param([string] $RemoteCmd)
    $target = "${sshUser}@${sshHost}"
    $port = [string]$sshPort
    Write-Host "ssh $target (port $port): $RemoteCmd`n"
    & ssh @("-i", $IdentityFile, "-p", $port, "-o", "StrictHostKeyChecking=accept-new", $target, $RemoteCmd)
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

if ($RemoteBootstrap) {
    $boot = Join-Path $DeployRoot "vps-bootstrap-debian.sh"
    if (-not (Test-Path -LiteralPath $boot)) {
        Write-Error "Missing $boot"
    }
    Write-Warning "RemoteBootstrap installs packages on $sshHost (nginx, php-fpm, MariaDB, UFW). Debian/Ubuntu only. Ctrl+C to abort; continuing in 3s..."
    Start-Sleep -Seconds 3
    $target = "${sshUser}@${sshHost}"
    $port = [string]$sshPort
    & scp @("-i", $IdentityFile, "-P", $port, "-o", "StrictHostKeyChecking=accept-new", $boot, "${target}:/tmp/vps-bootstrap-debian.sh")
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Invoke-RemoteSsh "bash /tmp/vps-bootstrap-debian.sh"
    if ($BootstrapOnly) {
        Write-Host "`nBootstrapOnly: add MySQL app user + config.local.php on the VPS, then run:`n  powershell -ExecutionPolicy Bypass -File .\Deploy-Ehr1ToVps.ps1`n"
        exit 0
    }
    Write-Host "`nRemoteBootstrap done. Ensure MySQL user + config.local.php exist; continuing with prepare/upload.`n"
}

if ($RemoteMinimalLayout) {
    $min = Join-Path $DeployRoot "vps-minimal-layout.sh"
    if (-not (Test-Path -LiteralPath $min)) {
        Write-Error "Missing $min"
    }
    Write-Host "RemoteMinimalLayout: safe mkdir + DB create on $sshHost (no apt).`n"
    $target = "${sshUser}@${sshHost}"
    $port = [string]$sshPort
    & scp @("-i", $IdentityFile, "-P", $port, "-o", "StrictHostKeyChecking=accept-new", $min, "${target}:/tmp/vps-minimal-layout.sh")
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Invoke-RemoteSsh "bash /tmp/vps-minimal-layout.sh"
}

function Get-PosixParentDir {
    param([string]$Path)
    $p = $Path.Trim().TrimEnd('/')
    $i = $p.LastIndexOf('/')
    if ($i -le 0) { return '/' }
    return $p.Substring(0, $i)
}

if (-not $SkipPrepare) {
    $parent = Get-PosixParentDir $cfg.RemotePublicHtml
    $pub = $cfg.RemotePublicHtml.Replace("'", "'\''")
    $app = $cfg.RemoteAppDir.Replace("'", "'\''")
    $par = $parent.Replace("'", "'\''")
    $wuser = $WebUser.Replace("'", "'\''")
    $wgrp = $WebGroup.Replace("'", "'\''")
    $bash = "set -e; mkdir -p '$pub' '$app'; chown -R '$wuser':'$wgrp' '$par'"
    Invoke-RemoteSsh $bash
}

if (-not $SkipSiteRoot) {
    & (Join-Path $DeployRoot "ehr1-cloud-site-root\deploy-site-root.ps1") -Profile $Profile -IdentityFile $IdentityFile -RemoteHost $sshHost -Port $sshPort -User $sshUser
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

if (-not $SkipApp) {
    $appDeploy = Join-Path $DeployRoot "ehr1-cloud-app\tools\deploy-via-scp.ps1"
    if ($PushLocalConfig) {
        & $appDeploy -Profile $Profile -IdentityFile $IdentityFile -RemoteHost $sshHost -Port $sshPort -User $sshUser -PushLocalConfig
    } else {
        & $appDeploy -Profile $Profile -IdentityFile $IdentityFile -RemoteHost $sshHost -Port $sshPort -User $sshUser
    }
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "`nDone. Next: ensure config.local.php exists on VPS; run php tools/install_schema.php; point ehr1.cloud DNS at $($cfg.PublicDnsARecordIPv4)."
Write-Host "If the host uses a catch-all nginx that drops unknown Host headers (e.g. some panel VPS), run once:"
Write-Host "  powershell -ExecutionPolicy Bypass -File .\deploy\Apply-Ehr1CloudNginxVhost.ps1`n"
