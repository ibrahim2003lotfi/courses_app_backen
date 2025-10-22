param(
    [int]$Port = 8080
)

Write-Host "🚀 Starting Staging Server" -ForegroundColor Yellow
Write-Host "=========================="

$BACKEND_PATH = "C:\Users\User\Desktop\Kholio\My apps\mycourses\backend"

# Check if port is available
$portInUse = Get-NetTCPConnection -LocalPort $Port -ErrorAction SilentlyContinue
if ($portInUse) {
    Write-Host "❌ Port $Port is already in use" -ForegroundColor Red
    Write-Host "Please stop the service using port $Port or use a different port" -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ Port $Port is available" -ForegroundColor Green

# Navigate to backend
Set-Location $BACKEND_PATH

# Start the staging server
Write-Host ""
Write-Host "🌐 Starting Laravel development server on port $Port..." -ForegroundColor Yellow
Write-Host "Environment: staging" -ForegroundColor Cyan
Write-Host "URL: http://localhost:$Port" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Gray
Write-Host ""

# Start with staging environment
$env:APP_ENV = "staging"
php artisan serve --host=0.0.0.0 --port=$Port --env=staging