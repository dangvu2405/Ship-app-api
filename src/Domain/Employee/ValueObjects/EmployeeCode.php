<?php

declare(strict_types=1);

namespace Src\Domain\Employee\ValueObjects;

use InvalidArgumentException;

final readonly class EmployeeCode
{
    private function __construct(
        private string $value
    ) {}

    public static function fromString(string $code): self
    {
        $code = strtoupper(trim($code));

        if (empty($code)) {
            throw new InvalidArgumentException('Employee code cannot be empty');
        }

        if (strlen($code) > 50) {
            throw new InvalidArgumentException('Employee code cannot exceed 50 characters');
        }

        if (!preg_match('/^[A-Z0-9\-_]+$/', $code)) {
            throw new InvalidArgumentException(
                'Employee code can only contain letters, numbers, hyphens and underscores'
            );
        }

        return new self($code);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
