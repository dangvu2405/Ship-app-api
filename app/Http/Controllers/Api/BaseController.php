<?php

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
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
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
    protected function errorResponse(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
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
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Unauthorized response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Forbidden response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
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
    protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
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
        // Log the exception
        Log::error('API Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // If it's an ApiException, use its status code and message
        if ($e instanceof ApiException) {
            return $this->errorResponse(
                $customMessage ?: $e->getMessage(),
                $e->getStatusCode(),
                $e->getErrors()
            );
        }

        // Database errors
        if ($e instanceof \Illuminate\Database\QueryException) {
            $message = $customMessage ?: 'Database error occurred';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }
            return $this->errorResponse($message, 500);
        }

        // General exception
        $message = $customMessage ?: ($e->getMessage() ?: 'An error occurred');
        
        // Don't expose internal errors in production
        if (!config('app.debug') && !($e instanceof Exception && $e->getCode() < 500)) {
            $message = 'An error occurred. Please try again later.';
        }

        return $this->errorResponse($message, 500);
    }
}
