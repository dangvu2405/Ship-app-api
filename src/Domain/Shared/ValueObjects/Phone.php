<?php

declare(strict_types=1);

namespace Src\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Phone
{
    private function __construct(
        private string $value
    ) {}

    public static function fromString(string $phone): self
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
            throw new InvalidArgumentException(
                "Invalid phone format: {$phone}. Must start with 0 and contain 10-11 digits"
            );
        }

        return new self($phone);
    }

    public static function fromStringOrNull(?string $phone): ?self
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        return self::fromString($phone);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        // Format: 0XXX XXX XXXX
        if (strlen($this->value) === 10) {
            return substr($this->value, 0, 4) . ' ' .
                   substr($this->value, 4, 3) . ' ' .
                   substr($this->value, 7, 3);
        }

        // Format: 0XXX XXX XXXX for 11 digits
        return substr($this->value, 0, 4) . ' ' .
               substr($this->value, 4, 3) . ' ' .
               substr($this->value, 7, 4);
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
