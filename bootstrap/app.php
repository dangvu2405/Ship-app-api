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
        // API middleware
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\HandleApiErrors::class,
        ]);

        // Register custom middleware aliases
        $middleware->alias([
            'auth'                  => \App\Http\Middleware\Authenticate::class,
            'role'                  => \App\Http\Middleware\RoleMiddleware::class,
            'permission'            => \App\Http\Middleware\PermissionMiddleware::class,
            'tenant.context'        => \App\Http\Middleware\EnsureTenantContext::class,
            'audit.sensitive_reads' => \App\Http\Middleware\LogSensitiveResourceReads::class,
            'sod'                   => \App\Http\Middleware\SodGuard::class,
            'track.actions'         => \App\Http\Middleware\TrackUserActions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for API routes
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
        
        // Validation Exception
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
        
        // Model Not Found Exception
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                ], 404);
            }
        });
        
        // Authentication Exception
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login.',
                ], 401);
            }
        });
        
        // Authorization Exception
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Access denied. You do not have permission.',
                ], 403);
            }
        });
        
        // NotFoundHttpException
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Route not found',
                ], 404);
            }
        });
        
        // MethodNotAllowedHttpException
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Method not allowed',
                ], 405);
            }
        });
        
        // Database Query Exception
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = 'Database error occurred';
                
                // Check for common database errors
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                
                if ($errorCode === 23000) { // Integrity constraint violation
                    $message = 'Cannot perform this action due to data integrity constraints';
                } elseif (str_contains($errorMessage, "doesn't exist") || str_contains($errorMessage, 'Base table')) {
                    $message = 'Database table not found';
                } elseif (str_contains($errorMessage, "Unknown column")) {
                    $message = 'Database column not found';
                } elseif (str_contains($errorMessage, "Duplicate entry")) {
                    $message = 'Duplicate entry. This record already exists.';
                } elseif (str_contains($errorMessage, "foreign key constraint")) {
                    $message = 'Cannot delete this record because it is being used by other records.';
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
                $message = $e->getMessage() ?: 'An error occurred';
                
                // Don't expose internal errors in production
                if (!config('app.debug') && $statusCode === 500) {
                    $message = 'Internal server error. Please try again later.';
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error' => config('app.debug') ? [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ] : null,
                ], $statusCode);
            }
        });
    })->create();
