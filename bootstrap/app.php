<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureRecentAuthentication;
use App\Http\Middleware\EnsureTrackedSessionIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureWorkspaceFeatureAvailable;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrackAuthenticatedSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts();
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->validateCsrfTokens(except: ['heartbeat/*']);
        $middleware->append(AddSecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'api.token' => AuthenticateApiToken::class,
            'recent.auth' => EnsureRecentAuthentication::class,
            'workspace.feature' => EnsureWorkspaceFeatureAvailable::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureTrackedSessionIsActive::class,
            TrackAuthenticatedSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (
                $response->getStatusCode() !== 404
                || $request->expectsJson()
                || $request->is('api/*')
            ) {
                return $response;
            }

            return Inertia::render('errors/not-found', [
                'canRegister' => Route::has('register'),
            ])->toResponse($request)->setStatusCode(404);
        });
    })->create();
