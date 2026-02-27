<?php

declare(strict_types=1);

namespace Src\Domain\Employee\ValueObjects;

use InvalidArgumentException;

final readonly class EmployeeType
{
    private const OFFICE = 'office';
    private const DRIVER = 'driver';

    private const VALID_TYPES = [
        self::OFFICE,
        self::DRIVER,
    ];

    private function __construct(
        private string $value
    ) {}

    public static function from(string $type): self
    {
        $type = strtolower(trim($type));

        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid employee type: {$type}. Valid types: " . implode(', ', self::VALID_TYPES)
            );
        }

        return new self($type);
    }

    public static function office(): self
    {
        return new self(self::OFFICE);
    }

    public static function driver(): self
    {
        return new self(self::DRIVER);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isOffice(): bool
    {
        return $this->value === self::OFFICE;
    }

    public function isDriver(): bool
    {
        return $this->value === self::DRIVER;
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
