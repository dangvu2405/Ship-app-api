<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SendExpiryAlerts extends Command
{
    protected $signature = 'alerts:expiry {--days=7}';
    protected $description = 'Check and create notifications for expiring documents, maintenance, contracts (Spec 2.8)';

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        $alertDate = now()->addDays($daysAhead)->toDateString();
        $this->info("Checking for items expiring within {$daysAhead} days (by {$alertDate})...");

        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            // Check vehicle documents expiry
            $this->checkVehicleDocuments($companyId, $alertDate);

            // Check driver documents expiry
            $this->checkDriverDocuments($companyId, $alertDate);

            // Check maintenance schedules
            $this->checkMaintenanceSchedules($companyId, $alertDate);

            // Check customer contracts
            $this->checkCustomerContracts($companyId, $alertDate);

            // Check outstanding debt
            $this->checkOutstandingDebt($companyId);
        }

        $this->info('✓ Expiry alerts processed successfully');

        return self::SUCCESS;
    }

    private function checkVehicleDocuments(int $companyId, string $alertDate): void
    {
        $docs = DB::table('vehicle_documents')
            ->where('company_id', $companyId)
            ->whereDate('expiry_date', '<=', $alertDate)
            ->whereDate('expiry_date', '>', now()->toDateString())
            ->get();

        foreach ($docs as $doc) {
            $vehicle = DB::table('vehicles')->where('id', $doc->vehicle_id)->first();
            $this->createNotification($companyId, null, "warning", "Vehicle '{$vehicle->license_plate}' document '{$doc->document_type}' expires on {$doc->expiry_date}");
        }
    }

    private function checkDriverDocuments(int $companyId, string $alertDate): void
    {
        $docs = DB::table('driver_documents')
            ->where('company_id', $companyId)
            ->whereDate('expiry_date', '<=', $alertDate)
            ->whereDate('expiry_date', '>', now()->toDateString())
            ->get();

        foreach ($docs as $doc) {
            $driver = DB::table('drivers')->where('id', $doc->driver_id)->first();
            $this->createNotification($companyId, null, "warning", "Driver '{$driver->name}' {$doc->document_type} expires on {$doc->expiry_date}");
        }
    }

    private function checkMaintenanceSchedules(int $companyId, string $alertDate): void
    {
        $schedules = DB::table('maintenance_schedules')
            ->where('company_id', $companyId)
            ->where('status', 'scheduled')
            ->whereDate('due_date', '<=', $alertDate)
            ->whereDate('due_date', '>', now()->toDateString())
            ->get();

        foreach ($schedules as $schedule) {
            $vehicle = DB::table('vehicles')->where('id', $schedule->vehicle_id)->first();
            $this->createNotification($companyId, null, "warning", "Vehicle '{$vehicle->license_plate}' maintenance due on {$schedule->due_date}");
        }
    }

    private function checkCustomerContracts(int $companyId, string $alertDate): void
    {
        // Assuming contracts have expiry_date field (based on spec structure)
        $contracts = DB::table('customers')
            ->where('company_id', $companyId)
            ->whereNotNull('contract_expiry_date')
            ->whereDate('contract_expiry_date', '<=', $alertDate)
            ->whereDate('contract_expiry_date', '>', now()->toDateString())
            ->get();

        foreach ($contracts as $contract) {
            $this->createNotification($companyId, null, "info", "Customer '{$contract->name}' contract expires on {$contract->contract_expiry_date}");
        }
    }

    private function checkOutstandingDebt(int $companyId): void
    {
        $overdueDebt = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->sum('total_amount');

        if ($overdueDebt > 0) {
            $this->createNotification($companyId, null, "error", "Outstanding debt of " . number_format($overdueDebt) . " is overdue");
        }
    }

    private function createNotification(int $companyId, ?int $userId, string $type, string $message): void
    {
        DB::table('notifications')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'type' => $type,
            'title' => ucfirst($type) . ' Alert',
            'message' => $message,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
