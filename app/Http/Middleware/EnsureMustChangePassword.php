<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMustChangePassword
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! (bool) $user->must_change_password) {
            if ($user !== null && $request->routeIs('password.force-change', 'password.force-change.store')) {
                return redirect()->route('institutions.index');
            }

            return $next($request);
        }

        if ($request->routeIs('password.force-change', 'password.force-change.store', 'logout')) {
            return $next($request);
        }

        return redirect()->route('password.force-change');
    }
}
