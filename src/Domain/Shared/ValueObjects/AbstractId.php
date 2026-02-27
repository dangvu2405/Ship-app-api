<?php

declare(strict_types=1);

namespace Src\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

abstract readonly class AbstractId
{
    final private function __construct(
        private string $value
    ) {}

    public static function fromString(string $value): static
    {
        if (empty($value)) {
            throw new InvalidArgumentException('ID cannot be empty');
        }

        return new static($value);
    }

    public static function generate(): static
    {
        return new static(Uuid::uuid4()->toString());
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
