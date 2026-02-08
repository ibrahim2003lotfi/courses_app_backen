# Quick Fix: Backend Connection Issue

## Problem
Backend is only listening on `127.0.0.1:8000` which only works on the same computer.

## Solution

### Step 1: Stop the current backend
Press `Ctrl+C` in the terminal where `php artisan serve` is running.

### Step 2: Start backend on all interfaces
Run this command instead:

```bash
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

Or shorter:
```bash
php artisan serve --host=0.0.0.0
```

This makes the backend accessible from other devices on your network.

### Step 3: Update API URL in Flutter

**For Physical Device:**
Your Wi-Fi IP is: `172.20.10.5`

File: `mobile/courses_app/lib/config/api.dart`
```dart
static const String baseUrl = "http://172.20.10.5:8000/api";
```

**For Android Emulator:**
```dart
static const String baseUrl = "http://10.0.2.2:8000/api";
```

**For iOS Simulator:**
```dart
static const String baseUrl = "http://localhost:8000/api";
```

### Step 4: Check Windows Firewall
If it still doesn't work, allow port 8000 through Windows Firewall:

1. Open Windows Defender Firewall
2. Advanced Settings
3. Inbound Rules → New Rule
4. Port → TCP → Specific local ports: 8000
5. Allow the connection
6. Apply to all profiles

### Step 5: Test Connection
1. Make sure phone and computer are on the same Wi-Fi network
2. Try login again
3. Should work now!

## Verify Backend is Accessible

Open browser on your phone and go to:
```
http://172.20.10.5:8000/api/test
```

Should return JSON response if backend is accessible.










