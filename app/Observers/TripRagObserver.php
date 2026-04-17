<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\IndexTripJob;
use App\Models\RagIndex;
use App\Models\Trip;

/**
 * Automatically keeps rag_index in sync when Trip records change.
 */
final class TripRagObserver
{
    public function saved(Trip $trip): void
    {
        IndexTripJob::dispatch($trip)->onQueue('indexing');
    }

    public function deleted(Trip $trip): void
    {
        RagIndex::withoutGlobalScopes()
            ->where('source_table', 'trips')
            ->where('source_id', $trip->id)
            ->delete();
    }
}
