# Ensure C:\Z-Data\Data Project exists and optionally copy/sync from another folder.
# Run from anywhere (double-click or PowerShell). See EHR1-Full-Data.md -> Local working folder.
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\deploy\Use-ZData-WorkingFolder.ps1
#   powershell -ExecutionPolicy Bypass -File .\Use-ZData-WorkingFolder.ps1 -SourceRoot "D:\Old\EHR1 Data"

param(
    [string] $SourceRoot = "",
    [switch] $OpenExplorer
)

$ErrorActionPreference = "Stop"
$TargetRoot = "C:\Z-Data\Data Project"
$parent = Split-Path $TargetRoot -Parent

if (-not (Test-Path -LiteralPath $parent)) {
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
}
if (-not (Test-Path -LiteralPath $TargetRoot)) {
    New-Item -ItemType Directory -Path $TargetRoot -Force | Out-Null
    Write-Host "Created $TargetRoot"
}

if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
    if (-not (Test-Path -LiteralPath $SourceRoot)) {
        Write-Error "SourceRoot not found: $SourceRoot"
    }
    Write-Host "Save All in Cursor (so Google Drive files are on disk), then:"
    Write-Host "  Robocopy from:  $SourceRoot"
    Write-Host "  To:             $TargetRoot"
    Write-Host "Excluding: .git, .cursor, Data Originals (optional second pass for Data Originals)"
    $ok = Read-Host "Run robocopy now? [y/N]"
    if ($ok -eq "y" -or $ok -eq "Y") {
        & robocopy $SourceRoot $TargetRoot /E /XD ".git" ".cursor" /NFL /NDL /NJH /NJS /nc /ns /np
        if ($LASTEXITCODE -ge 8) { Write-Error "robocopy failed: $LASTEXITCODE" }
        $ok2 = Read-Host "Also copy Data Originals? [y/N]"
        if ($ok2 -eq "y" -or $ok2 -eq "Y") {
            $srcDo = Join-Path $SourceRoot "Data Originals"
            $dstDo = Join-Path $TargetRoot "Data Originals"
            if (Test-Path -LiteralPath $srcDo) {
                & robocopy $srcDo $dstDo /E /NFL /NDL /NJH /NJS /nc /ns /np
            }
        }
    }
} else {
    Write-Host "Target ready: $TargetRoot"
    Write-Host "Next: git clone https://github.com/Rossehr1/ehr1.cloud.git `"$TargetRoot`""
    Write-Host "  (empty folder only), or copy from your old project with -SourceRoot."
}

$readme = Join-Path $TargetRoot "README-Z-DATA.txt"
@"
EHR1 Data - canonical working folder
====================================
Root: $TargetRoot

1. In Cursor: File -> Open Folder -> this path.
2. Git/Deploy: run scripts from deploy\ under this folder (not from Google Drive alone).
3. See EHR1-Full-Data.md in the repo -> Local working folder (Windows).

"@ | Set-Content -LiteralPath $readme -Encoding UTF8
Write-Host "Wrote $readme"

if ($OpenExplorer) {
    Start-Process explorer.exe $TargetRoot
}
