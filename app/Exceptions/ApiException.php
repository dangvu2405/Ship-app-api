<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected $statusCode;

    protected $errors;

    public function __construct(string $message = '', int $statusCode = 400, $errors = null, ?Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function render($request)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            $response = [
                'success' => false,
                'message' => $this->getMessage(),
            ];

            if ($this->errors !== null) {
                $response['errors'] = $this->errors;
            }

            return response()->json($response, $this->statusCode);
        }

        return parent::render($request);
    }
}
