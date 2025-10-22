param(
    [string]$Environment = "production",
    [switch]$Detailed = $false,
    [switch]$Alert = $false
)

# Health check configuration
$PRODUCTION_URL = "https://api.mycourses.com"
$HEALTH_LOG = "C:\monitoring\mycourses\logs\health-detailed.log"
$ALERT_EMAIL = "admin@mycourses.com"

$healthChecks = @()
$overallHealth = $true

function Test-ApiEndpoint {
    param([string]$Endpoint, [string]$ExpectedStatus = "200")
    
    try {
        $response = Invoke-WebRequest -Uri "$PRODUCTION_URL$Endpoint" -UseBasicParsing -TimeoutSec 30
        
        if ($response.StatusCode -eq $ExpectedStatus) {
            return @{ Status = "PASS"; Message = "HTTP $($response.StatusCode)"; ResponseTime = $response.Headers["X-Response-Time"] }
        } else {
            return @{ Status = "FAIL"; Message = "HTTP $($response.StatusCode), expected $ExpectedStatus" }
        }
    } catch {
        return @{ Status = "FAIL"; Message = $_.Exception.Message }
    }
}

function Test-DatabaseHealth {
    try {
        $env:PGPASSWORD = "STRONG_PRODUCTION_PASSWORD_2024"
        $result = psql -h localhost -U mycourses_prod -d mycourses_production -c "SELECT COUNT(*) as user_count FROM users; SELECT COUNT(*) as course_count FROM courses;" -t 2>$null
        
        if ($LASTEXITCODE -eq 0) {
            $lines = $result -split "`n" | Where-Object { $_.Trim() -ne "" }
            return @{ Status = "PASS"; Message = "Users: $($lines[0].Trim()), Courses: $($lines[1].Trim())" }
        } else {
            return @{ Status = "FAIL"; Message = "Database connection failed" }
        }
    } catch {
        return @{ Status = "FAIL"; Message = $_.Exception.Message }
    }
}

function Test-RedisHealth {
    try {
        $redisInfo = redis-cli info server 2>$null
        if ($LASTEXITCODE -eq 0) {
            $uptime = ($redisInfo | Select-String "uptime_in_seconds:").ToString().Split(":")[1]
            return @{ Status = "PASS"; Message = "Uptime: $uptime seconds" }
        } else {
            return @{ Status = "FAIL"; Message = "Redis connection failed" }
        }
    } catch {
        return @{ Status = "FAIL"; Message = $_.Exception.Message }
    }
}

function Test-DiskSpace {
    $drives = Get-WmiObject -Class Win32_LogicalDisk | Where-Object { $_.DriveType -eq 3 }
    $results = @()
    
    foreach ($drive in $drives) {
        $freeSpaceGB = [math]::Round($drive.FreeSpace / 1GB, 2)
        $totalSpaceGB = [math]::Round($drive.Size / 1GB, 2)
        $percentFree = [math]::Round(($drive.FreeSpace / $drive.Size) * 100, 2)
        
        if ($percentFree -lt 10) {
            $status = "CRITICAL"
        } elseif ($percentFree -lt 20) {
            $status = "WARNING"
        } else {
            $status = "PASS"
        }
        
        $results += @{
            Drive = $drive.DeviceID
            Status = $status
            Message = "$freeSpaceGB GB free of $totalSpaceGB GB ($percentFree%)"
        }
    }
    
    return $results
}

function Test-ProcessHealth {
    # Check if critical processes are running
    $processes = @(
        @{ Name = "php"; Description = "PHP processes" },
        @{ Name = "postgres"; Description = "PostgreSQL service" },
        @{ Name = "redis-server"; Description = "Redis service" }
    )
    
    $results = @()
    
    foreach ($proc in $processes) {
        $running = Get-Process -Name $proc.Name -ErrorAction SilentlyContinue
        if ($running) {
            $results += @{
                Process = $proc.Description
                Status = "PASS"
                Message = "$($running.Count) process(es) running"
            }
        } else {
            $results += @{
                Process = $proc.Description
                Status = "FAIL"
                Message = "Process not running"
            }
        }
    }
    
    return $results
}

# Execute health checks
Write-Host "Production Health Check Report" -ForegroundColor Magenta
Write-Host "==============================" -ForegroundColor Magenta
Write-Host "Timestamp: $(Get-Date)" -ForegroundColor Gray
Write-Host "Environment: $Environment" -ForegroundColor Gray
Write-Host ""

