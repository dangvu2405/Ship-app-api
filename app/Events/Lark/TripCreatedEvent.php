<?php

declare(strict_types=1);

namespace App\Events\Lark;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCreatedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $tripId,
        public readonly string $tripCode,
    ) {}
}
