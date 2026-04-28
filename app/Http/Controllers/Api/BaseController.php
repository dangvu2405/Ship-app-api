<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class BaseController extends Controller
{
    /**
     * Success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'api.success', int $code = 200): JsonResponse
    {
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
     * @param string $message
     * @param int $code
     * @param mixed $errors
     * @return JsonResponse
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
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'api.resource_not_found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Unauthorized response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'api.unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Forbidden response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'api.forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Validation error response
     *
     * @param mixed $errors
     * @param string $message
     * @return JsonResponse
     */
    protected function validationErrorResponse($errors, string $message = 'api.validation_failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Handle exceptions and return formatted error response
     *
     * @param Throwable $e
     * @param string|null $customMessage
     * @return JsonResponse
     */
    protected function handleException(Throwable $e, ?string $customMessage = null): JsonResponse
    {
        $level = match(true) {
            $e instanceof \Illuminate\Database\QueryException,
            $e instanceof \PDOException => 'critical',
            $e instanceof \Illuminate\Auth\AuthenticationException => 'info',
            $e instanceof \Illuminate\Auth\Access\AuthorizationException,
            $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException,
            $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException,
            $e instanceof ApiException => 'warning',
            $e instanceof \Illuminate\Validation\ValidationException => 'notice',
            default => 'error',
        };

        Log::log($level, 'API Exception', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ]);

        if ($e instanceof ApiException) {
            return $this->errorResponse(
                $customMessage ?: $e->getMessage(),
                $e->getStatusCode(),
                $e->getErrors()
            );
        }

        if ($e instanceof \Illuminate\Database\QueryException) {
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
        if (\Illuminate\Support\Facades\Lang::has($message)) {
            return __($message);
        }

        return $message;
    }
}