# API Health Checks
Write-Host "API Endpoints:" -ForegroundColor Cyan
$apiTests = @(
    @{ Endpoint = "/api/test"; Name = "Health Check" },
    @{ Endpoint = "/api/courses"; Name = "Public Courses" },
    @{ Endpoint = "/api/v1/home"; Name = "Home Page" }
)

foreach ($test in $apiTests) {
    $result = Test-ApiEndpoint $test.Endpoint
    $healthChecks += @{ Component = "API - $($test.Name)"; Status = $result.Status; Message = $result.Message }
    
    $color = if ($result.Status -eq "PASS") { "Green" } else { "Red"; $script:overallHealth = $false }
    Write-Host "  $($test.Name): $($result.Status) - $($result.Message)" -ForegroundColor $color
}

Write-Host ""

# Database Health
Write-Host "Database:" -ForegroundColor Cyan
$dbResult = Test-DatabaseHealth
$healthChecks += @{ Component = "Database"; Status = $dbResult.Status; Message = $dbResult.Message }
$color = if ($dbResult.Status -eq "PASS") { "Green" } else { "Red"; $script:overallHealth = $false }
Write-Host "  PostgreSQL: $($dbResult.Status) - $($dbResult.Message)" -ForegroundColor $color

Write-Host ""

# Redis Health
Write-Host "Cache:" -ForegroundColor Cyan
$redisResult = Test-RedisHealth
$healthChecks += @{ Component = "Redis"; Status = $redisResult.Status; Message = $redisResult.Message }
$color = if ($redisResult.Status -eq "PASS") { "Green" } else { "Red"; $script:overallHealth = $false }
Write-Host "  Redis: $($redisResult.Status) - $($redisResult.Message)" -ForegroundColor $color

Write-Host ""

# System Health
if ($Detailed) {
    Write-Host "System Resources:" -ForegroundColor Cyan
    
    # Disk Space
    $diskResults = Test-DiskSpace
    foreach ($disk in $diskResults) {
        $healthChecks += @{ Component = "Disk $($disk.Drive)"; Status = $disk.Status; Message = $disk.Message }
        $color = switch ($disk.Status) {
            "PASS" { "Green" }
            "WARNING" { "Yellow" }
            "CRITICAL" { "Red"; $script:overallHealth = $false }
        }
        Write-Host "  Disk $($disk.Drive): $($disk.Status) - $($disk.Message)" -ForegroundColor $color
    }
    
    Write-Host ""
    
    # Process Health
    Write-Host "Processes:" -ForegroundColor Cyan
    $processResults = Test-ProcessHealth
    foreach ($proc in $processResults) {
        $healthChecks += @{ Component = $proc.Process; Status = $proc.Status; Message = $proc.Message }
        $color = if ($proc.Status -eq "PASS") { "Green" } else { "Red"; $script:overallHealth = $false }
        Write-Host "  $($proc.Process): $($proc.Status) - $($proc.Message)" -ForegroundColor $color
    }
}

Write-Host ""

# Overall Status
Write-Host "Overall Health:" -ForegroundColor Magenta
if ($overallHealth) {
    Write-Host "  HEALTHY - All systems operational" -ForegroundColor Green
} else {
    Write-Host "  UNHEALTHY - Issues detected" -ForegroundColor Red
    
    if ($Alert) {
        # Send alert (configure your email settings)
        $alertBody = "Production health check failed at $(Get-Date).`n`nFailed checks:`n"
        $failedChecks = $healthChecks | Where-Object { $_.Status -ne "PASS" }
        foreach ($check in $failedChecks) {
            $alertBody += "- $($check.Component): $($check.Message)`n"
        }
        
        # Uncomment and configure for email alerts
        # Send-MailMessage -To $ALERT_EMAIL -Subject "Production Health Alert" -Body $alertBody -SmtpServer "your-smtp-server"
        
        Write-Host "  Alert would be sent to: $ALERT_EMAIL" -ForegroundColor Yellow
    }
}

# Log detailed results
if ($Detailed) {
    $logEntry = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [HEALTH-CHECK] Overall: $(if ($overallHealth) { 'HEALTHY' } else { 'UNHEALTHY' })"
    foreach ($check in $healthChecks) {
        $logEntry += "`n  $($check.Component): $($check.Status) - $($check.Message)"
    }
    
    Add-Content -Path $HEALTH_LOG -Value $logEntry
}

# Exit with appropriate code
if ($overallHealth) {
    exit 0
} else {
    exit 1
}