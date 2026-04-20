<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Correlation-Id') ?? $request->header('X-Request-Id');
        $id = is_string($incoming) && $incoming !== '' ? substr($incoming, 0, 128) : (string) Str::uuid();

        $request->attributes->set('correlation_id', $id);
        Log::withContext(['correlation_id' => $id]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $id, false);

        return $response;
    }
}
