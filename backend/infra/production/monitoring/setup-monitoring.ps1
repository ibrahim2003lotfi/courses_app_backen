Write-Host "Setting up production monitoring..." -ForegroundColor Yellow

# Create monitoring directories
$MONITORING_PATH = "C:\monitoring\mycourses"
New-Item -ItemType Directory -Path "$MONITORING_PATH\logs" -Force | Out-Null
New-Item -ItemType Directory -Path "$MONITORING_PATH\scripts" -Force | Out-Null

# Health check script
$healthCheckScript = @'
# Health Check Script for MyCourses API
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$logFile = "C:\monitoring\mycourses\logs\health-check.log"

try {
    # Test API health
    $response = Invoke-WebRequest -Uri "https://api.mycourses.com/api/test" -UseBasicParsing -TimeoutSec 30
    
    if ($response.StatusCode -eq 200) {
        $status = "HEALTHY"
        $message = "API responding normally"
    } else {
        $status = "WARNING"
        $message = "API returned status: $($response.StatusCode)"
    }
} catch {
    $status = "CRITICAL"
    $message = "API not responding: $($_.Exception.Message)"
}

# Log result
$logEntry = "$timestamp [$status] $message"
Add-Content -Path $logFile -Value $logEntry

# Send alerts if critical
if ($status -eq "CRITICAL") {
    # Send email alert (configure SMTP settings)
    # Send-MailMessage -To "admin@mycourses.com" -Subject "API Health Alert" -Body $message
    
    # Write to Event Log
    Write-EventLog -LogName Application -Source "MyCourses" -EventId 1001 -EntryType Error -Message $message
}

Write-Host $logEntry
'@

$healthCheckScript | Out-File -FilePath "$MONITORING_PATH\scripts\health-check.ps1" -Encoding UTF8

# Database monitoring script
$dbMonitorScript = @'
# Database Monitoring Script
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$logFile = "C:\monitoring\mycourses\logs\db-monitor.log"

try {
    # Test database connection
    $env:PGPASSWORD = "STRONG_PRODUCTION_PASSWORD_2024"
    $result = psql -h localhost -U mycourses_prod -d mycourses_production -c "SELECT COUNT(*) FROM users;" -t 2>$null
    
    if ($LASTEXITCODE -eq 0) {
        $userCount = $result.Trim()
        $status = "HEALTHY"
        $message = "Database responding, $userCount users"
    } else {
        $status = "CRITICAL"
        $message = "Database connection failed"
    }
} catch {
    $status = "CRITICAL"
    $message = "Database error: $($_.Exception.Message)"
}

$logEntry = "$timestamp [$status] $message"
Add-Content -Path $logFile -Value $logEntry
Write-Host $logEntry
'@

$dbMonitorScript | Out-File -FilePath "$MONITORING_PATH\scripts\db-monitor.ps1" -Encoding UTF8

Write-Host "Monitoring scripts created in $MONITORING_PATH\scripts\" -ForegroundColor Green

# Setup Windows Task Scheduler for monitoring
Write-Host ""
Write-Host "Setting up scheduled monitoring tasks..." -ForegroundColor Cyan

# Health check every 5 minutes
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-File `"$MONITORING_PATH\scripts\health-check.ps1`""
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 365) -At (Get-Date)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName "MyCourses-HealthCheck" -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force

# Database check every 15 minutes
$action2 = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-File `"$MONITORING_PATH\scripts\db-monitor.ps1`""
$trigger2 = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 15) -RepetitionDuration (New-TimeSpan -Days 365) -At (Get-Date)

Register-ScheduledTask -TaskName "MyCourses-DatabaseCheck" -Action $action2 -Trigger $trigger2 -Principal $principal -Settings $settings -Force

Write-Host "Scheduled tasks created:" -ForegroundColor Green
Write-Host "  - MyCourses-HealthCheck (every 5 minutes)" -ForegroundColor White
Write-Host "  - MyCourses-DatabaseCheck (every 15 minutes)" -ForegroundColor White