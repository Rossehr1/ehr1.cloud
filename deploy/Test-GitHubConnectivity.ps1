# Verify network + GitHub reachability and (if Git is installed) read access to the remote.
# Does not use your GitHub token; public API + optional git ls-remote.
# Usage (from deploy):
#   powershell -ExecutionPolicy Bypass -File .\Test-GitHubConnectivity.ps1

param(
    [string] $Owner = "Rossehr1",
    [string] $Repo = "ehr1.cloud",
    [string] $RepoRoot = ""
)

$ErrorActionPreference = "Continue"

if ([string]::IsNullOrWhiteSpace($RepoRoot)) {
    $RepoRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot "..")).Path
}

function Get-GitExe {
    $cmd = Get-Command git -CommandType Application -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    foreach ($p in @(
            (Join-Path $env:ProgramFiles "Git\cmd\git.exe"),
            (Join-Path $env:ProgramFiles "Git\bin\git.exe"),
            (Join-Path ${env:ProgramFiles(x86)} "Git\cmd\git.exe"),
            (Join-Path $env:LocalAppData "Programs\Git\cmd\git.exe")
        )) {
        if ($p -and (Test-Path -LiteralPath $p)) { return $p }
    }
    return $null
}

Write-Host "=== EHR1 GitHub connectivity ===" 
Write-Host ""

Write-Host "[1] HTTPS api.github.com ..."
try {
    $null = Invoke-RestMethod -Uri "https://api.github.com/zen" -Headers @{"User-Agent"="EHR1-connectivity-check"} -TimeoutSec 20
    Write-Host "    OK (reached GitHub API)" -ForegroundColor Green
} catch {
    Write-Host "    FAIL: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "[2] Repo metadata: $Owner/$Repo ..."
try {
    $meta = Invoke-RestMethod -Uri "https://api.github.com/repos/$Owner/$Repo" -Headers @{"User-Agent"="EHR1-connectivity-check"} -TimeoutSec 20
    Write-Host "    OK  full_name: $($meta.full_name)" -ForegroundColor Green
    Write-Host "    default_branch: $($meta.default_branch)  (use this branch in Init-And-Push-GitHub.ps1 -Branch if pushing the first time)"
    Write-Host "    clone_url:      $($meta.clone_url)"
} catch {
    Write-Host "    FAIL: $($_.Exception.Message)" -ForegroundColor Red
    $meta = $null
}

Write-Host "[3] HTTPS repo page https://github.com/$Owner/$Repo ..."
try {
    $r = Invoke-WebRequest -Uri "https://github.com/$Owner/$Repo" -UseBasicParsing -TimeoutSec 20
    Write-Host "    OK (HTTP $($r.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "    FAIL: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "[4] SSH git@github.com (port 22; BatchMode, no key required to test reachability) ..."
$ssh = Get-Command ssh -CommandType Application -ErrorAction SilentlyContinue
if (-not $ssh) {
    Write-Host "    SKIP (OpenSSH ssh not on PATH)" -ForegroundColor Yellow
} else {
    $batch = Join-Path $env:TEMP "ehr1-ssh-github.cmd"
    @"
@echo off
ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15 -T git@github.com 2>&1
exit /b %ERRORLEVEL%
"@ | Set-Content -LiteralPath $batch -Encoding ASCII
    $raw = & cmd.exe /c "`"$batch`"" 2>&1
    $t = ($raw | Out-String).Trim()
    Remove-Item -LiteralPath $batch -Force -ErrorAction SilentlyContinue
    if ($t -match "successfully authenticated|Hi ") {
        Write-Host "    OK SSH auth to GitHub." -ForegroundColor Green
        Write-Host "    $t"
    } elseif ($t -match "Permission denied|publickey") {
        Write-Host "    OK host reachable; GitHub rejected key (expected until you add a GitHub SSH key). Use HTTPS for Git or add SSH keys." -ForegroundColor Yellow
    } elseif ($t.Length -gt 0) {
        Write-Host "    $t" -ForegroundColor Yellow
    } else {
        Write-Host "    Completed with no output (check firewall if pushes over SSH fail)." -ForegroundColor Yellow
    }
}

Write-Host "[5] Git executable ..."
$git = Get-GitExe
if (-not $git) {
    Write-Host "    NOT FOUND - install Git for Windows: https://git-scm.com/download/win" -ForegroundColor Yellow
    Write-Host "    Then re-run this script; use Init-And-Push-GitHub.ps1 for push."
} else {
    Write-Host "    OK $(($(& $git --version) | Out-String).Trim())" -ForegroundColor Green
    $httpsUrl = "https://github.com/$Owner/$Repo.git"
    Write-Host '[6] git ls-remote (read-only listing; no auth for public repo) ...'
    $lr = & $git ls-remote $httpsUrl 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "    OK (refs from remote)" -ForegroundColor Green
        ($lr | Select-Object -First 6) | ForEach-Object { Write-Host "    $_" }
        if (($lr | Measure-Object -Line).Lines -gt 6) { Write-Host "    ..." }
    } else {
        Write-Host "    FAIL: $lr" -ForegroundColor Red
    }
}

$gitDir = Join-Path $RepoRoot ".git"
Write-Host ""
Write-Host "Local repo: $RepoRoot"
Write-Host ".git present: $(Test-Path -LiteralPath $gitDir)"
if ($meta -and $meta.default_branch) {
    Write-Host ""
    Write-Host ('Tip: GitHub default branch is "' + $meta.default_branch + '". First push aligns with:')
    Write-Host ('  powershell -ExecutionPolicy Bypass -File .\Init-And-Push-GitHub.ps1 -Branch ' + $meta.default_branch)
}
