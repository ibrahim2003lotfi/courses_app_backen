Write-Host "🚀 Setting up Staging Environment" -ForegroundColor Yellow
Write-Host "================================="

# Configuration
$BACKEND_PATH = "backend"
$STAGING_ENV = "$BACKEND_PATH\.env.staging"
$STAGING_PORT = 8080

# Check prerequisites
Write-Host ""
Write-Host "📋 Checking Prerequisites" -ForegroundColor Yellow
Write-Host "=========================="

# Check PHP
try {
    $phpVersion = php -v | Select-String "PHP \d+\.\d+" | ForEach-Object { $_.Matches[0].Value }
    Write-Host "✅ PHP found: $phpVersion" -ForegroundColor Green
} catch {
    Write-Host "❌ PHP not found. Please install PHP 8.2+" -ForegroundColor Red
    exit 1
}

# Check PostgreSQL
try {
    $postgresVersion = psql --version | Select-String "psql.*\d+\.\d+" | ForEach-Object { $_.Matches[0].Value }
    Write-Host "✅ PostgreSQL found: $postgresVersion" -ForegroundColor Green
} catch {
    Write-Host "❌ PostgreSQL not found. Please install PostgreSQL" -ForegroundColor Red
    exit 1
}

# Check Redis (optional)
try {
    redis-cli ping | Out-Null
    Write-Host "✅ Redis is running" -ForegroundColor Green
} catch {
    Write-Host "⚠️ Redis not running. Starting Redis..." -ForegroundColor Yellow
    # Try to start Redis if installed
    try {
        Start-Process "redis-server" -WindowStyle Hidden
        Start-Sleep 3
        redis-cli ping | Out-Null
        Write-Host "✅ Redis started" -ForegroundColor Green
    } catch {
        Write-Host "⚠️ Redis not available. Will use file cache instead" -ForegroundColor Yellow
    }
}

# Setup staging database
Write-Host ""
Write-Host "🗄️ Setting up Staging Database" -ForegroundColor Yellow
Write-Host "==============================="

try {
    # Run database setup script
    psql -U postgres -f "infra\staging\scripts\setup-staging-db.sql"
    Write-Host "✅ Staging database created" -ForegroundColor Green
} catch {
    Write-Host "❌ Failed to create staging database. Please run manually:" -ForegroundColor Red
    Write-Host "   psql -U postgres -f infra\staging\scripts\setup-staging-db.sql" -ForegroundColor Yellow
}

# Copy environment file
Write-Host ""
Write-Host "⚙️ Configuring Staging Environment" -ForegroundColor Yellow
Write-Host "=================================="

if (Test-Path $STAGING_ENV) {
    Write-Host "📄 Staging environment file already exists" -ForegroundColor Yellow
} else {
    Copy-Item "$BACKEND_PATH\.env.example" $STAGING_ENV
    Write-Host "✅ Environment file copied" -ForegroundColor Green
}

# Navigate to backend directory
Set-Location $BACKEND_PATH

# Generate application key for staging
Write-Host "🔑 Generating staging application key..." -ForegroundColor Yellow
php artisan key:generate --env=staging --force
Write-Host "✅ Application key generated" -ForegroundColor Green

# Install dependencies (if not already done)
Write-Host ""
Write-Host "📦 Installing Dependencies" -ForegroundColor Yellow
Write-Host "=========================="

if (-not (Test-Path "vendor")) {
    Write-Host "Installing Composer dependencies..." -ForegroundColor Yellow
    composer install --no-dev --optimize-autoloader
    Write-Host "✅ Dependencies installed" -ForegroundColor Green
} else {
    Write-Host "✅ Dependencies already installed" -ForegroundColor Green
}

# Run migrations for staging
Write-Host ""
Write-Host "🗄️ Running Staging Migrations" -ForegroundColor Yellow
Write-Host "=============================="

try {
    php artisan migrate --env=staging --force
    Write-Host "✅ Migrations completed" -ForegroundColor Green
} catch {
    Write-Host "❌ Migration failed. Please check database connection" -ForegroundColor Red
}

# Seed staging data
Write-Host ""
Write-Host "🌱 Seeding Staging Data" -ForegroundColor Yellow
Write-Host "======================="

try {
    php artisan db:seed --class=RoleSeeder --env=staging --force
    php artisan db:seed --class=StagingSeeder --env=staging --force
    Write-Host "✅ Staging data seeded" -ForegroundColor Green
} catch {
    Write-Host "⚠️ Seeding completed with warnings" -ForegroundColor Yellow
}

# Clear caches
Write-Host ""
Write-Host "🧹 Clearing Caches" -ForegroundColor Yellow
Write-Host "=================="

php artisan config:clear --env=staging
php artisan cache:clear --env=staging
php artisan view:clear --env=staging
Write-Host "✅ Caches cleared" -ForegroundColor Green

# Go back to root directory
Set-Location ..

Write-Host ""
Write-Host "🎉 Staging Environment Setup Complete!" -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Green
Write-Host ""
Write-Host "📝 Next Steps:" -ForegroundColor Yellow
Write-Host "1. Start staging server: .\infra\staging\scripts\start-staging.ps1" -ForegroundColor White
Write-Host "2. Run tests: .\infra\staging\scripts\test-staging.ps1" -ForegroundColor White
Write-Host "3. Access staging: http://localhost:$STAGING_PORT" -ForegroundColor White
Write-Host ""
Write-Host "🔑 Test Credentials:" -ForegroundColor Yellow
Write-Host "Admin: admin@staging.mycourses.local / staging123" -ForegroundColor White
Write-Host "Instructor: instructor@staging.mycourses.local / staging123" -ForegroundColor White
Write-Host "Student: student@staging.mycourses.local / staging123" -ForegroundColor White