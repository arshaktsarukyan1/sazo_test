<?php

use App\Http\Controllers\Webhooks\ShopifyWebhookController;
use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['api', 'throttle:webhooks'])
                ->post('/webhooks/shopify/orders', [ShopifyWebhookController::class, 'orders']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AssignCorrelationId::class,
        ]);
        $middleware->alias([
            'internal.api' => \App\Http\Middleware\InternalApiAuth::class,
            'public.metrics' => \App\Http\Middleware\RecordPublicRouteMetrics::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'correlation_id' => $request->attributes->get('correlation_id'),
            ], $e->status);
        });
    })->create();
