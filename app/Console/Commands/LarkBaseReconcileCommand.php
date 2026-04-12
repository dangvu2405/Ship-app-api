<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Lark\SyncLarkBaseRecordJob;
use App\Models\Employee;
use App\Models\Trip;
use Illuminate\Console\Command;

class LarkBaseReconcileCommand extends Command
{
    protected $signature = 'lark:sync-reconcile {--entity=all : all|employees|trips}';

    protected $description = 'Reconcile local records to Lark Base using queued upserts';

    public function handle(): int
    {
        $entity = (string) $this->option('entity');

        if ($entity === 'all' || $entity === 'employees') {
            Employee::query()->select('id')->orderBy('id')->lazy()->each(function (Employee $employee): void {
                SyncLarkBaseRecordJob::dispatch('employee', (int) $employee->id);
            });
            $this->info('Queued employee sync jobs.');
        }

        if ($entity === 'all' || $entity === 'trips') {
            Trip::query()->select('id')->orderBy('id')->lazy()->each(function (Trip $trip): void {
                SyncLarkBaseRecordJob::dispatch('trip', (int) $trip->id);
            });
            $this->info('Queued trip sync jobs.');
        }

        return self::SUCCESS;
    }
}
