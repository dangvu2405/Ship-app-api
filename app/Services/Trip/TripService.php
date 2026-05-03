<?php

declare(strict_types=1);

namespace App\Services\Trip;

use Illuminate\Support\Facades\DB;

class TripService
{
    public function recordStatusHistory(int $tripId, ?string $from, string $to, ?int $userId, ?string $note = null): void
    {
        DB::table('trip_status_histories')->insert([
            'trip_id' => $tripId,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
