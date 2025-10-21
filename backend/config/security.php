<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for the application
    |
    */

    'headers' => [
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'hsts' => [
            'enabled' => env('APP_ENV') === 'production',
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => false,
        ],
    ],

    'csp' => [
        'enabled' => true,
        'report_only' => false,
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", "data:", "https:"],
            'connect-src' => ["'self'"],
            'font-src' => ["'self'"],
            'object-src' => ["'none'"],
            'media-src' => ["'self'", "https:"],
            'frame-src' => ["'none'"],
            'base-uri' => ["'self'"],
        ],
    ],

    'rate_limiting' => [
        'api' => [
            'max_attempts' => 100,
            'decay_minutes' => 1,
        ],
        'auth' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
        ],
        'uploads' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
        ],
    ],

    'uploads' => [
        'max_file_size' => 524288000, // 500MB
        'allowed_mimes' => [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska',
        ],
        'allowed_extensions' => [
            'mp4', 'mov', 'avi', 'mkv', 'm4v'
        ],
    ],
];