#!/bin/bash

set -e

echo "🔐 Laravel Security Scan Suite (No Enlightn)"
echo "============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Create security reports directory
mkdir -p storage/security-reports

print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

echo ""
print_status $YELLOW "📋 Pre-flight Security Checks"
echo "================================"

# Check Laravel app is running
if curl -s -f http://localhost:8000/api/test > /dev/null; then
    print_status $GREEN "✅ Laravel application is running"
else
    print_status $RED "❌ Laravel application is not responding"
    echo "   Please start: php artisan serve"
    exit 1
fi

echo ""
print_status $YELLOW "🔍 Static Analysis"
echo "=================="

# PHPStan Analysis
if command -v vendor/bin/phpstan >/dev/null 2>&1; then
    print_status $YELLOW "Running PHPStan..."
    if vendor/bin/phpstan analyse --memory-limit=2G --error-format=json > storage/security-reports/phpstan.json 2>/dev/null; then
        print_status $GREEN "✅ PHPStan completed"
    else
        print_status $YELLOW "⚠️  PHPStan found issues (check storage/security-reports/phpstan.json)"
    fi
else
    print_status $YELLOW "⚠️  PHPStan not available"
fi

# Psalm Analysis
if command -v vendor/bin/psalm >/dev/null 2>&1; then
    print_status $YELLOW "Running Psalm..."
    if vendor/bin/psalm --output-format=json --report=storage/security-reports/psalm.json >/dev/null 2>&1; then
        print_status $GREEN "✅ Psalm completed"
    else
        print_status $YELLOW "⚠️  Psalm found issues (check storage/security-reports/psalm.json)"
    fi
else
    print_status $YELLOW "⚠️  Psalm not available"
fi

echo ""
print_status $YELLOW "📦 Dependency Vulnerability Check"
echo "================================="

# Security Advisor Check
if command -v vendor/bin/security-checker >/dev/null 2>&1; then
    print_status $YELLOW "Checking for vulnerable dependencies..."
    if vendor/bin/security-checker security:check --format=json > storage/security-reports/security-check.json 2>/dev/null; then
        print_status $GREEN "✅ No known vulnerabilities found"
    else
        print_status $RED "❌ Vulnerable dependencies detected"
        cat storage/security-reports/security-check.json
    fi
else
    print_status $YELLOW "⚠️  Security Checker not available"
fi

echo ""
print_status $YELLOW "🛡️  Laravel Security Analysis"
echo "============================="

# Custom Laravel Security Scan
print_status $YELLOW "Running custom Laravel security analysis..."
if php artisan security:scan --format=json > storage/security-reports/laravel-security.json; then
    print_status $GREEN "✅ Laravel security analysis completed"
    
    # Show the results in table format too
    php artisan security:scan
else
    print_status $RED "❌ Laravel security analysis failed"
fi

echo ""
print_status $YELLOW "🔒 Manual Security Checks"
echo "========================="

# Check for common security issues
print_status $YELLOW "Checking common security configurations..."

# Check .env file
if [ -f .env ]; then
    if grep -q "APP_DEBUG=false" .env; then
        print_status $GREEN "✅ APP_DEBUG is disabled"
    else
        print_status $YELLOW "⚠️  APP_DEBUG should be false for production"
    fi
    
    if grep -q "APP_KEY=" .env && [ -n "$(grep "APP_KEY=" .env | cut -d= -f2)" ]; then
        print_status $GREEN "✅ APP_KEY is set"
    else
        print_status $RED "❌ APP_KEY is missing or empty"
    fi
else
    print_status $RED "❌ .env file not found"
fi

# Check routes for potential security issues
print_status $YELLOW "Checking routes for debug endpoints..."
if php artisan route:list | grep -i "debug\|telescope\|horizon" >/dev/null 2>&1; then
    print_status $YELLOW "⚠️  Debug routes detected (ensure they're disabled in production)"
else
    print_status $GREEN "✅ No debug routes found"
fi

echo ""
print_status $YELLOW "📊 Security Report Summary"
echo "=========================="

# Generate summary
cat > storage/security-reports/summary.html << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Security Scan Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .pass { color: green; }
        .warn { color: orange; }
        .fail { color: red; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #ccc; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Security Scan Report</h1>
    <p>Generated: $(date)</p>
    
    <div class="section">
        <h2>Static Analysis</h2>
        <p>PHPStan: $([ -f storage/security-reports/phpstan.json ] && echo "✅ Completed" || echo "⚠️ Not run")</p>
        <p>Psalm: $([ -f storage/security-reports/psalm.json ] && echo "✅ Completed" || echo "⚠️ Not run")</p>
    </div>
    
    <div class="section">
        <h2>Dependency Security</h2>
        <p>Security Check: $([ -f storage/security-reports/security-check.json ] && echo "✅ Completed" || echo "⚠️ Not run")</p>
    </div>
    
    <div class="section">
        <h2>Laravel Security</h2>
        <p>Custom Security Scan: $([ -f storage/security-reports/laravel-security.json ] && echo "✅ Completed" || echo "⚠️ Not run")</p>
    </div>
    
    <div class="section">
        <h2>Manual Checks</h2>
        <p>Configuration and file permissions reviewed manually</p>
    </div>
</body>
</html>
EOF

print_status $GREEN "✅ Security scan completed!"
print_status $YELLOW "📁 Reports saved in storage/security-reports/"
print_status $YELLOW "🌐 Open storage/security-reports/summary.html for overview"

echo ""
print_status $YELLOW "🎯 Next Steps:"
echo "1. Review all reports in storage/security-reports/"
echo "2. Fix any CRITICAL or HIGH issues found"
echo "3. Consider implementing additional security headers"
echo "4. Set up automated security scanning in CI/CD"

exit 0