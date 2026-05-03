# Loads optional deploy/.env.deploy (gitignored). Never prints values.
# Keys (aliases in parentheses):
#   EHR1_DEPLOY_SSH_IDENTITY_FILE (SSH_IDENTITY_FILE)
#   EHR1_DEPLOY_SSH_HOST (SSH_HOST) — optional override of deploy-paths.json RemoteHost
#   EHR1_DEPLOY_SSH_PORT (SSH_PORT)
#   EHR1_DEPLOY_SSH_USER (SSH_USER)
# Alternate file: set process env EHR1_DEPLOY_ENV_FILE to an absolute path.
param(
    [Parameter(Mandatory = $true)]
    [string] $DeployRoot,
    [string] $EnvFile = ""
)
$ErrorActionPreference = "Stop"

$p = if (-not [string]::IsNullOrWhiteSpace($EnvFile)) {
    $EnvFile
} elseif (-not [string]::IsNullOrWhiteSpace($env:EHR1_DEPLOY_ENV_FILE)) {
    $env:EHR1_DEPLOY_ENV_FILE
} else {
    Join-Path $DeployRoot ".env.deploy"
}

$result = [ordered]@{
    IdentityFile = [string]$null
    RemoteHost   = [string]$null
    Port         = [string]$null
    User         = [string]$null
}

if (-not (Test-Path -LiteralPath $p)) {
    return [pscustomobject]$result
}

Get-Content -LiteralPath $p -Encoding UTF8 | ForEach-Object {
    $line = $_.Trim()
    if ($line -match '^\s*#' -or $line -eq '') { return }
    $eq = $line.IndexOf('=')
    if ($eq -lt 1) { return }
    $key = $line.Substring(0, $eq).Trim()
    $val = $line.Substring($eq + 1).Trim()
    if ($val.Length -ge 2 -and $val.StartsWith('"') -and $val.EndsWith('"')) {
        $val = $val.Trim('"')
    }
    switch -Regex ($key) {
        '^(EHR1_DEPLOY_SSH_IDENTITY_FILE|SSH_IDENTITY_FILE)$' { $result.IdentityFile = $val; break }
        '^(EHR1_DEPLOY_SSH_HOST|SSH_HOST)$' { $result.RemoteHost = $val; break }
        '^(EHR1_DEPLOY_SSH_PORT|SSH_PORT)$' { $result.Port = $val; break }
        '^(EHR1_DEPLOY_SSH_USER|SSH_USER)$' { $result.User = $val; break }
    }
}

return [pscustomobject]$result
