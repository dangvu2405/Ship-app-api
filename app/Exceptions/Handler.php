<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Src\Domain\Shared\Exceptions\DomainException;
use Src\Domain\Shared\Exceptions\EntityNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class Handler extends ExceptionHandler
{
    /**
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e): JsonResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    private function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => $this->validationError($e),
            $e instanceof AuthenticationException => $this->unauthenticated(),
            $e instanceof AuthorizationException => $this->forbidden($e),
            $e instanceof EntityNotFoundException => $this->entityNotFound($e),
            $e instanceof ModelNotFoundException => $this->modelNotFound(),
            $e instanceof DomainException => $this->domainError($e),
            $e instanceof HttpException => $this->httpError($e),
            default => $this->serverError($e),
        };
    }

    private function validationError(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated',
            'errors' => null,
        ], 401);
    }

    private function forbidden(AuthorizationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage() ?: 'Forbidden',
            'errors' => null,
        ], 403);
    }

    private function entityNotFound(EntityNotFoundException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => ['code' => $e->getErrorCode()],
        ], 404);
    }

    private function modelNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Resource not found',
            'errors' => null,
        ], 404);
    }

    private function domainError(DomainException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => ['code' => $e->getErrorCode()],
        ], 400);
    }

    private function httpError(HttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage() ?: 'HTTP Error',
            'errors' => null,
        ], $e->getStatusCode());
    }

    private function serverError(Throwable $e): JsonResponse
    {
        report($e);

        $message = app()->isProduction()
            ? 'Internal server error'
            : $e->getMessage();

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 500);
    }
}
