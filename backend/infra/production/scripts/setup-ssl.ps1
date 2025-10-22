# SSL Certificate Setup for Production
Write-Host "Setting up SSL certificates for production..." -ForegroundColor Yellow

# SSL Configuration
$DOMAIN = "api.mycourses.com"
$SSL_PATH = "C:\ssl\mycourses"
$CERT_PATH = "$SSL_PATH\$DOMAIN.crt"
$KEY_PATH = "$SSL_PATH\$DOMAIN.key"

# Create SSL directory
New-Item -ItemType Directory -Path $SSL_PATH -Force | Out-Null

Write-Host ""
Write-Host "SSL Certificate Setup Options:" -ForegroundColor Cyan
Write-Host "1. Let's Encrypt (Free, Auto-renewal)" -ForegroundColor White
Write-Host "2. Commercial Certificate (Paid, Manual)" -ForegroundColor White
Write-Host "3. Self-signed (Development only)" -ForegroundColor White
Write-Host ""

$choice = Read-Host "Choose option (1-3)"

switch ($choice) {
    "1" {
        Write-Host "Setting up Let's Encrypt certificate..." -ForegroundColor Green
        
        # Install Certbot (if not already installed)
        if (-not (Get-Command certbot -ErrorAction SilentlyContinue)) {
            Write-Host "Installing Certbot..."
            # For Windows, download from https://certbot.eff.org/
            Write-Host "Please install Certbot from: https://certbot.eff.org/instructions?ws=other&os=windows"
            Write-Host "Then run: certbot certonly --standalone -d $DOMAIN"
        } else {
            # Generate Let's Encrypt certificate
            certbot certonly --standalone -d $DOMAIN --email admin@mycourses.com --agree-tos --non-interactive
            
            # Copy certificates to our SSL directory
            Copy-Item "C:\Certbot\live\$DOMAIN\fullchain.pem" $CERT_PATH
            Copy-Item "C:\Certbot\live\$DOMAIN\privkey.pem" $KEY_PATH
        }
    }
    
    "2" {
        Write-Host "Commercial Certificate Setup" -ForegroundColor Green
        Write-Host "1. Purchase SSL certificate from a CA (DigiCert, Comodo, etc.)"
        Write-Host "2. Generate CSR (Certificate Signing Request)"
        Write-Host "3. Submit CSR to CA and download certificate"
        Write-Host "4. Place certificate files in $SSL_PATH"
        Write-Host ""
        Write-Host "Expected files:"
        Write-Host "  - $CERT_PATH (Certificate + Intermediate)"
        Write-Host "  - $KEY_PATH (Private Key)"
    }
    
    "3" {
        Write-Host "Generating self-signed certificate (DEVELOPMENT ONLY)..." -ForegroundColor Yellow
        
        # Generate self-signed certificate using OpenSSL
        if (Get-Command openssl -ErrorAction SilentlyContinue) {
            openssl req -x509 -newkey rsa:4096 -keyout $KEY_PATH -out $CERT_PATH -days 365 -nodes -subj "/C=US/ST=State/L=City/O=Organization/CN=$DOMAIN"
            Write-Host "Self-signed certificate generated" -ForegroundColor Green
        } else {
            Write-Host "OpenSSL not found. Please install OpenSSL or use PowerShell certificate commands."
        }
    }
}

# Set certificate permissions
if (Test-Path $CERT_PATH) {
    $acl = Get-Acl $CERT_PATH
    $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule("IIS_IUSRS", "Read", "Allow")
    $acl.SetAccessRule($accessRule)
    Set-Acl -Path $CERT_PATH -AclObject $acl
    Set-Acl -Path $KEY_PATH -AclObject $acl
    
    Write-Host "SSL certificate permissions set" -ForegroundColor Green
}

Write-Host ""
Write-Host "SSL Setup Notes:" -ForegroundColor Cyan
Write-Host "1. Update .env.production with SSL paths" -ForegroundColor White
Write-Host "2. Configure web server (IIS/Apache/Nginx) to use certificates" -ForegroundColor White
Write-Host "3. Test HTTPS access: https://$DOMAIN" -ForegroundColor White
Write-Host "4. Set up auto-renewal for Let's Encrypt certificates" -ForegroundColor White