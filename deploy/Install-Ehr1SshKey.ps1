# Generate an Ed25519 key for VPS deploy/SSH (if missing) and append the public key to the server authorized_keys.
# Run from the deploy folder:
#   powershell -ExecutionPolicy Bypass -File .\Install-Ehr1SshKey.ps1
#
# Uses deploy/.env.deploy + deploy-paths.json (same as Deploy-Ehr1ToVps.ps1). You get one SSH password
# prompt (unless key auth already works) when appending authorized_keys.
#
# -NoPassphrase: new keys have an empty passphrase (good for unattended deploys - protect the .ssh folder).
# -SkipInstall: only create/list the key; print the .pub for manual paste.
# -ForceRegenerate: delete existing key files and generate new (destructive).

param(
    [string] $Profile = "",
    [string] $IdentityFile = $(Join-Path $env:USERPROFILE ".ssh\ehr1_vps_ed25519"),
    [switch] $NoPassphrase,
    [switch] $SkipInstall,
    [switch] $ForceRegenerate
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

$pubPath = "${IdentityFile}.pub"

if ($ForceRegenerate) {
    Remove-Item -LiteralPath $IdentityFile, $pubPath -Force -ErrorAction SilentlyContinue
}

if (-not (Test-Path -LiteralPath $IdentityFile)) {
    $keyDir = Split-Path -LiteralPath $IdentityFile -Parent
    if (-not (Test-Path -LiteralPath $keyDir)) {
        New-Item -ItemType Directory -Path $keyDir -Force | Out-Null
    }
    Write-Host "Generating Ed25519 key: $IdentityFile"
    if ($NoPassphrase) {
        $emptyPass = [string]::Empty
        & ssh-keygen -t ed25519 -f $IdentityFile -q -N $emptyPass -C "ehr1-data-deploy-vps"
    } else {
        & ssh-keygen -t ed25519 -f $IdentityFile -C "ehr1-data-deploy-vps"
    }
    if ($LASTEXITCODE -ne 0) {
        Write-Error "ssh-keygen failed (exit $LASTEXITCODE)."
    }
} else {
    Write-Host "Using existing private key: $IdentityFile"
}

if (-not (Test-Path -LiteralPath $pubPath)) {
    Write-Error "Missing public key: $pubPath"
}

Write-Host ""
Write-Host "Public key file: $pubPath"
& ssh-keygen -lf $pubPath
Write-Host ""

if ($SkipInstall) {
    Write-Host "SkipInstall - paste this line into the server's ~/.ssh/authorized_keys (one line):"
    Write-Host ""
    Write-Host (Get-Content -LiteralPath $pubPath -Raw).TrimEnd()
    Write-Host ""
    exit 0
}

Write-Host "Appending public key on ${sshUser}@${sshHost} port $sshPort ..."
Write-Host "(One password prompt is normal if key login is not set up yet.)"
Write-Host ""

$target = "${sshUser}@${sshHost}"
$remoteCmd = "umask 077; mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"

Get-Content -LiteralPath $pubPath | & ssh -p $sshPort -o StrictHostKeyChecking=accept-new $target $remoteCmd
if ($LASTEXITCODE -ne 0) {
    Write-Warning "Remote install failed (exit $LASTEXITCODE). Add the public key manually:"
    Write-Host (Get-Content -LiteralPath $pubPath -Raw).TrimEnd()
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "Testing key auth (BatchMode)..."
& ssh -i $IdentityFile -p $sshPort -o StrictHostKeyChecking=accept-new -o BatchMode=yes -o ConnectTimeout=15 $target "echo ok"
if ($LASTEXITCODE -ne 0) {
    Write-Warning "BatchMode SSH test failed - if your key has a passphrase, run: ssh-add `"$IdentityFile`""
} else {
    Write-Host "Success. Run Deploy-Ehr1ToVps.ps1 next (no SSH password if key has no passphrase and ssh-agent is not required)."
}
