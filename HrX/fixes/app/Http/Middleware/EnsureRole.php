<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixes BUG-30 partially. Route-level role check so the 'editor' role
 * cannot hit admin-only routes even via direct URL.
 *
 * Register in bootstrap/app.php (Laravel 11) or Kernel.php (Laravel 10):
 *   $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
 *
 * Usage:
 *   Route::middleware(['auth','role:admin|owner'])->group(...);
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        $allowed = explode('|', $roles);

        if (!in_array($user->role, $allowed, true)) {
            abort(403, "Your role ({$user->role}) is not permitted here.");
        }

        return $next($request);
    }
}
