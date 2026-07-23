<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces multifactor authentication for internal users:
 * - staff without MFA enrolled are forced to the enrollment screen;
 * - staff with MFA enrolled must pass the TOTP challenge each session.
 */
class EnsureMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isStaff() && config('security.mfa_required_for_staff', true)) {
            if ($user->mustEnrollMfa() && ! $request->routeIs('mfa.*', 'logout')) {
                return redirect()->route('mfa.enroll');
            }

            if ($user->mfaEnabled() && ! $request->session()->get('mfa_passed') && ! $request->routeIs('mfa.*', 'logout')) {
                return redirect()->route('mfa.challenge');
            }
        }

        return $next($request);
    }
}
