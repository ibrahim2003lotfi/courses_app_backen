<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateEnvironment extends Command
{
    protected $signature = 'env:validate';
    protected $description = 'Validate environment configuration for security';

    public function handle()
    {
        $this->info('Validating environment configuration...');

        $validations = [
            'APP_ENV' => $this->validateAppEnv(),
            'APP_DEBUG' => $this->validateAppDebug(),
            'APP_URL' => $this->validateAppUrl(),
            'Database' => $this->validateDatabase(),
            'Redis' => $this->validateRedis(),
            'Session' => $this->validateSession(),
            'CORS' => $this->validateCors(),
        ];

        $this->table(['Component', 'Status', 'Message'], $validations);

        $failed = collect($validations)->where('Status', 'FAILED')->count();
        
        if ($failed > 0) {
            $this->error("{$failed} configuration issues found!");
            return 1;
        }

        $this->info('All environment configurations are valid!');
        return 0;
    }

    private function validateAppEnv()
    {
        $env = env('APP_ENV');
        $valid = in_array($env, ['production', 'staging']);
        
        return [
            'APP_ENV',
            $valid ? 'PASS' : 'FAILED',
            $valid ? "Set to {$env}" : "Should be 'production' or 'staging', currently: {$env}"
        ];
    }

    private function validateAppDebug()
    {
        $debug = env('APP_DEBUG');
        $valid = $debug === false || $debug === 'false';
        
        return [
            'APP_DEBUG',
            $valid ? 'PASS' : 'FAILED',
            $valid ? 'Debug mode disabled' : 'Debug mode should be false in production'
        ];
    }

    private function validateAppUrl()
    {
        $url = env('APP_URL');
        $valid = str_starts_with($url, 'https://');
        
        return [
            'APP_URL',
            $valid ? 'PASS' : 'FAILED',
            $valid ? "Secure URL: {$url}" : "URL should use HTTPS, currently: {$url}"
        ];
    }

    private function validateDatabase()
    {
        $ssl = env('DB_SSLMODE');
        $valid = app()->environment('production') ? $ssl === 'require' : true;
        
        return [
            'Database SSL',
            $valid ? 'PASS' : 'WARNING',
            $valid ? 'SSL configured' : 'DB_SSLMODE should be "require" in production'
        ];
    }

    private function validateRedis()
    {
        $password = env('REDIS_PASSWORD');
        $valid = app()->environment('production') ? !empty($password) : true;
        
        return [
            'Redis Auth',
            $valid ? 'PASS' : 'WARNING',
            $valid ? 'Redis password set' : 'REDIS_PASSWORD should be set in production'
        ];
    }

    private function validateSession()
    {
        $secure = env('SESSION_SECURE_COOKIE');
        $valid = app()->environment('production') ? $secure === true : true;
        
        return [
            'Session Cookie',
            $valid ? 'PASS' : 'WARNING',
            $valid ? 'Secure cookies enabled' : 'SESSION_SECURE_COOKIE should be true in production'
        ];
    }

    private function validateCors()
    {
        $origins = env('CORS_ALLOWED_ORIGINS');
        $valid = !empty($origins) && !str_contains($origins, '*');
        
        return [
            'CORS Origins',
            $valid ? 'PASS' : 'WARNING',
            $valid ? 'Specific origins configured' : 'CORS should specify exact origins, not wildcards'
        ];
    }
}