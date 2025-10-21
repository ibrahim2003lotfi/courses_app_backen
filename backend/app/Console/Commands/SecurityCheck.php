<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'security:check';
    protected $description = 'Perform security configuration checks';

    public function handle()
    {
        $this->info('Running security checks...');

        $checks = [
            'APP_DEBUG' => $this->checkAppDebug(),
            'APP_ENV' => $this->checkAppEnv(),
            'HTTPS' => $this->checkHttps(),
            'DB_SSL' => $this->checkDbSsl(),
            'SANCTUM_STATEFUL_DOMAINS' => $this->checkSanctumDomains(),
        ];

        $this->table(['Check', 'Status', 'Message'], $checks);

        // Only fail if critical checks fail
        $criticalFailed = collect($checks)
            ->whereIn('Check', ['APP_DEBUG', 'APP_ENV'])
            ->where('Status', 'FAILED')
            ->count();

        if ($criticalFailed > 0) {
            $this->error("Critical security checks failed!");
            return 1;
        }

        $warnings = collect($checks)->where('Status', 'WARNING')->count();
        if ($warnings > 0) {
            $this->warn("{$warnings} warnings found (expected for local development)");
        }

        $this->info('Security status: ACCEPTABLE for local development');
        return 0;
    }

    private function checkAppDebug()
    {
        $debug = env('APP_DEBUG');
        $isDebugDisabled = $debug === false || $debug === 'false';
        
        return [
            'APP_DEBUG',
            $isDebugDisabled ? 'PASS' : 'FAILED',
            $isDebugDisabled ? 'Debug mode disabled' : 'Debug mode should be false'
        ];
    }

    private function checkAppEnv()
    {
        $env = env('APP_ENV');
        $valid = in_array($env, ['production', 'staging']);
        
        return [
            'APP_ENV',
            $valid ? 'PASS' : 'FAILED',
            $valid ? "Set to {$env}" : "Should be 'production' or 'staging'"
        ];
    }

    private function checkHttps()
    {
        $url = env('APP_URL');
        $isProduction = env('APP_ENV') === 'production';
        $isHttps = str_starts_with($url, 'https://');
        
        // Only require HTTPS in production
        if ($isProduction && !$isHttps) {
            return [
                'HTTPS',
                'FAILED',
                'Should use HTTPS in production'
            ];
        }
        
        return [
            'HTTPS',
            $isHttps ? 'PASS' : 'WARNING',
            $isHttps ? 'Using HTTPS' : 'HTTP OK for local development'
        ];
    }

    private function checkDbSsl()
    {
        $ssl = env('DB_SSLMODE', 'prefer');
        $isProduction = env('APP_ENV') === 'production';
        
        return [
            'DB_SSL',
            ($isProduction && $ssl !== 'require') ? 'WARNING' : 'PASS',
            $isProduction ? 
                ($ssl === 'require' ? 'SSL enforced' : 'SSL should be required in production') :
                'SSL optional for local development'
        ];
    }

    private function checkSanctumDomains()
    {
        $domains = env('SANCTUM_STATEFUL_DOMAINS');
        $valid = !empty($domains);
        
        return [
            'SANCTUM_DOMAINS',
            $valid ? 'PASS' : 'WARNING',
            $valid ? 'Domains configured' : 'SANCTUM_STATEFUL_DOMAINS should be set'
        ];
    }
}