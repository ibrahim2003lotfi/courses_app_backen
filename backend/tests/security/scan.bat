@echo off
echo Laravel Security Scan
echo =====================

echo.
echo 1. Running Laravel Security Analysis...
php artisan security:scan

echo.
echo 2. Checking PHPStan...
if exist "vendor\bin\phpstan.bat" (
    echo Running PHPStan...
    vendor\bin\phpstan.bat analyse --memory-limit=1G --no-progress
) else (
    echo PHPStan not found - skipping
)

echo.
echo 3. Checking Security Checker...
if exist "vendor\bin\security-checker.bat" (
    echo Running Security Checker...
    vendor\bin\security-checker.bat security:check
) else (
    echo Security Checker not found - skipping
)

echo.
echo Security Scan Completed Successfully!
echo =====================================