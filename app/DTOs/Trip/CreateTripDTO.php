<?php

declare(strict_types=1);

namespace App\DTOs\Trip;

/**
 * Data Transfer Object for creating a Trip.
 */
readonly class CreateTripDTO
{
    /**
     * @param CreateTripStopDTO[] $stops
     * @param CreateTripSurchargeDTO[] $surcharges
     */
    public function __construct(
        public int $customer_id,
        public string $received_date,
        public string $scheduled_date,
        public float $base_price,
        public ?string $contact_name = null,
        public ?string $contact_phone = null,
        public ?int $cargo_type_id = null,
        public ?string $cargo_description = null,
        public ?float $cargo_quantity = null,
        public ?string $cargo_unit = null,
        public ?float $cargo_weight_ton = null,
        public ?string $cargo_notes = null,
        public ?string $payment_method = null,
        public ?string $internal_notes = null,
        public array $stops = [],
        public array $surcharges = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            customer_id: (int) $data['customer_id'],
            received_date: $data['received_date'],
            scheduled_date: $data['scheduled_date'],
            base_price: (float) $data['base_price'],
            contact_name: $data['contact_name'] ?? null,
            contact_phone: $data['contact_phone'] ?? null,
            cargo_type_id: isset($data['cargo_type_id']) ? (int) $data['cargo_type_id'] : null,
            cargo_description: $data['cargo_description'] ?? null,
            cargo_quantity: isset($data['cargo_quantity']) ? (float) $data['cargo_quantity'] : null,
            cargo_unit: $data['cargo_unit'] ?? null,
            cargo_weight_ton: isset($data['cargo_weight_ton']) ? (float) $data['cargo_weight_ton'] : null,
            cargo_notes: $data['cargo_notes'] ?? null,
            payment_method: $data['payment_method'] ?? null,
            internal_notes: $data['internal_notes'] ?? null,
            stops: array_map(fn(array $stop) => CreateTripStopDTO::fromArray($stop), $data['stops'] ?? []),
            surcharges: array_map(fn(array $surcharge) => CreateTripSurchargeDTO::fromArray($surcharge), $data['surcharges'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'customer_id' => $this->customer_id,
            'received_date' => $this->received_date,
            'scheduled_date' => $this->scheduled_date,
            'base_price' => $this->base_price,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'cargo_type_id' => $this->cargo_type_id,
            'cargo_description' => $this->cargo_description,
            'cargo_quantity' => $this->cargo_quantity,
            'cargo_unit' => $this->cargo_unit,
            'cargo_weight_ton' => $this->cargo_weight_ton,
            'cargo_notes' => $this->cargo_notes,
            'payment_method' => $this->payment_method,
            'internal_notes' => $this->internal_notes,
        ];
    }
}
