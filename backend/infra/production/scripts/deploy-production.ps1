param(
    [string]$Version = "latest",
    [string]$Environment = "production",
    [switch]$SkipBackup = $false,
    [switch]$SkipMigrations = $false,
    [switch]$Force = $false
)

# Production deployment configuration
$PRODUCTION_PATH = "C:\inetpub\mycourses-api"
$BACKUP_PATH = "C:\backups\mycourses"
$TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
$DEPLOYMENT_LOG = "$BACKUP_PATH\deployments\deploy_$TIMESTAMP.log"

# Colors for output
$Colors = @{
    Success = "Green"
    Warning = "Yellow"
    Error = "Red"
    Info = "Cyan"
    Header = "Magenta"
}

function Write-Log {
    param([string]$Message, [string]$Level = "Info")
    $logMessage = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [$Level] $Message"
    Write-Host $logMessage -ForegroundColor $Colors[$Level]
    Add-Content -Path $DEPLOYMENT_LOG -Value $logMessage
}

function Test-Prerequisites {
    Write-Log "Checking deployment prerequisites..." "Header"
    
    # Check if running as Administrator
    if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
        Write-Log "This script must be run as Administrator" "Error"
        exit 1
    }
    
    # Check PHP
    try {
        $phpVersion = php -v | Select-String "PHP \d+\.\d+" | ForEach-Object { $_.Matches[0].Value }
        Write-Log "PHP Version: $phpVersion" "Success"
    } catch {
        Write-Log "PHP not found or not in PATH" "Error"
        exit 1
    }
    
    # Check PostgreSQL
    try {
        $result = psql -U mycourses_prod -d mycourses_production -c "SELECT version();" 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-Log "PostgreSQL connection successful" "Success"
        } else {
            Write-Log "PostgreSQL connection failed" "Error"
            exit 1
        }
    } catch {
        Write-Log "PostgreSQL connection test failed" "Error"
        exit 1
    }
    
    # Check Redis
    try {
        redis-cli ping | Out-Null
        Write-Log "Redis connection successful" "Success"
    } catch {
        Write-Log "Redis connection failed" "Warning"
    }
    
    # Check disk space (minimum 5GB free)
    $freeSpace = (Get-WmiObject -Class Win32_LogicalDisk -Filter "DeviceID='C:'").FreeSpace / 1GB
    if ($freeSpace -lt 5) {
        Write-Log "Insufficient disk space: ${freeSpace}GB free" "Error"
        exit 1
    }
    Write-Log "Free disk space: ${freeSpace}GB" "Success"
}

function Backup-Production {
    if ($SkipBackup) {
        Write-Log "Skipping backup (--SkipBackup flag set)" "Warning"
        return
    }
    
    Write-Log "Creating production backup..." "Header"
    
    # Create backup directories
    $backupDir = "$BACKUP_PATH\$TIMESTAMP"
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    
    # Database backup
    Write-Log "Backing up database..." "Info"
    $env:PGPASSWORD = "STRONG_PRODUCTION_PASSWORD_2024"
    pg_dump -h localhost -U mycourses_prod -d mycourses_production -f "$backupDir\database.sql"
    
    if ($LASTEXITCODE -ne 0) {
        Write-Log "Database backup failed" "Error"
        exit 1
    }
    
    # Application backup
    Write-Log "Backing up application files..." "Info"
    if (Test-Path $PRODUCTION_PATH) {
        Compress-Archive -Path $PRODUCTION_PATH -DestinationPath "$backupDir\application.zip" -Force
    }
    
    # Storage backup (if using local storage)
    if (Test-Path "$PRODUCTION_PATH\storage\app") {
        Compress-Archive -Path "$PRODUCTION_PATH\storage\app" -DestinationPath "$backupDir\storage.zip" -Force
    }
    
    Write-Log "Backup completed: $backupDir" "Success"
    
    # Clean old backups (keep last 30 days)
    $oldBackups = Get-ChildItem $BACKUP_PATH -Directory | Where-Object { $_.CreationTime -lt (Get-Date).AddDays(-30) }
    foreach ($oldBackup in $oldBackups) {
        Remove-Item $oldBackup.FullName -Recurse -Force
        Write-Log "Removed old backup: $($oldBackup.Name)" "Info"
    }
}

function Deploy-Application {
    Write-Log "Deploying application..." "Header"
    
    # Stop services if running
    Write-Log "Stopping services..." "Info"
    Stop-Process -Name "php" -Force -ErrorAction SilentlyContinue
    
    # Create production directory if it doesn't exist
    if (-not (Test-Path $PRODUCTION_PATH)) {
        New-Item -ItemType Directory -Path $PRODUCTION_PATH -Force | Out-Null
    }
    
    # Copy application files
    Write-Log "Copying application files..." "Info"
    $sourceFiles = @(
        "app", "bootstrap", "config", "database", "public", "resources", "routes", "storage"
    )
    
    foreach ($folder in $sourceFiles) {
        if (Test-Path $folder) {
            Copy-Item -Path $folder -Destination "$PRODUCTION_PATH\" -Recurse -Force
            Write-Log "Copied $folder" "Info"
        }
    }
    
    # Copy specific files
    $files = @("composer.json", "composer.lock", "artisan")
    foreach ($file in $files) {
        if (Test-Path $file) {
            Copy-Item -Path $file -Destination "$PRODUCTION_PATH\" -Force
        }
    }
    
    # Copy production environment file
    Copy-Item -Path ".env.production" -Destination "$PRODUCTION_PATH\.env" -Force
    Write-Log "Production environment configured" "Success"
    
    # Set proper permissions
    $acl = Get-Acl $PRODUCTION_PATH
    $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule("IIS_IUSRS", "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow")
    $acl.SetAccessRule($accessRule)
    Set-Acl -Path $PRODUCTION_PATH -AclObject $acl
    
    Write-Log "File permissions set" "Success"
}

