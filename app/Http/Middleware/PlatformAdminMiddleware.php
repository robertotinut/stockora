<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PlatformAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('platform');

        if (!$guard->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Platform administrator authentication required.'], 401);
            }

            return redirect()->route('platform.login');
        }

        $admin = $guard->user();
        if (!$admin || !$admin->is_active) {
            $guard->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your platform administrator account is inactive.'], 403);
            }

            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Your platform administrator account is inactive.']);
        }

        return $next($request);
    }
}
