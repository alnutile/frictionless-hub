<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind a load balancer (e.g. an AWS ALB in front of EKS) trust X-Forwarded-* so URLs and HTTPS detection are correct.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Show an in-app error page instead of the bare framework one. 500/503 keep the
        // detailed debug page locally so errors stay easy to diagnose.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            $handled = in_array($status, [403, 404, 419], true)
                || (in_array($status, [500, 503], true) && ! app()->hasDebugModeEnabled());

            if (! $handled || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('error', [
                'status' => $status,
                'isInitiative' => $request->is('initiatives/*'),
                'signedIn' => $request->user() !== null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
