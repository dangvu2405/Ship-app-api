<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Trip;
use App\Support\TripStatusRules;
use Illuminate\Validation\ValidationException;

final class TripObserver
{
    public function updating(Trip $trip): void
    {
        if (! $trip->isDirty('status')) {
            return;
        }

        $from = (string) $trip->getOriginal('status');
        $to = (string) $trip->status;

        if (TripStatusRules::allows($from, $to)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => ['Chuyển trạng thái chuyến đi không hợp lệ.'],
        ]);
    }
}
