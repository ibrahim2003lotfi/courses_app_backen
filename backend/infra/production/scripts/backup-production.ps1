param(
    [string]$BackupType = "full", # full, incremental, database-only
    [string]$Retention = "30" # days to keep backups
)

# Backup Configuration
$BACKUP_ROOT = "C:\backups\mycourses-production"
$PRODUCTION_PATH = "C:\inetpub\mycourses-api"
$S3_BUCKET = "mycourses-production-backups"
$TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
$BACKUP_PATH = "$BACKUP_ROOT\$TIMESTAMP"

function Write-BackupLog {
    param([string]$Message, [string]$Level = "Info")
    $logMessage = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [$Level] $Message"
    Write-Host $logMessage
    Add-Content -Path "$BACKUP_ROOT\backup.log" -Value $logMessage
}

function Backup-Database {
    Write-BackupLog "Starting database backup..." "Info"
    
    # Create backup directory
    New-Item -ItemType Directory -Path $BACKUP_PATH -Force | Out-Null
    
    # Set PostgreSQL password
    $env:PGPASSWORD = "STRONG_PRODUCTION_PASSWORD_2024"
    
    # Full database backup
    $dbBackupFile = "$BACKUP_PATH\database_$TIMESTAMP.sql"
    pg_dump -h localhost -U mycourses_prod -d mycourses_production -f $dbBackupFile
    
    if ($LASTEXITCODE -eq 0) {
        Write-BackupLog "Database backup completed: $dbBackupFile" "Success"
        
        # Compress database backup
        Compress-Archive -Path $dbBackupFile -DestinationPath "$dbBackupFile.zip" -Force
        Remove-Item $dbBackupFile -Force
        
        Write-BackupLog "Database backup compressed" "Success"
        return $true
    } else {
        Write-BackupLog "Database backup failed" "Error"
        return $false
    }
}

function Backup-Application {
    if ($BackupType -eq "database-only") {
        return $true
    }
    
    Write-BackupLog "Starting application backup..." "Info"
    
    # Application files backup (excluding storage and cache)
    $appBackupFile = "$BACKUP_PATH\application_$TIMESTAMP.zip"
    
    $excludePatterns = @(
        "$PRODUCTION_PATH\storage\logs\*",
        "$PRODUCTION_PATH\storage\framework\cache\*",
        "$PRODUCTION_PATH\storage\framework\sessions\*",
        "$PRODUCTION_PATH\storage\framework\views\*",
        "$PRODUCTION_PATH\vendor\*",
        "$PRODUCTION_PATH\node_modules\*"
    )
    
    # Create application backup
    try {
        Compress-Archive -Path $PRODUCTION_PATH -DestinationPath $appBackupFile -Force
        Write-BackupLog "Application backup completed: $appBackupFile" "Success"
        return $true
    } catch {
        Write-BackupLog "Application backup failed: $($_.Exception.Message)" "Error"
        return $false
    }
}

function Backup-Storage {
    if ($BackupType -eq "database-only") {
        return $true
    }
    
    Write-BackupLog "Starting storage backup..." "Info"
    
    $storageBackupFile = "$BACKUP_PATH\storage_$TIMESTAMP.zip"
    $storagePath = "$PRODUCTION_PATH\storage\app"
    
    if (Test-Path $storagePath) {
        try {
            Compress-Archive -Path $storagePath -DestinationPath $storageBackupFile -Force
            Write-BackupLog "Storage backup completed: $storageBackupFile" "Success"
            return $true
        } catch {
            Write-BackupLog "Storage backup failed: $($_.Exception.Message)" "Error"
            return $false
        }
    } else {
        Write-BackupLog "Storage path not found, skipping" "Warning"
        return $true
    }
}

