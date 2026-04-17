<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\IndexDriverJob;
use App\Models\Driver;
use App\Models\RagIndex;

/**
 * Automatically keeps rag_index in sync when Driver records change.
 */
final class DriverRagObserver
{
    public function saved(Driver $driver): void
    {
        IndexDriverJob::dispatch($driver)->onQueue('indexing');
    }

    public function deleted(Driver $driver): void
    {
        RagIndex::withoutGlobalScopes()
            ->where('source_table', 'drivers')
            ->where('source_id', $driver->id)
            ->delete();
    }
}
