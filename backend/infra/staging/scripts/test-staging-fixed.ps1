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

# Test 1: API Health Check
Test-Endpoint "API Health" "$STAGING_URL/api/test"

Write-Host ""
Write-Host "Test Summary" -ForegroundColor Yellow
Write-Host "============" -ForegroundColor Yellow

if ($FAILED_TESTS -eq 0) {
    Write-Host "All tests passed!" -ForegroundColor Green
} else {
    Write-Host "$FAILED_TESTS test(s) failed." -ForegroundColor Red
}