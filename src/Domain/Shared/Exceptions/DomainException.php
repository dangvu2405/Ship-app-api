<?php

declare(strict_types=1);

namespace Src\Domain\Shared\Exceptions;

use Exception;

class DomainException extends Exception
{
    protected string $errorCode = '';

    public function __construct(string $message = '', string $errorCode = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
