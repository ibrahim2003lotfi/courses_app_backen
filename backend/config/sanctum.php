<?php

use Laravel\Sanctum\Sanctum;

return [

    // Not needed for Flutter Mobile or API
    'stateful' => [],

    // Sanctum should authenticate via bearer token, not web session
    'guard' => ['api'],

    // Tokens never expire unless specified manually
    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        // These are only needed for SPA — safe to keep, won't affect API
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
