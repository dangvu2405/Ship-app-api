<?php

declare(strict_types=1);

namespace Src\Domain\Shared\Exceptions;

class ValidationException extends DomainException
{
    /**
     * @param array<string, array<string>> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        string $errorCode = 'VALIDATION_ERROR'
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
