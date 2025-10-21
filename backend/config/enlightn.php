<?php

return [
    'analyzers' => [
        \Enlightn\Enlightn\Analyzers\Security\AppDebugAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\AppKeyAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\CSRFAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\DebugModeAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\EncryptCookiesAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\FillableGuardedAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\HashDriverAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\HttpsOnlyAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\InsecureURLAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\MassAssignmentAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\PHPVersionAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\ProductionEnvironmentVariablesAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\RawSQLInjectionAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\StableVersionAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\TrustProxiesAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\UnguardedModelsAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\VulnerableDependencyAnalyzer::class,
        \Enlightn\Enlightn\Analyzers\Security\XSSAnalyzer::class,
    ],

    'dont_report' => [
        // Add analyzer classes to skip
    ],

    'baseline_path' => 'enlightn_baseline.php',
    
    'ci_mode' => env('ENLIGHTN_CI_MODE', false),
];