function Install-Dependencies {
    Write-Log "Installing production dependencies..." "Header"
    
    Set-Location $PRODUCTION_PATH
    
    # Install Composer dependencies (production mode)
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    
    if ($LASTEXITCODE -ne 0) {
        Write-Log "Composer install failed" "Error"
        exit 1
    }
    
    Write-Log "Dependencies installed" "Success"
}

function Run-Migrations {
    if ($SkipMigrations) {
        Write-Log "Skipping migrations (--SkipMigrations flag set)" "Warning"
        return
    }
    
    Write-Log "Running database migrations..." "Header"
    
    Set-Location $PRODUCTION_PATH
    $env:APP_ENV = "production"
    
    # Check migration status first
    $migrationStatus = php artisan migrate:status --env=production
    Write-Log "Current migration status checked" "Info"
    
    # Run migrations
    if ($Force) {
        php artisan migrate --env=production --force
    } else {
        # Interactive confirmation for production
        $confirmation = Read-Host "Run migrations on PRODUCTION database? (y/N)"
        if ($confirmation -eq "y" -or $confirmation -eq "Y") {
            php artisan migrate --env=production --force
        } else {
            Write-Log "Migrations skipped by user" "Warning"
            return
        }
    }
    
    if ($LASTEXITCODE -ne 0) {
        Write-Log "Migrations failed" "Error"
        exit 1
    }
    
    Write-Log "Migrations completed successfully" "Success"
}

function Optimize-Production {
    Write-Log "Optimizing for production..." "Header"
    
    Set-Location $PRODUCTION_PATH
    $env:APP_ENV = "production"
    
    # Clear all caches
    php artisan config:clear --env=production
    php artisan cache:clear --env=production
    php artisan view:clear --env=production
    php artisan route:clear --env=production
    
    # Optimize for production
    php artisan config:cache --env=production
    php artisan route:cache --env=production
    php artisan view:cache --env=production
    php artisan event:cache --env=production
    
    # Optimize Composer autoloader
    composer dump-autoload --optimize --classmap-authoritative
    
    Write-Log "Production optimization completed" "Success"
}

function Test-Deployment {
    Write-Log "Testing deployment..." "Header"
    
    Set-Location $PRODUCTION_PATH
    
    # Test database connection
    try {
        $dbTest = php artisan migrate:status --env=production 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-Log "Database connection test: PASS" "Success"
        } else {
            Write-Log "Database connection test: FAIL" "Error"
            return $false
        }
    } catch {
        Write-Log "Database connection test: FAIL" "Error"
        return $false
    }
    
    # Test Redis connection
    try {
        $redisTest = php artisan tinker --execute="use Illuminate\Support\Facades\Cache; Cache::put('test', 'value', 60); echo Cache::get('test');" --env=production 2>$null
        if ($redisTest -like "*value*") {
            Write-Log "Redis connection test: PASS" "Success"
        } else {
            Write-Log "Redis connection test: FAIL" "Warning"
        }
    } catch {
        Write-Log "Redis connection test: FAIL" "Warning"
    }
    
    Write-Log "Deployment tests completed" "Success"
    return $true
}

function Start-Services {
    Write-Log "Starting production services..." "Header"
    
    Set-Location $PRODUCTION_PATH
    
    # Start queue workers (background)
    Start-Process -FilePath "php" -ArgumentList "artisan", "queue:work", "redis", "--sleep=3", "--tries=3", "--max-time=3600", "--env=production" -WindowStyle Hidden
    
    # Start scheduler (if using)
    # This would typically be handled by Windows Task Scheduler in real production
    
    Write-Log "Production services started" "Success"
}

# Main deployment flow
function Main {
    Write-Log "Starting production deployment..." "Header"
    Write-Log "Version: $Version" "Info"
    Write-Log "Environment: $Environment" "Info"
    Write-Log "Timestamp: $TIMESTAMP" "Info"
    
    # Create backup directory
    New-Item -ItemType Directory -Path "$BACKUP_PATH\deployments" -Force | Out-Null
    
    try {
        Test-Prerequisites
        Backup-Production
        Deploy-Application
        Install-Dependencies
        Run-Migrations
        Optimize-Production
        
        if (Test-Deployment) {
            Start-Services
            Write-Log "PRODUCTION DEPLOYMENT COMPLETED SUCCESSFULLY!" "Success"
            Write-Log "Application deployed to: $PRODUCTION_PATH" "Info"
            Write-Log "Backup location: $BACKUP_PATH\$TIMESTAMP" "Info"
            Write-Log "Deployment log: $DEPLOYMENT_LOG" "Info"
        } else {
            Write-Log "DEPLOYMENT TESTS FAILED - PLEASE INVESTIGATE" "Error"
            exit 1
        }
        
    } catch {
        Write-Log "DEPLOYMENT FAILED: $($_.Exception.Message)" "Error"
        Write-Log "Check logs at: $DEPLOYMENT_LOG" "Error"
        exit 1
    }
}

# Run main deployment
Main