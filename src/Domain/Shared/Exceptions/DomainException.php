<?php

declare(strict_types=1);

namespace Src\Domain\Shared\Exceptions;

use Exception;
use Throwable;

abstract class DomainException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
