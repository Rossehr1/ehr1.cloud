# PowerShell script to deploy website files to Hostinger via FTP
# Usage: .\deploy-to-hostinger.ps1

param(
    [Parameter(Mandatory=$true)]
    [string]$FtpHost,
    
    [Parameter(Mandatory=$true)]
    [string]$FtpUsername,
    
    [Parameter(Mandatory=$true)]
    [string]$FtpPassword
)

# Files to upload
$filesToUpload = @(
    "index.html",
    "about.html",
    "styles.css"
)

Write-Host "Deploying to Hostinger via FTP..." -ForegroundColor Green
Write-Host "Host: $FtpHost" -ForegroundColor Cyan
Write-Host "Username: $FtpUsername" -ForegroundColor Cyan

# Create FTP request function
function Upload-File {
    param(
        [string]$LocalFile,
        [string]$RemoteFile,
        [string]$FtpHost,
        [string]$FtpUsername,
        [string]$FtpPassword
    )
    
    try {
        $ftpUri = "ftp://$FtpHost/public_html/$RemoteFile"
        $ftpRequest = [System.Net.FtpWebRequest]::Create($ftpUri)
        $ftpRequest.Credentials = New-Object System.Net.NetworkCredential($FtpUsername, $FtpPassword)
        $ftpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $ftpRequest.UseBinary = $true
        $ftpRequest.UsePassive = $true
        
        $fileContent = [System.IO.File]::ReadAllBytes($LocalFile)
        $ftpRequest.ContentLength = $fileContent.Length
        
        $requestStream = $ftpRequest.GetRequestStream()
        $requestStream.Write($fileContent, 0, $fileContent.Length)
        $requestStream.Close()
        
        $response = $ftpRequest.GetResponse()
        Write-Host "✓ Uploaded: $RemoteFile" -ForegroundColor Green
        $response.Close()
        
        return $true
    }
    catch {
        Write-Host "✗ Failed to upload $RemoteFile : $_" -ForegroundColor Red
        return $false
    }
}

# Upload each file
foreach ($file in $filesToUpload) {
    if (Test-Path $file) {
        Upload-File -LocalFile $file -RemoteFile $file -FtpHost $FtpHost -FtpUsername $FtpUsername -FtpPassword $FtpPassword
    }
    else {
        Write-Host "✗ File not found: $file" -ForegroundColor Yellow
    }
}

Write-Host "`nDeployment complete! Visit https://ehr1.cloud to verify." -ForegroundColor Green
