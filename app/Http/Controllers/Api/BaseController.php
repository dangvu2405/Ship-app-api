<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class BaseController extends Controller
{
    use AuthorizesRequests;

    /**
     * Success response
     *
     * @param  mixed  $data
     */
    protected function successResponse($data = null, string $message = 'api.success', int $code = 200): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            $data = [
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                ],
            ];
        }

        $response = [
            'success' => true,
            'message' => $this->translateMessage($message),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Error response
     *
     * @param  mixed  $errors
     */
    protected function errorResponse(string $message = 'api.error', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->translateMessage($message),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(string $message = 'api.resource_not_found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'api.unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Use when Sanctum may be bypassed (tests) or a client omits credentials — avoids 500 from null $request->user().
     *
     * @return JsonResponse|null JSON 401 when guest, null when authenticated
     */
    protected function unauthorizedIfGuest(Request $request): ?JsonResponse
    {
        if ($request->user() === null) {
            return $this->unauthorizedResponse('api.unauthenticated');
        }

        return null;
    }

    /**
     * Forbidden response
     */
    protected function forbiddenResponse(string $message = 'api.forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Validation error response
     *
     * @param  mixed  $errors
     */
    protected function validationErrorResponse($errors, string $message = 'api.validation_failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Handle exceptions and return formatted error response
     */
    protected function handleException(Throwable $e, ?string $customMessage = null): JsonResponse
    {
        $level = match (true) {
            $e instanceof QueryException,
            $e instanceof \PDOException => 'critical',
            $e instanceof AuthenticationException => 'info',
            $e instanceof AuthorizationException,
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException,
            $e instanceof ApiException => 'warning',
            $e instanceof ValidationException => 'notice',
            default => 'error',
        };

        Log::log($level, 'API Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($e instanceof ApiException) {
            return $this->errorResponse(
                $customMessage ?: $e->getMessage(),
                $e->getStatusCode(),
                $e->getErrors()
            );
        }

        if ($e instanceof QueryException) {
            // Never expose query/table details to the client
            $message = config('app.debug')
                ? ($customMessage ?: __('api.database_error_debug', ['error' => $e->getMessage()]))
                : ($customMessage ?: __('api.database_error'));

            return $this->errorResponse($message, 500);
        }

        // In production ALWAYS return a generic message — never the raw exception text
        $message = config('app.debug')
            ? ($customMessage ?: $e->getMessage() ?: __('api.generic_error'))
            : __('api.internal_server_error');

        return $this->errorResponse($message, 500);
    }

    private function translateMessage(string $message): string
    {
        if (Lang::has($message)) {
            return __($message);
        }

        return $message;
    }
}
