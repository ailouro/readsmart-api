<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Replaces the old `?key=ADMIN_PANEL_KEY` query-string guard with a real
 * session login. Any route behind this middleware requires a logged-in
 * user whose role is 'admin'.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        if (($user->role ?? null) !== 'admin') {
            Auth::guard('web')->logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'That account is not an administrator.']);
        }

        return $next($request);
    }
}