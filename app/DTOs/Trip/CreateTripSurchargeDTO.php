<?php

declare(strict_types=1);

namespace App\DTOs\Trip;

/**
 * Data Transfer Object for creating a Trip Surcharge.
 */
readonly class CreateTripSurchargeDTO
{
    public function __construct(
        public string $name,
        public float $amount,
        public ?string $notes = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            amount: (float) $data['amount'],
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'amount' => $this->amount,
            'notes' => $this->notes,
        ];
    }
}
