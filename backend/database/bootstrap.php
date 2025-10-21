<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

(new LoadEnvironmentVariables)->bootstrap($app);
(new LoadConfiguration)->bootstrap($app);

return $app;