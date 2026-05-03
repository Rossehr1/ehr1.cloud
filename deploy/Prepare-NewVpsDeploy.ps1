# After cloning the repo: verify paths, SSH key, and print the exact deploy sequence for a new VPS (ehr1.cloud).
# Does not run SSH by itself (no surprise connections). Copies deploy-paths.example.json -> deploy-paths.json only if the latter is missing.
# Usage (from deploy):
#   powershell -ExecutionPolicy Bypass -File .\Prepare-NewVpsDeploy.ps1
#   powershell -ExecutionPolicy Bypass -File .\Prepare-NewVpsDeploy.ps1 -Profile ehr1.cloud

param(
    [string] $Profile = ""
)

$ErrorActionPreference = "Stop"
$DeployRoot = $PSScriptRoot
$cfgPath = Join-Path $DeployRoot "deploy-paths.json"
$examplePath = Join-Path $DeployRoot "deploy-paths.example.json"

if (-not (Test-Path -LiteralPath $cfgPath)) {
    if (-not (Test-Path -LiteralPath $examplePath)) {
        Write-Error "Missing $cfgPath and $examplePath"
    }
    Copy-Item -LiteralPath $examplePath -Destination $cfgPath
    Write-Host "Created deploy-paths.json from deploy-paths.example.json"
    Write-Host "  Edit RemoteHost and PublicDnsARecordIPv4 to your NEW VPS public IPv4."
    Write-Host ""
} else {
    Write-Host "Using existing deploy-paths.json - confirm RemoteHost and PublicDnsARecordIPv4 match the NEW VPS."
    Write-Host ""
}

$cfg = & (Join-Path $DeployRoot "Read-DeployPaths.ps1") -DeployRoot $DeployRoot -Profile $Profile
$sec = & (Join-Path $DeployRoot "Read-DeploySecrets.ps1") -DeployRoot $DeployRoot
$key = $(Join-Path $env:USERPROFILE ".ssh\ehr1_vps_ed25519")
if ($sec.IdentityFile) { $key = $sec.IdentityFile }

$sshHost = if ($sec.RemoteHost) { $sec.RemoteHost } else { $cfg.RemoteHost }
$sshPort = [int]$cfg.Port
if ($sec.Port) {
    $pSec = 0
    if ([int]::TryParse($sec.Port, [ref]$pSec) -and $pSec -gt 0) { $sshPort = $pSec }
}
$sshUser = if ($sec.User) { $sec.User } else { $cfg.User }

if (-not (Test-Path -LiteralPath $key)) {
    Write-Warning "SSH private key not found: $key"
    Write-Warning "Run: powershell -ExecutionPolicy Bypass -File .\Install-Ehr1SshKey.ps1 -NoPassphrase"
} else {
    Write-Host "SSH key: $key"
}

$hostLine = "ssh -i `"$key`" -p $sshPort ${sshUser}@${sshHost}"
Write-Host "Profile: $($cfg.DeployProfile)  Domain: $($cfg.Domain)"
Write-Host "RemotePublicHtml: $($cfg.RemotePublicHtml)"
Write-Host "RemoteAppDir:     $($cfg.RemoteAppDir)"
Write-Host ""
Write-Host "Test SSH (optional):"
Write-Host "  $hostLine"
Write-Host ""

Write-Host "=== New VPS + ehr1.cloud (order) ==="
Write-Host "Step 1 - DNS: A/AAAA for ehr1.cloud -> $($cfg.PublicDnsARecordIPv4) (panel vhost docroot = $($cfg.RemotePublicHtml) if using a panel)."
Write-Host ""
Write-Host "Step 2 - One-time key to VPS (from this folder):"
Write-Host "  powershell -ExecutionPolicy Bypass -File .\Install-Ehr1SshKey.ps1 -NoPassphrase"
Write-Host ""
Write-Host "Step 3 - Optional: copy env.deploy.example -> .env.deploy for SSH overrides (never commit)."
Write-Host ""
Write-Host "Step 4 - First-time on server (pick one):"
Write-Host "  CloudPanel / existing PHP+DB:  powershell -ExecutionPolicy Bypass -File .\Deploy-Ehr1ToVps.ps1 -RemoteMinimalLayout"
Write-Host "  Plain Ubuntu (no panel):       powershell -ExecutionPolicy Bypass -File .\Deploy-Ehr1ToVps.ps1 -RemoteBootstrap -BootstrapOnly"
Write-Host ""
Write-Host "Step 5 - Create MySQL DB + app user; add includes/config.local.php on the server (from config.vps-db.example.php)."
Write-Host ""
Write-Host "Step 6 - Upload full site + app:"
Write-Host "  powershell -ExecutionPolicy Bypass -File .\Deploy-Ehr1ToVps.ps1"
Write-Host ""
Write-Host "Step 7 - On VPS:  cd $($cfg.RemoteAppDir) ; php tools/install_schema.php"
Write-Host ""
Write-Host "Step 8 - TLS (e.g. certbot) for ehr1.cloud when DNS is live."
Write-Host ""
