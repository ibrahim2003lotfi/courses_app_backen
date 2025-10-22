param(
    [Parameter(Mandatory=$true)]
    [ValidateSet("start", "stop", "restart", "status", "logs", "artisan", "test", "fresh", "backup")]
    [string]$Action,
    
    [Parameter(ValueFromRemainingArguments=$true)]
    [string[]]$Arguments
)

$BACKEND_PATH = "C:\Users\User\Desktop\Kholio\My apps\mycourses\backend"
$STAGING_PORT = 8080

function Show-Usage {
    Write-Host "Staging Environment Management" -ForegroundColor Yellow
    Write-Host "=============================" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Usage: .\manage-staging.ps1 <action> [arguments]" -ForegroundColor White
    Write-Host ""
    Write-Host "Actions:" -ForegroundColor Cyan
    Write-Host "  start     Start staging server" -ForegroundColor White
    Write-Host "  stop      Stop staging server" -ForegroundColor White
    Write-Host "  status    Show staging status" -ForegroundColor White
    Write-Host "  artisan   Run artisan command" -ForegroundColor White
    Write-Host "  test      Run tests" -ForegroundColor White
    Write-Host "  fresh     Refresh database" -ForegroundColor White
    Write-Host "  logs      View logs" -ForegroundColor White
    Write-Host "  backup    Backup staging database" -ForegroundColor White
    Write-Host ""
    Write-Host "Examples:" -ForegroundColor Cyan
    Write-Host "  .\manage-staging.ps1 start" -ForegroundColor Gray
    Write-Host "  .\manage-staging.ps1 artisan migrate" -ForegroundColor Gray
    Write-Host "  .\manage-staging.ps1 test" -ForegroundColor Gray
}

switch ($Action) {
    "start" {
        Write-Host "Starting staging environment..." -ForegroundColor Yellow
        Set-Location $BACKEND_PATH
        $env:APP_ENV = "staging"
        Write-Host "Starting server on port $STAGING_PORT..." -ForegroundColor Cyan
        Write-Host "Press Ctrl+C to stop" -ForegroundColor Gray
        php artisan serve --host=127.0.0.1 --port=$STAGING_PORT --env=staging
        Set-Location ..
    }
    
    "stop" {
        Write-Host "Stopping staging environment..." -ForegroundColor Yellow
        # Find and stop PHP processes on staging port
        $processes = Get-WmiObject Win32_Process | Where-Object { $_.CommandLine -like "*php*serve*$STAGING_PORT*" }
        if ($processes) {
            foreach ($proc in $processes) {
                Stop-Process -Id $proc.ProcessId -Force
            }
            Write-Host "Staging server stopped" -ForegroundColor Green
        } else {
            Write-Host "No staging server found on port $STAGING_PORT" -ForegroundColor Yellow
        }
    }
    
    "status" {
        Write-Host "Staging Environment Status" -ForegroundColor Yellow
        Write-Host "=========================" -ForegroundColor Yellow
        
        # Check if server is running
        try {
            $response = Invoke-WebRequest -Uri "http://localhost:$STAGING_PORT/api/test" -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -eq 200) {
                Write-Host "API is running (http://localhost:$STAGING_PORT)" -ForegroundColor Green
            }
        } catch {
            Write-Host "API is not responding" -ForegroundColor Red
        }
        
        # Check database connection (simplified)
        Set-Location $BACKEND_PATH
        Write-Host "Checking database connection..." -ForegroundColor Cyan
        try {
            $dbCheck = php artisan migrate:status --env=staging 2>$null
            if ($LASTEXITCODE -eq 0) {
                Write-Host "Database connection working" -ForegroundColor Green
            } else {
                Write-Host "Database connection failed" -ForegroundColor Red
            }
        } catch {
            Write-Host "Database connection failed" -ForegroundColor Red
        }
        Set-Location ..
    }
    
    "artisan" {
        Write-Host "Running artisan command: $($Arguments -join ' ')" -ForegroundColor Yellow
        Set-Location $BACKEND_PATH
        $env:APP_ENV = "staging"
        php artisan @Arguments --env=staging
        Set-Location ..
    }
    
    "test" {
        Write-Host "Running staging tests..." -ForegroundColor Yellow
        Set-Location $BACKEND_PATH
        $env:APP_ENV = "staging"
        php artisan test --env=staging
        Set-Location ..
    }
    
    "fresh" {
        Write-Host "Refreshing staging database..." -ForegroundColor Yellow
        Set-Location $BACKEND_PATH
        $env:APP_ENV = "staging"
        php artisan migrate:fresh --seed --force --env=staging
        Write-Host "Database refreshed with test data" -ForegroundColor Green
        Set-Location ..
    }
    
    "logs" {
        Write-Host "Viewing staging logs..." -ForegroundColor Yellow
        $logFile = "$BACKEND_PATH\storage\logs\laravel.log"
        if (Test-Path $logFile) {
            Get-Content $logFile -Tail 50 -Wait
        } else {
            Write-Host "No log file found at $logFile" -ForegroundColor Yellow
        }
    }
    
    "backup" {
        Write-Host "Creating staging database backup..." -ForegroundColor Yellow
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        $backupFile = "infra\staging\backups\staging_backup_$timestamp.sql"
        
        # Create backup directory if it doesn't exist
        $backupDir = "infra\staging\backups"
        if (-not (Test-Path $backupDir)) {
            New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
        }
        
        try {
            $env:PGPASSWORD = "staging_secure_password_2024"
            pg_dump -h localhost -U mycourses_staging -d mycourses_staging -f $backupFile
            Write-Host "Backup created: $backupFile" -ForegroundColor Green
        } catch {
            Write-Host "Backup failed. Please check PostgreSQL connection" -ForegroundColor Red
        }
    }
    
    default {
        Show-Usage
    }
}