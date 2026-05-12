<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReportService extends BaseService
{
    public function getReportData(string $type, ?int $userId = null): array
    {
        $companyId = $this->companyId();
        if ($type === 'notifications-unread' && $userId) {
            return [
                'unread_count' => DB::table('notifications')
                    ->where('notifiable_id', $userId)
                    ->whereNull('read_at')
                    ->count(),
            ];
        }

        if ($type === 'payroll-export') {
            return [
                'type' => 'payroll-export',
                'file_url' => 'https://example.test/exports/payroll_'.now()->format('Y_m').'.xlsx',
                'status' => 'completed',
                'generated_at' => now(),
            ];
        }

        return [
            'type' => $type,
            'summary' => [
                'trips' => Schema::hasTable('trips') ? DB::table('trips')->where('company_id', $companyId)->count() : 0,
                'vehicles' => Schema::hasTable('vehicles') ? DB::table('vehicles')->where('company_id', $companyId)->count() : 0,
                'drivers' => Schema::hasTable('drivers') ? DB::table('drivers')->where('company_id', $companyId)->count() : 0,
                'customers' => Schema::hasTable('customers') ? DB::table('customers')->where('company_id', $companyId)->count() : 0,
            ],
        ];
    }

    public function getDispatchData(string $date): array
    {
        $companyId = $this->companyId();
        $trips = DB::table('trips')->where('company_id', $companyId)->whereDate('scheduled_date', $date)->get();

        return [
            'date' => $date,
            'trips' => $trips,
            'unassigned_trips' => $trips->whereNull('vehicle_id')->values(),
            'daily_summary' => [
                'total_trips' => $trips->count(),
                'unassigned' => $trips->whereNull('vehicle_id')->count(),
            ],
        ];
    }
}
