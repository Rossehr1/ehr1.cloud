# Upload ehr1-cloud-app to the VPS over SCP (SSH key auth — see README-DEPLOY.txt).
# Optional SSH key/host/port/user: deploy/.env.deploy (see deploy/env.deploy.example). Never commit .env.deploy.
# Default host, port, user, and remote path: deploy/deploy-paths.json (see deploy/Read-DeployPaths.ps1).
# Stages a temp copy without includes/config.local.php so production DB credentials are not overwritten.
# Usage: .\deploy-via-scp.ps1
#        .\deploy-via-scp.ps1 -Profile ehr1.cloud   # optional profile (see deploy/deploy-paths.json)

param(
    [string] $RemoteHost = "",
    [int] $Port = 0,
    [string] $User = "",
    [string] $RemoteBase = "",
    [string] $Profile = "",
    [string] $IdentityFile = $(Join-Path $env:USERPROFILE ".ssh\ehr1_vps_ed25519"),
    [switch] $PushLocalConfig = $false
)

$ErrorActionPreference = "Stop"
$DeployRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$RepoRoot = Split-Path $DeployRoot -Parent
$sec = & (Join-Path $DeployRoot "Read-DeploySecrets.ps1") -DeployRoot $DeployRoot
if (-not $PSBoundParameters.ContainsKey('IdentityFile') -and $sec.IdentityFile) {
    $IdentityFile = $sec.IdentityFile
}
$cfg = & (Join-Path $DeployRoot "Read-DeployPaths.ps1") -DeployRoot $DeployRoot -Profile $Profile
if ([string]::IsNullOrWhiteSpace($RemoteHost)) {
    $RemoteHost = if ($sec.RemoteHost) { $sec.RemoteHost } else { $cfg.RemoteHost }
}
if ($Port -le 0) {
    if ($sec.Port) {
        $pSec = 0
        if ([int]::TryParse($sec.Port, [ref]$pSec) -and $pSec -gt 0) { $Port = $pSec }
    }
    if ($Port -le 0) { $Port = [int]$cfg.Port }
}
if ([string]::IsNullOrWhiteSpace($User)) {
    $User = if ($sec.User) { $sec.User } else { $cfg.User }
}
if ([string]::IsNullOrWhiteSpace($RemoteBase)) { $RemoteBase = $cfg.RemoteAppDir }

$local = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path $IdentityFile)) {
    Write-Error "Missing key: $IdentityFile - run: ssh-keygen -t ed25519 -f `"$IdentityFile`""
}

if (-not (Test-Path $local)) {
    Write-Error "Missing folder: $local"
}

$tail = ""
if ($cfg.PSObject.Properties['DeployProfile']) {
    $tail = "  profile: $($cfg.DeployProfile) ($($cfg.Domain))`n"
}
Write-Host "Uploading from:`n  $local`nto:`n  ${User}@${RemoteHost}:$RemoteBase`n$tail"
if (-not $PushLocalConfig) {
    Write-Host "Note: includes/config.local.php is NOT uploaded (production keeps its own). Use -PushLocalConfig only to overwrite.`n"
} else {
    Write-Warning "Pushing local config.local.php - overwrites production DB credentials on the server."
}

$ssh = @("-i", $IdentityFile, "-p", "$Port", "-o", "StrictHostKeyChecking=accept-new", "${User}@${RemoteHost}", "mkdir -p `"$RemoteBase`"")
& ssh @ssh
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$staging = Join-Path $env:TEMP ("ehr1-cloud-deploy-" + [guid]::NewGuid().ToString())
try {
    New-Item -ItemType Directory -Path $staging -Force | Out-Null
    if ($PushLocalConfig) {
        robocopy $local $staging /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
    } else {
        robocopy $local $staging /E /XF "config.local.php" /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
    }
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy failed with exit code $LASTEXITCODE"
    }
    $repoSqlMysql = Join-Path $RepoRoot "sql\mysql"
    if (Test-Path -LiteralPath $repoSqlMysql) {
        $stagingSql = Join-Path $staging "sql\mysql"
        New-Item -ItemType Directory -Path $stagingSql -Force | Out-Null
        robocopy $repoSqlMysql $stagingSql *.sql /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
        if ($LASTEXITCODE -ge 8) {
            throw "robocopy sql/mysql failed with exit code $LASTEXITCODE"
        }
        Write-Host "Staged sql/mysql (*.sql) for remote migrate scripts.`n"
    }

    $scpArgs = @("-i", $IdentityFile, "-P", "$Port", "-o", "StrictHostKeyChecking=accept-new", "-r", "$staging\*", "${User}@${RemoteHost}:$RemoteBase/")
    & scp @scpArgs
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
    Remove-Item -LiteralPath $staging -Recurse -Force -ErrorAction SilentlyContinue
}

$ht = Join-Path $local ".htaccess"
if (Test-Path $ht) {
    & scp @("-i", $IdentityFile, "-P", "$Port", "-o", "StrictHostKeyChecking=accept-new", $ht, "${User}@${RemoteHost}:$RemoteBase/.htaccess")
}

$remoteChmod = "chmod 755 `"$RemoteBase`" `"$RemoteBase/includes`" `"$RemoteBase/tools`" `"$RemoteBase/reports`" `"$RemoteBase/assets`" 2>/dev/null; chmod -R a+rX `"$RemoteBase/assets`" 2>/dev/null; exit 0"
& ssh @("-i", $IdentityFile, "-p", "$Port", "-o", "StrictHostKeyChecking=accept-new", "${User}@${RemoteHost}", $remoteChmod)
exit 0
