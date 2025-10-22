Write-Host "Running Staging Environment Tests" -ForegroundColor Yellow
Write-Host "=================================" -ForegroundColor Yellow

$STAGING_URL = "http://localhost:8080"
$FAILED_TESTS = 0

function Test-Endpoint {
    param(
        [string]$TestName,
        [string]$Url,
        [int]$ExpectedCode = 200,
        [string]$Method = "GET",
        [hashtable]$Headers = @{},
        [string]$Body = $null
    )
    
    Write-Host "Testing $TestName... " -NoNewline -ForegroundColor Cyan
    
    try {
        $params = @{
            Uri = $Url
            Method = $Method
            UseBasicParsing = $true
            TimeoutSec = 10
        }
        
        if ($Headers.Count -gt 0) {
            $params.Headers = $Headers
        }
        
        if ($Body) {
            $params.Body = $Body
            $params.ContentType = "application/json"
        }
        
        $response = Invoke-WebRequest @params
        
        if ($response.StatusCode -eq $ExpectedCode) {
            Write-Host "PASS" -ForegroundColor Green
            return $true
        } else {
            Write-Host "FAIL (Expected: $ExpectedCode, Got: $($response.StatusCode))" -ForegroundColor Red
            $script:FAILED_TESTS++
            return $false
        }
    } catch {
        Write-Host "FAIL (Error: $($_.Exception.Message))" -ForegroundColor Red
        $script:FAILED_TESTS++
        return $false
    }
}

Write-Host ""
Write-Host "Running API Health Tests" -ForegroundColor Yellow
Write-Host "========================" -ForegroundColor Yellow

# Test 1: API Health Check
Test-Endpoint "API Health" "$STAGING_URL/api/test"

# Test 2: Public Courses Endpoint
Test-Endpoint "Public Courses" "$STAGING_URL/api/courses"

# Test 3: Home Page Data
Test-Endpoint "Home Page" "$STAGING_URL/api/v1/home"

# Test 4: Search Functionality
Test-Endpoint "Search" "$STAGING_URL/api/v1/search?q=test"

Write-Host ""
Write-Host "Running Authentication Tests" -ForegroundColor Yellow
Write-Host "============================" -ForegroundColor Yellow

# Test 5: User Registration
$timestamp = Get-Date -Format "HHmmss"
$registrationData = @{
    name = "Test User $timestamp"
    email = "test$timestamp@staging.local"
    password = "password123"
    password_confirmation = "password123"
    role = "student"
} | ConvertTo-Json

Test-Endpoint "User Registration" "$STAGING_URL/api/register" 201 "POST" @{} $registrationData

# Test 6: User Login
$loginData = @{
    email = "student@staging.mycourses.local"
    password = "staging123"
} | ConvertTo-Json

$loginResult = Test-Endpoint "User Login" "$STAGING_URL/api/login" 200 "POST" @{} $loginData

# Get token for authenticated tests
$token = $null
if ($loginResult) {
    try {
        $loginResponse = Invoke-WebRequest -Uri "$STAGING_URL/api/login" -Method POST -Body $loginData -ContentType "application/json" -UseBasicParsing
        $tokenData = $loginResponse.Content | ConvertFrom-Json
        $token = $tokenData.token
        Write-Host "Authentication token obtained" -ForegroundColor Green
    } catch {
        Write-Host "Could not obtain authentication token" -ForegroundColor Yellow
    }
}

if ($token) {
    Write-Host ""
    Write-Host "Running Authenticated Tests" -ForegroundColor Yellow
    Write-Host "===========================" -ForegroundColor Yellow
    
    $authHeaders = @{ "Authorization" = "Bearer $token" }
    
    # Test 7: Debug User Endpoint
    Test-Endpoint "Debug User Info" "$STAGING_URL/api/debug-user" 200 "GET" $authHeaders
    
    # Test 8: Logout
    Test-Endpoint "User Logout" "$STAGING_URL/api/logout" 200 "POST" $authHeaders
}

Write-Host ""
Write-Host "Running Database Tests" -ForegroundColor Yellow
Write-Host "=====================" -ForegroundColor Yellow

# Test database connectivity through artisan (simplified)
Write-Host "Testing Database Connection... " -NoNewline -ForegroundColor Cyan
try {
    $env:APP_ENV = "staging"
    $dbTest = php artisan migrate:status --env=staging 2>$null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "PASS" -ForegroundColor Green
    } else {
        Write-Host "FAIL" -ForegroundColor Red
        $FAILED_TESTS++
    }
    Set-Location ..
} catch {
    Write-Host "FAIL" -ForegroundColor Red
    $FAILED_TESTS++
}

Write-Host ""
Write-Host "Test Summary" -ForegroundColor Yellow
Write-Host "============" -ForegroundColor Yellow

if ($FAILED_TESTS -eq 0) {
    Write-Host "All tests passed! Staging environment is healthy." -ForegroundColor Green
    Write-Host ""
    Write-Host "Staging Environment Ready:" -ForegroundColor Cyan
    Write-Host "   API: $STAGING_URL" -ForegroundColor White
    Write-Host "   Database: mycourses_staging on localhost:5432" -ForegroundColor White
    Write-Host ""
    Write-Host "Test Credentials:" -ForegroundColor Cyan
    Write-Host "   Admin: admin@staging.mycourses.local / staging123" -ForegroundColor White
    Write-Host "   Instructor: instructor@staging.mycourses.local / staging123" -ForegroundColor White
    Write-Host "   Student: student@staging.mycourses.local / staging123" -ForegroundColor White
    exit 0
} else {
    Write-Host "$FAILED_TESTS test(s) failed. Please check the staging environment." -ForegroundColor Red
    exit 1
}