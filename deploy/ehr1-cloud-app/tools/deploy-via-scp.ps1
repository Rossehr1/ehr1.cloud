# Upload ehr1-cloud-app to Hostinger over SCP (requires SSH key auth - see README-DEPLOY.txt).
# Usage: .\deploy-via-scp.ps1
# Adjust $RemoteBase if your document root path differs (check via SSH: pwd; ls).

param(
    [string] $RemoteHost = "92.112.189.73",
    [int] $Port = 65002,
    [string] $User = "u660126262",
    [string] $RemoteBase = "/home/u660126262/domains/ehr1.cloud/public_html/ehr1-data",
    [string] $IdentityFile = $(Join-Path $env:USERPROFILE ".ssh\ehr1_hostinger_ed25519")
)

$ErrorActionPreference = "Stop"
# Parent of tools/ = deploy/ehr1-cloud-app (folder to upload)
$local = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path $IdentityFile)) {
    Write-Error "Missing key: $IdentityFile - run: ssh-keygen -t ed25519 -f `"$IdentityFile`""
}

if (-not (Test-Path $local)) {
    Write-Error "Missing folder: $local"
}

Write-Host "Uploading from:`n  $local`nto:`n  ${User}@${RemoteHost}:$RemoteBase`n"

# Ensure target exists, then sync contents
$ssh = @("-i", $IdentityFile, "-p", "$Port", "-o", "StrictHostKeyChecking=accept-new", "${User}@${RemoteHost}", "mkdir -p `"$RemoteBase`"")
& ssh @ssh
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$scpArgs = @("-i", $IdentityFile, "-P", "$Port", "-o", "StrictHostKeyChecking=accept-new", "-r", "$local\*", "${User}@${RemoteHost}:$RemoteBase/")
& scp @scpArgs
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

# Windows `*` omits dotfiles — upload .htaccess if present
$ht = Join-Path $local ".htaccess"
if (Test-Path $ht) {
    & scp @("-i", $IdentityFile, "-P", "$Port", "-o", "StrictHostKeyChecking=accept-new", $ht, "${User}@${RemoteHost}:$RemoteBase/.htaccess")
}

& ssh @("-i", $IdentityFile, "-p", "$Port", "-o", "StrictHostKeyChecking=accept-new", "${User}@${RemoteHost}", "chmod 755 `"$RemoteBase`" `"$RemoteBase/includes`" `"$RemoteBase/tools`" `"$RemoteBase/reports`" `"$RemoteBase/assets`" 2>/dev/null; exit 0")
exit 0
