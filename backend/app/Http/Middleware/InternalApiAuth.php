<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('tds.internal_api_token', '');

        if ($expectedToken === '') {
            return response()->json([
                'message' => 'Internal API token is not configured.',
            ], 503);
        }

        $providedToken = (string) $request->bearerToken();

        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
