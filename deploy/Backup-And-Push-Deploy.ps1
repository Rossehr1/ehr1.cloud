# One-shot: optional local folder backup (excludes Data Originals), Git commit+push if Git is available, then VPS deploy.
# Run from deploy/:
#   powershell -ExecutionPolicy Bypass -File .\Backup-And-Push-Deploy.ps1
#   powershell -ExecutionPolicy Bypass -File .\Backup-And-Push-Deploy.ps1 -SkipGit -SkipBackup
# Git: installs to PATH or standard locations. Push still needs your GitHub login (credential manager / PAT / SSH).
# Deploy uses SSH key (Install-Ehr1SshKey.ps1 once). If deploy hangs, copy the repo to a local SSD path and re-run.

param(
    [switch] $SkipBackup,
    [switch] $SkipGit,
    [switch] $SkipDeploy,
    [string] $CommitMessage = "Sync: production deploy",
    [switch] $PullRebaseBeforePush,
    [switch] $AllowUnrelatedHistories
)

$ErrorActionPreference = "Stop"
$DeployRoot = $PSScriptRoot
$RepoRoot = (Resolve-Path -LiteralPath (Join-Path $DeployRoot "..")).Path

function Get-GitExe {
    $cmd = Get-Command git -CommandType Application -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    foreach ($p in @(
            (Join-Path $env:ProgramFiles "Git\bin\git.exe"),
            (Join-Path ${env:ProgramFiles(x86)} "Git\bin\git.exe"),
            (Join-Path $env:LocalAppData "Programs\Git\bin\git.exe")
        )) {
        if ($p -and (Test-Path -LiteralPath $p)) { return $p }
    }
    return $null
}

if (-not $SkipBackup) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $parent = Join-Path $env:USERPROFILE "Documents\EHR1-Data-Backups"
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
    $dest = Join-Path $parent "ehr1-repo-backup-$stamp"
    Write-Host "Robocopy backup (excluding Data Originals) to:`n  $dest"
    & robocopy $RepoRoot $dest /E /XD "Data Originals" /NFL /NDL /NJH /NJS /nc /ns /np
    if ($LASTEXITCODE -ge 8) {
        Write-Error "robocopy failed with exit code $LASTEXITCODE"
    }
    Write-Host "Backup done (robocopy uses 0-7 as success; see robocopy docs).`n"
}

$gitExe = Get-GitExe
if (-not $SkipGit) {
    if (-not $gitExe) {
        Write-Warning "Git not found. Install Git for Windows, then run Init-And-Push-GitHub.ps1 or re-run this script."
    } else {
        Write-Host "Using Git: $gitExe"
        Push-Location -LiteralPath $RepoRoot
        try {
            if (-not (Test-Path -LiteralPath ".git")) {
                & $gitExe @("init", "-b", "master")
                if ($LASTEXITCODE -ne 0) { throw "git init failed" }
            }
            $headRef = (& $gitExe @("rev-parse", "--abbrev-ref", "HEAD")).Trim()
            if ([string]::IsNullOrWhiteSpace($headRef) -or $headRef -eq "HEAD") {
                & $gitExe @("checkout", "-b", "master")
                $headRef = "master"
            }
            $skipPushOut = $false
            & $gitExe @("add", "-A")
            $dirty = (& $gitExe @("status", "--porcelain"))
            if ($dirty) {
                & $gitExe @("commit", "-m", $CommitMessage)
                if ($LASTEXITCODE -ne 0) {
                    Write-Warning "git commit failed; set user.name / user.email if needed. Skipping push."
                    $skipPushOut = $true
                }
            } else {
                Write-Host "Git: nothing to commit."
            }
            if (-not $skipPushOut) {
                $remoteUrl = "https://github.com/Rossehr1/ehr1.cloud.git"
                & $gitExe @("remote", "get-url", "origin") 2>$null | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    & $gitExe @("remote", "set-url", "origin", $remoteUrl)
                } else {
                    & $gitExe @("remote", "add", "origin", $remoteUrl)
                }
                if ($PullRebaseBeforePush) {
                    & $gitExe @("fetch", "origin")
                    $remoteHead = & $gitExe @("ls-remote", "--heads", "origin", $headRef)
                    if ($remoteHead) {
                        if ($AllowUnrelatedHistories) {
                            Write-Host "Merging origin/$headRef with --allow-unrelated-histories (no rebase; safer on Google Drive / locked .cursor)..."
                            & $gitExe @("pull", "origin", $headRef, "--allow-unrelated-histories", "--no-rebase", "--no-edit")
                        } else {
                            & $gitExe @("pull", "--rebase", "origin", $headRef)
                        }
                        if ($LASTEXITCODE -ne 0) {
                            Write-Warning "git pull failed. Resolve conflicts, or re-run with -AllowUnrelatedHistories for merge instead of rebase."
                        }
                    }
                }
                & $gitExe @("push", "-u", "origin", $headRef)
                if ($LASTEXITCODE -ne 0) {
                    Write-Warning "git push failed (auth or diverged branch). Run: powershell -File deploy\Init-And-Push-GitHub.ps1 -PullRebaseBeforePush"
                }
            }
        } finally {
            Pop-Location
        }
        Write-Host ""
    }
}

if (-not $SkipDeploy) {
    Write-Host "Starting Deploy-Ehr1ToVps.ps1 (site root + app)..."
    & (Join-Path $DeployRoot "Deploy-Ehr1ToVps.ps1")
}

Write-Host "`nIf deploy hung: copy repo to a local (non-Google-Drive) folder, open deploy there, run Deploy-Ehr1ToVps.ps1 again."
