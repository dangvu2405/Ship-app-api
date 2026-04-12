<?php

declare(strict_types=1);

namespace App\Events\Lark;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverAssignedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $assignmentId,
        public readonly int $driverId,
        public readonly int $vehicleId,
    ) {}
}
