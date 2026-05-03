# Upload site root: *.html, assets/, and .htaccess to public_html for the active deploy profile.
# Optional SSH settings: deploy/.env.deploy (see deploy/env.deploy.example).
# Usage: .\deploy-site-root.ps1
#        .\deploy-site-root.ps1 -Profile ehr1.cloud

param(
    [string] $RemoteHost = "",
    [int] $Port = 0,
    [string] $User = "",
    [string] $RemotePublicHtml = "",
    [string] $Profile = "",
    [string] $IdentityFile = $(Join-Path $env:USERPROFILE ".ssh\ehr1_vps_ed25519")
)

$ErrorActionPreference = "Stop"
$DeployRoot = Split-Path $PSScriptRoot -Parent
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
if ([string]::IsNullOrWhiteSpace($RemotePublicHtml)) { $RemotePublicHtml = $cfg.RemotePublicHtml }

$local = $PSScriptRoot

if (-not (Test-Path $IdentityFile)) {
    Write-Error "Missing key: $IdentityFile"
}

$htmlFiles = @(Get-ChildItem -Path $local -Filter "*.html" -File)
if ($htmlFiles.Count -eq 0) {
    Write-Error "No .html files in $local"
}

Write-Host "Uploading site root to ${User}@${RemoteHost}:$RemotePublicHtml"
if ($cfg.PSObject.Properties['DeployProfile']) {
    Write-Host "  profile: $($cfg.DeployProfile) ($($cfg.Domain))`n"
} else {
    Write-Host ""
}

foreach ($f in $htmlFiles) {
    Write-Host "  $($f.Name)"
    & scp @("-i", $IdentityFile, "-P", "$Port", "-o", "StrictHostKeyChecking=accept-new", $f.FullName, "${User}@${RemoteHost}:$RemotePublicHtml/$($f.Name)")
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

$ht = Join-Path $local ".htaccess"
if (Test-Path $ht) {
    Write-Host "  .htaccess"
    & scp @("-i", $IdentityFile, "-P", "$Port", "-o", "StrictHostKeyChecking=accept-new", $ht, "${User}@${RemoteHost}:$RemotePublicHtml/.htaccess")
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

$assetsDir = Join-Path $local "assets"
if (Test-Path -LiteralPath $assetsDir) {
    $assetFiles = @(Get-ChildItem -LiteralPath $assetsDir -Recurse -File -ErrorAction SilentlyContinue)
    if ($assetFiles.Count -gt 0) {
        Write-Host "  assets/ ($($assetFiles.Count) files)"
        & ssh @("-i", $IdentityFile, "-p", "$Port", "-o", "StrictHostKeyChecking=accept-new", "${User}@${RemoteHost}", "mkdir -p `"$RemotePublicHtml/assets`"")
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
        & scp @("-i", $IdentityFile, "-P", "$Port", "-o", "StrictHostKeyChecking=accept-new", "-r", $assetsDir, "${User}@${RemoteHost}:$RemotePublicHtml/")
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    }
}

$repair = @"
chmod 755 `"$RemotePublicHtml`" 2>/dev/null
find `"$RemotePublicHtml`" -maxdepth 1 -name '*.html' -exec chmod 644 {} \; 2>/dev/null
test -f `"$RemotePublicHtml/.htaccess`" && chmod 644 `"$RemotePublicHtml/.htaccess`" 2>/dev/null
if [ -d `"$RemotePublicHtml/assets`" ]; then
  find `"$RemotePublicHtml/assets`" -type d -exec chmod 755 {} \; 2>/dev/null
  find `"$RemotePublicHtml/assets`" -type f -exec chmod 644 {} \; 2>/dev/null
fi
chmod -R u+rwX,go+rX `"$($cfg.RemoteAppDir)`" 2>/dev/null
exit 0
"@
& ssh @("-i", $IdentityFile, "-p", "$Port", "-o", "StrictHostKeyChecking=accept-new", "${User}@${RemoteHost}", $repair)
exit 0
