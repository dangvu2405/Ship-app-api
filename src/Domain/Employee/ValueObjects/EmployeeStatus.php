<?php

declare(strict_types=1);

namespace Src\Domain\Employee\ValueObjects;

use InvalidArgumentException;

final readonly class EmployeeStatus
{
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';
    private const RESIGNED = 'resigned';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
        self::RESIGNED,
    ];

    private function __construct(
        private string $value
    ) {}

    public static function from(string $status): self
    {
        $status = strtolower(trim($status));

        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid employee status: {$status}. Valid statuses: " . implode(', ', self::VALID_STATUSES)
            );
        }

        return new self($status);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function resigned(): self
    {
        return new self(self::RESIGNED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    public function isResigned(): bool
    {
        return $this->value === self::RESIGNED;
    }

    public function canBeDeleted(): bool
    {
        return !$this->isActive();
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
