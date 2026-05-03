# Loads deploy/hostinger-paths.json. Call with -DeployRoot set to the deploy/ folder.
# New format: ActiveDeployProfile + Profiles.{name}. Old flat JSON (no Profiles) still works.
param(
    [Parameter(Mandatory = $true)]
    [string] $DeployRoot,
    [string] $Profile = ""
)
$path = Join-Path $DeployRoot "hostinger-paths.json"
if (-not (Test-Path -LiteralPath $path)) {
    Write-Error "Missing $path - create it or fix DeployRoot."
}
$raw = Get-Content -LiteralPath $path -Raw -Encoding UTF8 | ConvertFrom-Json

$hasProfiles = $false
foreach ($p in $raw.PSObject.Properties) {
    if ($p.Name -eq "Profiles" -and $p.Value -ne $null) { $hasProfiles = $true; break }
}

if (-not $hasProfiles) {
    return $raw
}

$name = $Profile
if ([string]::IsNullOrWhiteSpace($name)) {
    $name = [string]$raw.ActiveDeployProfile
}
if ([string]::IsNullOrWhiteSpace($name)) {
    Write-Error "hostinger-paths.json: set ActiveDeployProfile or pass -Profile."
}

$profObj = $null
foreach ($p in $raw.Profiles.PSObject.Properties) {
    if ($p.Name -eq $name) {
        $profObj = $p.Value
        break
    }
}
if ($null -eq $profObj) {
    $valid = ($raw.Profiles.PSObject.Properties | ForEach-Object { $_.Name }) -join ", "
    Write-Error "Unknown profile '$name'. Valid profiles: $valid"
}

[pscustomobject]@{
    RemoteHost              = $raw.RemoteHost
    Port                    = [int]$raw.Port
    User                    = $raw.User
    Domain                  = $profObj.Domain
    RemotePublicHtml        = $profObj.RemotePublicHtml
    RemoteAppDir            = $profObj.RemoteAppDir
    PublicDnsARecordIPv4    = $raw.PublicDnsARecordIPv4
    DeployProfile           = $name
}
