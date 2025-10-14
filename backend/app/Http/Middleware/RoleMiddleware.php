<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }
        
        // التحقق من حقل role أولاً
        if (isset($user->role) && $user->role === $role) {
            return $next($request);
        }
        
        // التحقق من hasRole trait مع try-catch
        try {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return $next($request);
            }
        } catch (\Exception $e) {
            // تجاهل الخطأ وانتقل للطريقة التالية
        }
        
        // التحقق المباشر من قاعدة البيانات
        try {
            $hasRole = \DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', get_class($user))
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', $role)
                ->exists();
                
            if ($hasRole) {
                return $next($request);
            }
        } catch (\Exception $e) {
            // تجاهل الخطأ
        }
        
        return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
    }
}