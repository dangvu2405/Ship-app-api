<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // API middleware
        $middleware->api(prepend: [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\SetApiLocale::class,
            \App\Http\Middleware\HandleApiErrors::class,
        ]);

        // Register custom middleware aliases
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'tenant.context' => \App\Http\Middleware\EnsureTenantContext::class,
            'audit.sensitive_reads' => \App\Http\Middleware\LogSensitiveResourceReads::class,
            'sod' => \App\Http\Middleware\SodGuard::class,
            'track.actions' => \App\Http\Middleware\TrackUserActions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Logging levels mapping
        $exceptions->level(\PDOException::class, \Psr\Log\LogLevel::CRITICAL);
        $exceptions->level(\Illuminate\Database\QueryException::class, \Psr\Log\LogLevel::CRITICAL);
        $exceptions->level(\Illuminate\Auth\AuthenticationException::class, \Psr\Log\LogLevel::INFO);
        $exceptions->level(\Illuminate\Auth\Access\AuthorizationException::class, \Psr\Log\LogLevel::WARNING);
        $exceptions->level(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class, \Psr\Log\LogLevel::WARNING);
        $exceptions->level(\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class, \Psr\Log\LogLevel::WARNING);
        $exceptions->level(\Illuminate\Database\Eloquent\ModelNotFoundException::class, \Psr\Log\LogLevel::WARNING);
        $exceptions->level(\Illuminate\Validation\ValidationException::class, \Psr\Log\LogLevel::NOTICE);

        // Always return JSON for API routes
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Validation Exception
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('api.validation_failed'),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Model Not Found Exception
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('api.resource_not_found'),
                ], 404);
            }
        });

        // Authentication Exception
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('api.unauthenticated'),
                ], 401);
            }
        });

        // Authorization Exception
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: __('api.access_denied'),
                ], 403);
            }
        });

        // NotFoundHttpException
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('api.route_not_found'),
                ], 404);
            }
        });

        // MethodNotAllowedHttpException
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('api.method_not_allowed'),
                ], 405);
            }
        });

        // Database Query Exception
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = __('api.database_error');

                // Check for common database errors
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();

                if ($errorCode === 23000) { // Integrity constraint violation
                    $message = __('api.integrity_constraints_error');
                } elseif (str_contains($errorMessage, "doesn't exist") || str_contains($errorMessage, 'Base table')) {
                    $message = __('api.database_table_not_found');
                } elseif (str_contains($errorMessage, 'Unknown column')) {
                    $message = __('api.database_column_not_found');
                } elseif (str_contains($errorMessage, 'Duplicate entry')) {
                    $message = __('api.duplicate_entry');
                } elseif (str_contains($errorMessage, 'foreign key constraint')) {
                    $message = __('api.foreign_key_constraint');
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });

        // PDO Exception
        $exceptions->render(function (\PDOException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database connection error',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });

        // General Exception Handler - Catch all other errors
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                // Never expose raw exception messages in production — always log and return generic
                if (config('app.debug')) {
                    $message = $e->getMessage() ?: 'An error occurred';
                } elseif ($statusCode >= 500) {
                    $message = __('api.internal_server_error');
                } else {
                    // 4xx from HttpException subclasses are safe to forward as-is
                    $message = $e->getMessage() ?: __('api.generic_error');
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error' => config('app.debug') ? [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ] : null,
                ], $statusCode);
            }
        });
    })->create();
