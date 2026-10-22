<?php

use App\Http\Middleware\ApiAuth;
use App\Http\Middleware\EnsureDemoEnabled;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyTideJwt;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'apiauth' => ApiAuth::class,
            'auth.api' => ApiAuth::class,
            'demo.enabled' => EnsureDemoEnabled::class,
            'role' => EnsureRole::class,
            'tide.auth' => VerifyTideJwt::class,
        ]);

        // NFR-SEC-02: nosniff, X-Frame-Options, Referrer-Policy, CSP.
        $middleware->append(SecurityHeaders::class);

        // API routes authenticate with the session guard until WP2 swaps in
        // Bearer JWT. Cookies + session are prepended so the same-origin
        // React UI can call /api/v1 with the browser session.
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $exception->getMessage(),
                    'details' => $exception->errors(),
                ],
            ], 422);
        });

        // Gate::authorize throws AuthorizationException, which the handler
        // maps to AccessDeniedHttpException before render callbacks run.
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Bạn không có quyền thực hiện hành động này.',
                ],
            ], 403);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Chưa đăng nhập hoặc phiên đã hết hạn.',
                ],
            ], 401);
        });
    })->create();
