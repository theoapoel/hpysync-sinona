<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Admin always passes
        if ($user->role === 'admin') {
            return $next($request);
        }

        if (!RolePermission::can($user->role, $module)) {
            abort(403);
        }

        return $next($request);
    }
}
