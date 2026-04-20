<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixes BUG-18. Requires 2FA confirmation on sensitive routes.
 *
 * Expects the User model to expose:
 *   - two_factor_confirmed_at  (timestamp, set after TOTP verification)
 * as shipped by laravel/fortify or manually added.
 *
 * For roles admin/owner, access is BLOCKED until two_factor_confirmed_at is set.
 * For editor / employee, this middleware is a no-op (they aren't routed through it).
 */
class Require2fa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if (in_array($user->role, ['admin', 'owner'], true)) {
            if (empty($user->two_factor_confirmed_at)) {
                return redirect()->route('profile.security')
                    ->with('warning', '2FA is required for admin/owner accounts before finalizing payroll.');
            }
        }

        return $next($request);
    }
}
