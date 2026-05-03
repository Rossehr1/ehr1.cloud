@echo off
REM Install SSH key: powershell -ExecutionPolicy Bypass -File Install-Ehr1SshKey.ps1 -NoPassphrase
REM Set VPS (and USER) to match deploy\deploy-paths.json — or use Connect-VpsRoot.ps1 (reads JSON / .env.deploy)
REM For host/user/key from deploy\.env.deploy use: powershell -ExecutionPolicy Bypass -File Connect-VpsRoot.ps1
REM Full walkthrough + automation: deploy\ehr1-cloud-app\README-DEPLOY.txt — Deploy-Ehr1ToVps.ps1
REM Interactive SSH to VPS — see deploy\ehr1-cloud-app\README-DEPLOY.txt and deploy\deploy-paths.json
REM No PowerShell execution policy — double-click or run from cmd.exe
set VPS=177.7.52.88
set USER=root
echo.
echo Connecting %USER%@%VPS% — type password when SSH asks (nothing echoes).
echo.
ssh -tt -o StrictHostKeyChecking=accept-new %USER%@%VPS%
echo.
pause
