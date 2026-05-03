# Initialize Git (if needed), set origin to GitHub, commit all tracked files, and push.
# Repo root defaults to the parent of deploy/ (the EHR1 Data project folder).
# Prerequisite: Git on PATH.
# Tracked for GitHub: full deploy/ehr1-cloud-app/ (data application + assets/app.css), deploy/ehr1-cloud-site-root/
# (HTML + assets/*), sql/mysql, tools/, deploy scripts, docs. Excluded: Data Originals/, secrets, config.local.php — .gitignore
# If GitHub already has the main website: use -PullRebaseBeforePush (and -AllowUnrelatedHistories if histories do not share a base).
# Usage (from deploy):
#   powershell -ExecutionPolicy Bypass -File .\Init-And-Push-GitHub.ps1 -PullRebaseBeforePush
#   powershell -ExecutionPolicy Bypass -File .\Init-And-Push-GitHub.ps1 -PullRebaseBeforePush -AllowUnrelatedHistories
#   powershell -ExecutionPolicy Bypass -File .\Init-And-Push-GitHub.ps1 -RemoteUrl git@github.com:Rossehr1/ehr1.cloud.git

param(
    [string] $RepoRoot = "",
    [string] $RemoteUrl = "https://github.com/Rossehr1/ehr1.cloud.git",
    [string] $Branch = "master",
    [string] $CommitMessage = "Sync: data app, deploy, site-root assets, SQL, tools, docs",
    [switch] $SkipPush,
    [switch] $PullRebaseBeforePush,
    [switch] $AllowUnrelatedHistories
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($RepoRoot)) {
    $RepoRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot "..")).Path
}

$gitCmd = Get-Command git -CommandType Application -ErrorAction SilentlyContinue
if (-not $gitCmd) {
    foreach ($gp in @(
            (Join-Path $env:ProgramFiles "Git\cmd\git.exe"),
            (Join-Path $env:ProgramFiles "Git\bin\git.exe"),
            (Join-Path ${env:ProgramFiles(x86)} "Git\cmd\git.exe"),
            (Join-Path $env:LocalAppData "Programs\Git\cmd\git.exe")
        )) {
        if ($gp -and (Test-Path -LiteralPath $gp)) {
            $env:Path = "$(Split-Path -Parent $gp);$env:Path"
            break
        }
    }
    $gitCmd = Get-Command git -CommandType Application -ErrorAction SilentlyContinue
}
if (-not $gitCmd) {
    Write-Error "Git not found. Install Git for Windows or add it to PATH, then reopen the terminal."
}

Push-Location -LiteralPath $RepoRoot
try {
    if (-not (Test-Path -LiteralPath ".git")) {
        Write-Host "Initializing repository at $RepoRoot (branch $Branch)"
        & git @("init", "-b", $Branch)
        if ($LASTEXITCODE -ne 0) { throw "git init failed ($LASTEXITCODE)" }
    }

    $headRef = $Branch
    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = "SilentlyContinue"
    $null = & git rev-parse --verify HEAD 2>&1
    $ErrorActionPreference = $prevEap
    if ($LASTEXITCODE -eq 0) {
        $r = (& git rev-parse --abbrev-ref HEAD).Trim()
        if (-not [string]::IsNullOrWhiteSpace($r) -and $r -ne "HEAD") {
            $headRef = $r
        }
    } else {
        Write-Host "Fresh repo (no commits yet); working branch: $Branch"
    }

    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = "SilentlyContinue"
    $null = & git remote get-url origin 2>&1
    $hasOrigin = ($LASTEXITCODE -eq 0)
    $ErrorActionPreference = $prevEap
    if ($hasOrigin) {
        Write-Host "Setting origin to $RemoteUrl"
        & git remote set-url origin $RemoteUrl
    } else {
        Write-Host "Adding origin $RemoteUrl"
        & git remote add origin $RemoteUrl
    }
    if ($LASTEXITCODE -ne 0) { throw "git remote failed ($LASTEXITCODE)" }

    & git add -A
    if ($LASTEXITCODE -ne 0) { throw "git add failed ($LASTEXITCODE)" }

    $dirty = (& git status --porcelain)
    if (-not $dirty) {
        Write-Host "Nothing to commit (clean or only ignored paths)."
    } else {
        & git commit -m $CommitMessage
        if ($LASTEXITCODE -ne 0) {
            Write-Error "git commit failed. On first use run: git config --global user.name `"Your Name`"; git config --global user.email you@example.com"
        }
    }

    if ($SkipPush) {
        Write-Host "SkipPush: when ready: git push -u origin $headRef"
        exit 0
    }

    if ($PullRebaseBeforePush) {
        Write-Host "Fetching origin (preserve existing GitHub commits)..."
        & git fetch origin
        if ($LASTEXITCODE -ne 0) { throw "git fetch failed ($LASTEXITCODE)" }
        $prevEap2 = $ErrorActionPreference
        $ErrorActionPreference = "SilentlyContinue"
        $remoteHead = (& git ls-remote --heads origin $headRef 2>&1 | Out-String).Trim()
        $ErrorActionPreference = $prevEap2
        if (-not [string]::IsNullOrWhiteSpace($remoteHead)) {
            if ($AllowUnrelatedHistories) {
                Write-Host "Merging origin/$headRef with --allow-unrelated-histories (no rebase; safer on Google Drive / locked .cursor)..."
                & git pull origin $headRef --allow-unrelated-histories --no-rebase --no-edit
                if ($LASTEXITCODE -ne 0) {
                    Write-Error "Merge failed. Resolve conflicts, then: git push -u origin $headRef"
                }
            } else {
                Write-Host "Pulling origin/$headRef with rebase before push..."
                & git pull --rebase origin $headRef
                if ($LASTEXITCODE -ne 0) {
                    Write-Error "git pull --rebase failed. Resolve conflicts, or re-run with -AllowUnrelatedHistories (uses merge instead of rebase)."
                }
            }
        }
    }

    Write-Host "Pushing to origin ($headRef)..."
    & git push -u origin $headRef
    if ($LASTEXITCODE -ne 0) {
        Write-Error "git push failed. Try: -PullRebaseBeforePush, or pull manually, then push. Never force-push unless you intend to rewrite GitHub."
    }
} finally {
    Pop-Location
}
