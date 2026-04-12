<?php

declare(strict_types=1);

namespace App\Events\Lark;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollApprovedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $payrollId,
        public readonly string $period,
    ) {}
}
