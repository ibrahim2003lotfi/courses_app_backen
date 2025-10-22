@echo off
setlocal

set BACKEND_PATH=backend
set STAGING_PORT=8080

if "%1"=="" goto usage
if "%1"=="start" goto start
if "%1"=="stop" goto stop
if "%1"=="status" goto status
if "%1"=="artisan" goto artisan
if "%1"=="test" goto test
if "%1"=="fresh" goto fresh
if "%1"=="logs" goto logs
goto usage

:start
echo Starting staging environment...
cd %BACKEND_PATH%
set APP_ENV=staging
echo Starting server on port %STAGING_PORT%...
php artisan serve --host=127.0.0.1 --port=%STAGING_PORT% --env=staging
cd ..
goto end

:stop
echo Stopping staging environment...
taskkill /F /IM php.exe /FI "WINDOWTITLE eq *%STAGING_PORT%*" 2>nul
echo Staging server stopped
goto end

:status
echo Staging Environment Status
echo =========================
curl -s -o nul -w "API Status: %%{http_code}" http://localhost:%STAGING_PORT%/api/test
echo.
cd %BACKEND_PATH%
set APP_ENV=staging
php artisan migrate:status --env=staging >nul 2>&1
if %errorlevel% equ 0 (
    echo Database: Connected
) else (
    echo Database: Error
)
cd ..
goto end

:artisan
cd %BACKEND_PATH%
set APP_ENV=staging
shift
php artisan %* --env=staging
cd ..
goto end

:test
echo Running staging tests...
cd %BACKEND_PATH%
set APP_ENV=staging
php artisan test --env=staging
cd ..
goto end

:fresh
echo Refreshing staging database...
cd %BACKEND_PATH%
set APP_ENV=staging
php artisan migrate:fresh --seed --force --env=staging
echo Database refreshed
cd ..
goto end

:logs
echo Viewing staging logs...
if exist "%BACKEND_PATH%\storage\logs\laravel.log" (
    type "%BACKEND_PATH%\storage\logs\laravel.log"
) else (
    echo No log file found
)
goto end

:usage
echo Staging Environment Management
echo =============================
echo.
echo Usage: manage-staging.bat [action] [arguments]
echo.
echo Actions:
echo   start     Start staging server
echo   stop      Stop staging server
echo   status    Show staging status
echo   artisan   Run artisan command
echo   test      Run tests
echo   fresh     Refresh database
echo   logs      View logs
echo.
echo Examples:
echo   manage-staging.bat start
echo   manage-staging.bat artisan migrate
echo   manage-staging.bat test

:end