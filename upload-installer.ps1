$ftpHost = "92.112.189.73"
$ftpUsername = "u660126262.ehr1.cloud"
$ftpPassword = "yodApodA911**"

function Upload-File {
    param(
        [string]$LocalFile,
        [string]$RemoteFile
    )
    try {
        $ftpUri = "ftp://$ftpHost/$RemoteFile"
        Write-Host "Uploading: $LocalFile -> $ftpUri" -ForegroundColor Cyan
        Write-Host "File size: $([math]::Round((Get-Item $LocalFile).Length / 1MB, 2)) MB" -ForegroundColor Gray
        
        $ftpRequest = [System.Net.FtpWebRequest]::Create($ftpUri)
        $ftpRequest.Credentials = New-Object System.Net.NetworkCredential($ftpUsername, $ftpPassword)
        $ftpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $ftpRequest.UseBinary = $true
        $ftpRequest.UsePassive = $true
        $ftpRequest.Timeout = 300000
        
        $fileContent = [System.IO.File]::ReadAllBytes($LocalFile)
        $ftpRequest.ContentLength = $fileContent.Length
        
        $requestStream = $ftpRequest.GetRequestStream()
        $requestStream.Write($fileContent, 0, $fileContent.Length)
        $requestStream.Close()
        
        $response = $ftpRequest.GetResponse()
        Write-Host "Successfully uploaded: $RemoteFile" -ForegroundColor Green
        $response.Close()
        return $true
    }
    catch {
        Write-Host "Error uploading $RemoteFile : $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

Write-Host "Starting upload to Hostinger..." -ForegroundColor Yellow
Write-Host ""

$files = @(
    @{Local = "certified-backup.html"; Remote = "certified-backup.html"},
    @{Local = "EHR1_installer.exe"; Remote = "EHR1_installer.exe"}
)

foreach ($fileInfo in $files) {
    if (Test-Path $fileInfo.Local) {
        Upload-File -LocalFile $fileInfo.Local -RemoteFile $fileInfo.Remote
        Write-Host ""
    }
    else {
        Write-Host "File not found: $($fileInfo.Local)" -ForegroundColor Yellow
    }
}

Write-Host "Upload complete!" -ForegroundColor Green
