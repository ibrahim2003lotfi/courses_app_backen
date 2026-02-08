# Email Setup Guide

## Current Situation

Your backend is configured to use the **'log'** mail driver, which means:
- ✅ Emails are being "sent" (logged to files)
- ❌ Emails are NOT actually delivered to inboxes
- ✅ Verification codes ARE being generated and saved to database
- ✅ Codes are logged in `backend/storage/logs/laravel.log`

## Quick Solution: Check Logs for Code

When you register or click "Resend Code", check your backend terminal or log file:

**Look for lines like:**
```
🔑 Verification code for your-email@gmail.com: 123456
```

The code will be there!

## Option 1: Use Resend Code Button (Easiest)

1. On verification page, click **"إعادة إرسال الكود"** (Resend Code)
2. Check backend terminal - you'll see the code logged
3. Enter the code from terminal

## Option 2: Check Log File

Open: `backend/storage/logs/laravel.log`

Search for: `🔑 Verification code` or `verification code`

You'll see something like:
```
[2025-12-17 17:30:00] local.INFO: 🔑 Verification code for your-email@gmail.com: 123456
```

## Option 3: Configure Real Email (For Production)

### Using Gmail SMTP:

1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**: 
   - Go to Google Account → Security → 2-Step Verification → App passwords
   - Generate password for "Mail"
3. **Update `.env` file**:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password-here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

4. **Restart backend**:
```bash
php artisan config:clear
php artisan serve --host=0.0.0.0
```

### Using Mailtrap (For Development):

1. Sign up at https://mailtrap.io (free)
2. Get SMTP credentials
3. Update `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
```

## Current Mail Configuration

File: `backend/config/mail.php`
- Default: `env('MAIL_MAILER', 'log')` ← Currently using 'log'

## Test Email Sending

After configuring SMTP, test with:
```bash
# In browser or Postman
GET http://172.20.10.5:8000/api/test-real-email
```

## For Now (Development)

**Just use the Resend Code button and check the backend terminal!**

The code will be logged there clearly now.










