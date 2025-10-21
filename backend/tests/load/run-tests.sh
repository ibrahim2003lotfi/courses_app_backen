#!/bin/bash

echo "🚀 Starting Load Tests for MyCourses API"
echo "========================================="

# Ensure Laravel app is running
echo "📋 Pre-flight checks..."
response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/test)
if [ "$response" != "200" ]; then
    echo "❌ API not responding at http://localhost:8000/api/test"
    echo "   Please start your Laravel development server: php artisan serve"
    exit 1
fi

echo "✅ API is responding"
echo ""

# Create results directory
mkdir -p results

# Test 1: Public Endpoints (Heavy Load)
echo "🌐 Testing Public Endpoints..."
k6 run --out json=results/public-endpoints.json 01-public-endpoints.js

echo ""

# Test 2: Authentication Workflow
echo "🔐 Testing Authentication Workflow..."
k6 run --out json=results/auth-workflow.json 02-auth-workflow.js

echo ""

# Test 3: Instructor Operations (Light Load)
echo "👨‍🏫 Testing Instructor Workflow..."
k6 run --out json=results/instructor-workflow.json 03-instructor-workflow.js

echo ""

# Test 4: Payment System Stress Test
echo "💳 Testing Payment System..."
k6 run --out json=results/payment-stress.json 04-payment-stress.js

echo ""
echo "🎯 All load tests completed!"
echo "📊 Results saved in results/ directory"
echo ""
echo "📈 Quick Summary:"
echo "=================="

# Generate quick summary from results
if command -v jq &> /dev/null; then
    for file in results/*.json; do
        if [ -f "$file" ]; then
            echo "$(basename "$file"):"
            echo "  - Total Requests: $(jq '.metrics.http_reqs.values.count' "$file")"
            echo "  - Failed Requests: $(jq '.metrics.http_req_failed.values.rate * 100' "$file")%"
            echo "  - Avg Response Time: $(jq '.metrics.http_req_duration.values.avg' "$file")ms"
            echo "  - 95th Percentile: $(jq '.metrics.http_req_duration.values["p(95)"]' "$file")ms"
            echo ""
        fi
    done
else
    echo "Install 'jq' for detailed summaries: sudo apt install jq"
fi