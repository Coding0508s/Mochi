<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalInstitutionIngest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('services.external_institutions.enabled', false)) {
            abort(503, 'External institution ingest is disabled.');
        }

        $expected = config('services.external_institutions.bearer_token');
        if (! is_string($expected) || $expected === '') {
            abort(503, 'External institution ingest is not configured.');
        }

        if ($request->bearerToken() !== $expected) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