function Upload-ToS3 {
    Write-BackupLog "Uploading backups to S3..." "Info"
    
    # Check if AWS CLI is available
    if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
        Write-BackupLog "AWS CLI not found, skipping S3 upload" "Warning"
        return $false
    }
    
    try {
        # Upload backup directory to S3
        aws s3 sync $BACKUP_PATH "s3://$S3_BUCKET/backups/$TIMESTAMP/" --delete
        
        if ($LASTEXITCODE -eq 0) {
            Write-BackupLog "S3 upload completed" "Success"
            return $true
        } else {
            Write-BackupLog "S3 upload failed" "Error"
            return $false
        }
    } catch {
        Write-BackupLog "S3 upload error: $($_.Exception.Message)" "Error"
        return $false
    }
}

function Cleanup-OldBackups {
    Write-BackupLog "Cleaning up old backups..." "Info"
    
    $cutoffDate = (Get-Date).AddDays(-[int]$Retention)
    
    # Local cleanup
    $oldBackups = Get-ChildItem $BACKUP_ROOT -Directory | Where-Object { $_.CreationTime -lt $cutoffDate }
    foreach ($backup in $oldBackups) {
        Remove-Item $backup.FullName -Recurse -Force
        Write-BackupLog "Removed old backup: $($backup.Name)" "Info"
    }
    
    # S3 cleanup (if AWS CLI is available)
    if (Get-Command aws -ErrorAction SilentlyContinue) {
        $s3OldBackups = aws s3 ls "s3://$S3_BUCKET/backups/" | Where-Object { 
            $_.Split()[0] -lt $cutoffDate.ToString("yyyy-MM-dd")
        }
        
        foreach ($s3Backup in $s3OldBackups) {
            $backupName = $s3Backup.Split()[-1]
            aws s3 rm "s3://$S3_BUCKET/backups/$backupName" --recursive
            Write-BackupLog "Removed old S3 backup: $backupName" "Info"
        }
    }
    
    Write-BackupLog "Cleanup completed" "Success"
}

function Generate-BackupReport {
    $backupSize = (Get-ChildItem $BACKUP_PATH -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
    $backupFiles = Get-ChildItem $BACKUP_PATH -File
    
    $report = @"
Production Backup Report
========================
Timestamp: $TIMESTAMP
Backup Type: $BackupType
Total Size: $([math]::Round($backupSize, 2)) MB
Files Created: $($backupFiles.Count)

Files:
$($backupFiles | ForEach-Object { "  - $($_.Name) ($([math]::Round($_.Length / 1MB, 2)) MB)" } | Out-String)

Backup Location: $BACKUP_PATH
S3 Location: s3://$S3_BUCKET/backups/$TIMESTAMP/

Generated: $(Get-Date)
"@
    
    $report | Out-File -FilePath "$BACKUP_PATH\backup-report.txt" -Encoding UTF8
    Write-BackupLog "Backup report generated" "Success"
    
    return $report
}

# Main backup execution
function Main {
    Write-BackupLog "Starting production backup process..." "Header"
    Write-BackupLog "Backup Type: $BackupType" "Info"
    Write-BackupLog "Retention: $Retention days" "Info"
    
    # Create backup root directory
    New-Item -ItemType Directory -Path $BACKUP_ROOT -Force | Out-Null
    
    $success = $true
    
    # Execute backup steps
    if (-not (Backup-Database)) { $success = $false }
    if (-not (Backup-Application)) { $success = $false }
    if (-not (Backup-Storage)) { $success = $false }
    
    if ($success) {
        # Generate report
        $report = Generate-BackupReport
        
        # Upload to S3
        Upload-ToS3
        
        # Cleanup old backups
        Cleanup-OldBackups
        
        Write-BackupLog "BACKUP COMPLETED SUCCESSFULLY" "Success"
        Write-BackupLog "Backup location: $BACKUP_PATH" "Info"
        
        # Send success notification
        Write-Host $report -ForegroundColor Green
        
    } else {
        Write-BackupLog "BACKUP FAILED - Please check logs" "Error"
        exit 1
    }
}

# Execute main backup process
Main