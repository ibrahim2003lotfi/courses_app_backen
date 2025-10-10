<?php

namespace app\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // مسارات الـ API (مثل register, login ...)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // المسارات العادية (صفحات الويب)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
