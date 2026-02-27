<?php

declare(strict_types=1);

namespace Src\Domain\Shared\Exceptions;

abstract class EntityNotFoundException extends DomainException
{
    public function __construct(
        string $message,
        string $errorCode = 'ENTITY_NOT_FOUND'
    ) {
        parent::__construct($message, $errorCode);
    }
}
