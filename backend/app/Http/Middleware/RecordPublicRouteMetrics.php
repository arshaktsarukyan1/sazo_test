<?php

namespace App\Http\Middleware;

use App\Services\Observability\Metrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RecordPublicRouteMetrics
{
    public function __construct(private readonly Metrics $metrics)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('tds_metrics_started_at', microtime(true));

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $start = $request->attributes->get('tds_metrics_started_at');
        if (! is_float($start)) {
            return;
        }

        $ms = (microtime(true) - $start) * 1000.0;
        $route = $request->route()?->getName() ?? $request->path();
        $this->metrics->recordLatencyMs('public_route:'.$route, $ms);

        if ($response->getStatusCode() >= 500) {
            $this->metrics->increment('http_5xx');
        }
        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            $this->metrics->increment('http_4xx');
        }
    }
}
