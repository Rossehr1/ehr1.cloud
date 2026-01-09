$ftpHost = "92.112.189.73"
$ftpUsername = "u660126262.ehr1.cloud"
$ftpPassword = "yodApodA911**"

$files = @("index.html", "about.html", "styles.css")

function Upload-File {
    param(
        [string]$LocalFile,
        [string]$RemoteFile
    )
    try {
        $ftpUri = "ftp://$ftpHost/$RemoteFile"
        Write-Host "Uploading: $LocalFile -> $ftpUri" -ForegroundColor Cyan
        $ftpRequest = [System.Net.FtpWebRequest]::Create($ftpUri)
        $ftpRequest.Credentials = New-Object System.Net.NetworkCredential($ftpUsername, $ftpPassword)
        $ftpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $ftpRequest.UseBinary = $true
        $ftpRequest.UsePassive = $true
        $ftpRequest.EnableSsl = $false
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

Write-Host "Starting FTP upload to Hostinger..." -ForegroundColor Yellow
Write-Host "Host: $ftpHost" -ForegroundColor Cyan
Write-Host "Username: $ftpUsername" -ForegroundColor Cyan
Write-Host ""

$successCount = 0
foreach ($file in $files) {
    if (Test-Path $file) {
        if (Upload-File -LocalFile $file -RemoteFile "public_html/$file") {
            $successCount++
        }
    }
    else {
        Write-Host "File not found: $file" -ForegroundColor Yellow
    }
}

Write-Host ""
if ($successCount -eq $files.Count) {
    Write-Host "All files uploaded successfully!" -ForegroundColor Green
    Write-Host "Your website should be live at: https://ehr1.cloud" -ForegroundColor Green
}
else {
    Write-Host "Some files failed to upload. Success: $successCount/$($files.Count)" -ForegroundColor Yellow
    Write-Host "Trying alternative paths..." -ForegroundColor Yellow
    
    foreach ($file in $files) {
        if (Test-Path $file) {
            Write-Host "Trying root directory: $file" -ForegroundColor Cyan
            if (Upload-File -LocalFile $file -RemoteFile $file) {
                $successCount++
            }
        }
    }
}