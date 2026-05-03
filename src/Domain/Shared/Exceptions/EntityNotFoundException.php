<?php

declare(strict_types=1);

namespace Src\Domain\Shared\Exceptions;

class EntityNotFoundException extends \Src\Domain\Shared\Exceptions\DomainException
{
    public function __construct(string $message = 'Entity not found', string $errorCode = 'ENTITY_NOT_FOUND', ?int $code = null)
    {
        parent::__construct($message, $errorCode, $code ?? 0);
    }
}
