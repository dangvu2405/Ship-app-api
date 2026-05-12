<?php

declare(strict_types=1);

namespace App\DTOs\Trip;

/**
 * Data Transfer Object for creating a Trip Stop.
 */
readonly class CreateTripStopDTO
{
    public function __construct(
        public string $stop_type,
        public int $sequence,
        public string $address,
        public ?int $location_id = null,
        public ?string $contact_name = null,
        public ?string $contact_phone = null,
        public ?string $scheduled_time = null,
        public ?string $notes = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            stop_type: $data['stop_type'],
            sequence: (int) $data['sequence'],
            address: $data['address'],
            location_id: isset($data['location_id']) ? (int) $data['location_id'] : null,
            contact_name: $data['contact_name'] ?? null,
            contact_phone: $data['contact_phone'] ?? null,
            scheduled_time: $data['scheduled_time'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'stop_type' => $this->stop_type,
            'sequence' => $this->sequence,
            'address' => $this->address,
            'location_id' => $this->location_id,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'scheduled_time' => $this->scheduled_time,
            'notes' => $this->notes,
        ];
    }
}
