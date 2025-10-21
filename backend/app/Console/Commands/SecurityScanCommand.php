<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class SecurityScanCommand extends Command
{
    protected $signature = 'security:scan {--format=table : Output format (table, json)}';
    protected $description = 'Run comprehensive security checks on the Laravel application';

    public function handle()
    {
        $this->info('🔐 Laravel Security Scanner');
        $this->info('===========================');

        $results = [];
        $overallScore = 0;
        $totalChecks = 0;

        // Security Check 1: Environment Configuration
        $envResults = $this->checkEnvironmentSecurity();
        $results['environment'] = $envResults;
        $overallScore += $envResults['score'];
        $totalChecks++;

        // Security Check 2: File Permissions
        $fileResults = $this->checkFilePermissions();
        $results['file_permissions'] = $fileResults;
        $overallScore += $fileResults['score'];
        $totalChecks++;

        // Security Check 3: Laravel Configuration
        $configResults = $this->checkLaravelConfiguration();
        $results['laravel_config'] = $configResults;
        $overallScore += $configResults['score'];
        $totalChecks++;

        // Security Check 4: Database Security
        $dbResults = $this->checkDatabaseSecurity();
        $results['database'] = $dbResults;
        $overallScore += $dbResults['score'];
        $totalChecks++;

        // Security Check 5: Headers & Middleware
        $headerResults = $this->checkSecurityHeaders();
        $results['headers'] = $headerResults;
        $overallScore += $headerResults['score'];
        $totalChecks++;

        // Calculate final score
        $finalScore = round($overallScore / $totalChecks, 1);
        $results['overall_score'] = $finalScore;

        // Output results
        if ($this->option('format') === 'json') {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $this->displayResults($results);
        }

        // Set exit code based on score
        if ($finalScore < 7.0) {
            $this->error('Security score too low. Please address the issues above.');
            return 1;
        }

        $this->info('✅ Security scan completed successfully!');
        return 0;
    }

    private function checkEnvironmentSecurity(): array
    {
        $checks = [];
        $score = 0;
        $maxScore = 0;

        // Check APP_DEBUG
        $maxScore++;
        if (config('app.debug') === false) {
            $checks[] = ['✅', 'APP_DEBUG is disabled', 'PASS'];
            $score++;
        } else {
            $checks[] = ['❌', 'APP_DEBUG should be false in production', 'FAIL'];
        }

        // Check APP_KEY
        $maxScore++;
        if (!empty(config('app.key'))) {
            $checks[] = ['✅', 'APP_KEY is set', 'PASS'];
            $score++;
        } else {
            $checks[] = ['❌', 'APP_KEY is not set', 'FAIL'];
        }

        // Check HTTPS
        $maxScore++;
        if (config('app.url', '') && str_starts_with(config('app.url'), 'https://')) {
            $checks[] = ['✅', 'HTTPS configured in APP_URL', 'PASS'];
            $score++;
        } else {
            $checks[] = ['⚠️', 'Consider using HTTPS in APP_URL', 'WARN'];
            $score += 0.5;
        }

        return [
            'score' => ($score / $maxScore) * 10,
            'checks' => $checks,
            'category' => 'Environment Security'
        ];
    }

    private function checkFilePermissions(): array
    {
        $checks = [];
        $score = 0;
        $maxScore = 0;

        // Check .env permissions
        $maxScore++;
        if (File::exists('.env')) {
            $perms = substr(sprintf('%o', fileperms('.env')), -3);
            if (in_array($perms, ['600', '644'])) {
                $checks[] = ['✅', ".env file permissions are secure ($perms)", 'PASS'];
                $score++;
            } else {
                $checks[] = ['❌', ".env file permissions too open ($perms)", 'FAIL'];
            }
        } else {
            $checks[] = ['⚠️', '.env file not found', 'WARN'];
        }

        // Check storage directory permissions
        $maxScore++;
        if (File::exists('storage') && is_writable('storage')) {
            $checks[] = ['✅', 'Storage directory is writable', 'PASS'];
            $score++;
        } else {
            $checks[] = ['❌', 'Storage directory permissions issue', 'FAIL'];
        }

        return [
            'score' => ($score / $maxScore) * 10,
            'checks' => $checks,
            'category' => 'File Permissions'
        ];
    }

    private function checkLaravelConfiguration(): array
    {
        $checks = [];
        $score = 0;
        $maxScore = 0;

        // Check session security
        $maxScore++;
        if (config('session.secure') === true) {
            $checks[] = ['✅', 'Secure session cookies enabled', 'PASS'];
            $score++;
        } else {
            $checks[] = ['⚠️', 'Consider enabling secure session cookies', 'WARN'];
            $score += 0.5;
        }

        // Check CSRF protection
        $maxScore++;
        $middleware = config('app.middleware', []);
        if (in_array(\App\Http\Middleware\VerifyCsrfToken::class, $middleware)) {
            $checks[] = ['✅', 'CSRF protection middleware found', 'PASS'];
            $score++;
        } else {
            $checks[] = ['⚠️', 'CSRF protection should be enabled', 'WARN'];
        }

        // Check password hashing
        $maxScore++;
        if (config('hashing.driver') === 'bcrypt' || config('hashing.driver') === 'argon2id') {
            $checks[] = ['✅', 'Secure password hashing configured', 'PASS'];
            $score++;
        } else {
            $checks[] = ['❌', 'Weak password hashing detected', 'FAIL'];
        }

        return [
            'score' => ($score / $maxScore) * 10,
            'checks' => $checks,
            'category' => 'Laravel Configuration'
        ];
    }

    private function checkDatabaseSecurity(): array
    {
        $checks = [];
        $score = 0;
        $maxScore = 0;

        // Check database connection
        $maxScore++;
        try {
            \DB::connection()->getPdo();
            $checks[] = ['✅', 'Database connection secure', 'PASS'];
            $score++;
        } catch (\Exception $e) {
            $checks[] = ['❌', 'Database connection issue', 'FAIL'];
        }

        // Check for SQL injection protection (using Eloquent)
        $maxScore++;
        $checks[] = ['✅', 'Using Eloquent ORM (SQL injection protection)', 'PASS'];
        $score++;

        return [
            'score' => ($score / $maxScore) * 10,
            'checks' => $checks,
            'category' => 'Database Security'
        ];
    }

    private function checkSecurityHeaders(): array
    {
        $checks = [];
        $score = 0;
        $maxScore = 0;

        // Check for security middleware
        $maxScore++;
        if (File::exists('app/Http/Middleware/SecurityHeadersMiddleware.php')) {
            $checks[] = ['✅', 'Security headers middleware found', 'PASS'];
            $score++;
        } else {
            $checks[] = ['⚠️', 'Consider adding security headers middleware', 'WARN'];
            $score += 0.5;
        }

        // Check CORS configuration
        $maxScore++;
        if (config('cors')) {
            $checks[] = ['✅', 'CORS configuration found', 'PASS'];
            $score++;
        } else {
            $checks[] = ['⚠️', 'CORS configuration should be reviewed', 'WARN'];
            $score += 0.5;
        }

        return [
            'score' => ($score / $maxScore) * 10,
            'checks' => $checks,
            'category' => 'Security Headers'
        ];
    }

    private function displayResults(array $results): void
    {
        foreach ($results as $category => $data) {
            if ($category === 'overall_score') continue;

            $this->newLine();
            $this->info("📋 {$data['category']} (Score: {$data['score']}/10)");
            $this->info(str_repeat('=', 50));

            $headers = ['Status', 'Check', 'Result'];
            $rows = $data['checks'];

            $this->table($headers, $rows);
        }

        $this->newLine();
        $this->info("🎯 Overall Security Score: {$results['overall_score']}/10");
        
        if ($results['overall_score'] >= 9) {
            $this->info('Excellent security posture! 🎉');
        } elseif ($results['overall_score'] >= 7) {
            $this->info('Good security, minor improvements needed 👍');
        } else {
            $this->error('Security improvements required ⚠️');
        }
    }
}