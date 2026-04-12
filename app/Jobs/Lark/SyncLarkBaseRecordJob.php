<?php

declare(strict_types=1);

namespace App\Jobs\Lark;

use App\Models\Employee;
use App\Models\Trip;
use App\Services\Lark\LarkBaseSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncLarkBaseRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
    ) {
        $this->onQueue('lark-sync');
    }

    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(LarkBaseSyncService $syncService): void
    {
        if ($this->entityType === 'employee') {
            $employee = Employee::find($this->entityId);
            if ($employee) {
                $syncService->syncEmployee($employee);
            }

            return;
        }

        if ($this->entityType === 'trip') {
            $trip = Trip::find($this->entityId);
            if ($trip) {
                $syncService->syncTrip($trip);
            }
        }
    }
}
