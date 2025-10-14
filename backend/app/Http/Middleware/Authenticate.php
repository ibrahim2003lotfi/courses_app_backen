<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request for API
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // للـ API requests
        if ($request->is('api/*') || $request->expectsJson()) {
            if ($this->authenticate($request, $guards) === 'authentication_failed') {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return $next($request);
        }

        // للـ Web requests
        return parent::handle($request, $next, ...$guards);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

        return 'authentication_failed';
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // لا نريد redirect للـ API
        return null;
    }
}