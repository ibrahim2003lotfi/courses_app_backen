<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verification Code</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .container { 
            max-width: 600px; 
            margin: 20px auto; 
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header { 
            background: #4F46E5; 
            color: white; 
            padding: 30px; 
            text-align: center; 
        }
        .content {
            padding: 40px;
        }
        .code { 
            font-size: 42px; 
            font-weight: bold; 
            text-align: center; 
            margin: 40px 0; 
            color: #4F46E5;
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            letter-spacing: 8px;
            border: 2px dashed #e2e8f0;
        }
        .footer { 
            margin-top: 40px; 
            padding-top: 20px; 
            border-top: 1px solid #e2e8f0; 
            font-size: 14px; 
            color: #64748b; 
            text-align: center;
        }
        .warning {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            padding: 15px;
            border-radius: 8px;
            margin: 25px 0;
            color: #92400e;
        }
        .info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0; font-size: 28px;">📚 {{ config('app.name', 'My Courses App') }}</h1>
            <p style="margin:10px 0 0 0; opacity: 0.9;">Account Verification</p>
        </div>
        
        <div class="content">
            <h2 style="color: #1f2937; margin-bottom: 10px;">Hello {{ $user->name }},</h2>
            
            <p style="font-size: 16px; color: #4b5563;">Welcome to {{ config('app.name', 'My Courses App') }}! To complete your registration, please use the verification code below:</p>
            
            <div class="code">{{ $code }}</div>
            
            <div class="warning">
                <strong style="color: #dc2626;">⚠️ Important:</strong> This code will expire in <strong>{{ $expires_in }}</strong>. Do not share this code with anyone.
            </div>
            
            <div class="info">
                <strong>💡 Tip:</strong> Enter this code in the verification screen of the app to activate your account.
            </div>
            
            <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
                If you didn't create an account with {{ config('app.name', 'My Courses App') }}, please ignore this email.
            </p>
        </div>
        
        <div class="footer">
            <p style="margin: 5px 0;">&copy; {{ date('Y') }} {{ config('app.name', 'My Courses App') }}. All rights reserved.</p>
            <p style="margin: 5px 0; font-size: 12px; color: #94a3b8;">This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>