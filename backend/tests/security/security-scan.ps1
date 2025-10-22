Write-Host "Laravel Security Scan" -ForegroundColor Yellow
Write-Host "====================="

Write-Host ""
Write-Host "1. Running Laravel Security Analysis..." -ForegroundColor Cyan
php artisan security:scan

Write-Host ""
Write-Host "2. Checking for PHPStan..." -ForegroundColor Cyan
if (Test-Path "vendor\bin\phpstan.bat") {
    Write-Host "Running PHPStan..." -ForegroundColor Yellow
    vendor\bin\phpstan.bat analyse --memory-limit=1G --no-progress
} else {
    Write-Host "PHPStan not found - skipping" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "3. Checking for Security Checker..." -ForegroundColor Cyan
if (Test-Path "vendor\bin\security-checker.bat") {
    Write-Host "Running Security Checker..." -ForegroundColor Yellow
    vendor\bin\security-checker.bat security:check
} else {
    Write-Host "Security Checker not found - skipping" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Security Scan Completed Successfully!" -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Green