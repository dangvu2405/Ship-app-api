<?php

declare(strict_types=1);

namespace Src\Domain\Employee\ValueObjects;

use InvalidArgumentException;

final readonly class Gender
{
    private const MALE = 'male';
    private const FEMALE = 'female';
    private const OTHER = 'other';

    private const VALID_GENDERS = [
        self::MALE,
        self::FEMALE,
        self::OTHER,
    ];

    private function __construct(
        private string $value
    ) {}

    public static function from(string $gender): self
    {
        $gender = strtolower(trim($gender));

        if (!in_array($gender, self::VALID_GENDERS, true)) {
            throw new InvalidArgumentException(
                "Invalid gender: {$gender}. Valid genders: " . implode(', ', self::VALID_GENDERS)
            );
        }

        return new self($gender);
    }

    public static function fromStringOrNull(?string $gender): ?self
    {
        if ($gender === null || trim($gender) === '') {
            return null;
        }

        return self::from($gender);
    }

    public static function male(): self
    {
        return new self(self::MALE);
    }

    public static function female(): self
    {
        return new self(self::FEMALE);
    }

    public static function other(): self
    {
        return new self(self::OTHER);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isMale(): bool
    {
        return $this->value === self::MALE;
    }

    public function isFemale(): bool
    {
        return $this->value === self::FEMALE;
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
