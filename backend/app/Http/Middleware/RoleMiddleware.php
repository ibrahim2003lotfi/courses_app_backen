<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $role)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                Log::warning('RoleMiddleware: No authenticated user', [
                    'route' => $request->path(),
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'message' => 'Unauthenticated. Please login first.',
                    'error' => 'no_user',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Check role field directly on user model
            if (isset($user->role) && $user->role === $role) {
                return $next($request);
            }
            
            // Check raw database value
            try {
                $dbRole = \DB::table('users')->where('id', $user->id)->value('role');
                if ($dbRole === $role) {
                    return $next($request);
                }
            } catch (\Exception $e) {
                Log::warning('RoleMiddleware: Database role check failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Check using hasRole method if available
            try {
                if (method_exists($user, 'hasRole')) {
                    // Reload user to get fresh roles from database
                    $freshUser = \App\Models\User::find($user->id);
                    if ($freshUser && $freshUser->hasRole($role)) {
                        return $next($request);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('RoleMiddleware: hasRole check failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Check using Spatie permission tables directly
            try {
                if (\Schema::hasTable('model_has_roles') && \Schema::hasTable('roles')) {
                    $hasRole = \DB::table('model_has_roles')
                        ->where('model_id', $user->id)
                        ->where('model_type', get_class($user))
                        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                        ->where('roles.name', $role)
                        ->exists();
                        
                    if ($hasRole) {
                        return $next($request);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('RoleMiddleware: Database roles check failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // User doesn't have the required role
            Log::warning('RoleMiddleware: User does not have required role', [
                'user_id' => $user->id,
                'required_role' => $role,
                'user_role' => $user->role ?? 'not set',
            ]);
            
            return response()->json([
                'message' => 'Unauthorized. You do not have the required role: ' . $role,
                'error' => 'unauthorized',
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('RoleMiddleware: Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'An error occurred while checking permissions.',
                'error' => 'middleware_error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}