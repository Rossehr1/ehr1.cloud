# Interactive SSH to VPS (optional: key from deploy/.env.deploy — never commit that file).
# Run from PowerShell:
#   powershell -ExecutionPolicy Bypass -File .\Connect-VpsRoot.ps1
# Or use Connect-VpsRoot.cmd (host/user are fixed in the .cmd — prefer this .ps1 for .env.deploy).
#
# Optional deploy/.env.deploy keys: EHR1_DEPLOY_SSH_HOST, EHR1_DEPLOY_SSH_USER, EHR1_DEPLOY_SSH_IDENTITY_FILE
# See deploy/env.deploy.example
#
# Paths: deploy/deploy-paths.json — README-DEPLOY.txt — Prepare-NewVpsDeploy.ps1
#
# After login (EP PAID DDL example):
#   mysql -u root -p YOUR_DATABASE < /path/to/07_archive_supplemental.sql
# SQL files: repo sql/mysql/

param(
    [string] $VpsHost = "",
    [string] $User = "",
    [string] $IdentityFile = ""
)

$ErrorActionPreference = "Stop"
$DeployRoot = $PSScriptRoot
$sec = & (Join-Path $DeployRoot "Read-DeploySecrets.ps1") -DeployRoot $DeployRoot
$cfg = & (Join-Path $DeployRoot "Read-DeployPaths.ps1") -DeployRoot $DeployRoot
if ([string]::IsNullOrWhiteSpace($VpsHost)) {
    $VpsHost = if ($sec.RemoteHost) { $sec.RemoteHost } else { $cfg.RemoteHost }
}
if ([string]::IsNullOrWhiteSpace($User)) {
    $User = if ($sec.User) { $sec.User } else { $cfg.User }
}
if (-not $PSBoundParameters.ContainsKey('IdentityFile') -and $sec.IdentityFile) {
    $IdentityFile = $sec.IdentityFile
}

Write-Host ""
Write-Host "Connecting: ${User}@${VpsHost}"
if ($IdentityFile) {
    if (-not (Test-Path -LiteralPath $IdentityFile)) {
        Write-Error "SSH identity file not found: $IdentityFile"
    }
    Write-Host "Using identity file (key-based auth)."
} else {
    Write-Host "OpenSSH will prompt for password if no key is offered. Ctrl+C to cancel."
}
Write-Host ""
$null = Read-Host "Press Enter to run ssh"

$sshArgs = @("-tt", "-o", "StrictHostKeyChecking=accept-new")
if ($IdentityFile) {
    $sshArgs = @("-i", $IdentityFile) + $sshArgs
}
$sshArgs += "${User}@${VpsHost}"
& ssh @sshArgs
