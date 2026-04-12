<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lark\LarkBaseSyncService;
use Illuminate\Console\Command;

class LarkBaseReverseSyncCommand extends Command
{
    protected $signature = 'lark:reverse-sync
        {--entity=all : all|employees|trips}
        {--limit=200 : Max records fetched per entity}';

    protected $description = 'Sync records from Lark Base back to local database (update-only mode).';

    public function __construct(private readonly LarkBaseSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->syncService->reverseSyncAllowed()) {
            $this->warn('Lark Base reverse sync is disabled. Set LARK_BASE_ENABLE_REVERSE_SYNC=true to enable.');

            return self::FAILURE;
        }

        $entity = strtolower((string) $this->option('entity'));
        if (! in_array($entity, ['all', 'employees', 'trips'], true)) {
            $this->error('Invalid --entity value. Use one of: all, employees, trips.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $totalFailed = 0;

        if ($entity === 'all' || $entity === 'employees') {
            $employeeStats = $this->syncService->reverseSyncEmployees($limit);
            $this->printStats('employees', $employeeStats);
            $totalFailed += $employeeStats['failed'];
        }

        if ($entity === 'all' || $entity === 'trips') {
            $tripStats = $this->syncService->reverseSyncTrips($limit);
            $this->printStats('trips', $tripStats);
            $totalFailed += $tripStats['failed'];
        }

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{fetched: int, updated: int, skipped: int, failed: int}  $stats
     */
    private function printStats(string $entity, array $stats): void
    {
        $this->line(sprintf(
            '%s -> fetched: %d, updated: %d, skipped: %d, failed: %d',
            $entity,
            $stats['fetched'],
            $stats['updated'],
            $stats['skipped'],
            $stats['failed']
        ));
    }
}
