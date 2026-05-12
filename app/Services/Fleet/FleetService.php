<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FleetService extends BaseService
{
    public function releaseVehicleAssignment(string $vehicleId, string $date, ?string $reason): void
    {
        DB::table('vehicle_assignments')
            ->where('vehicle_id', $vehicleId)
            ->whereNull('to_date')
            ->where('company_id', $this->companyId())
            ->update([
                'to_date' => $date,
                'release_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function getAvailableResources(string $table, string $date): array
    {
        $query = $this->scopedQuery($table);
        if ($table === 'vehicles') {
            $busy = DB::table('trips')
                ->where('company_id', $this->companyId())
                ->whereDate('scheduled_date', $date)
                ->pluck('vehicle_id')
                ->filter()
                ->all();
            $query->where('status', 'active')->whereNotIn('id', $busy);
        }
        if ($table === 'drivers') {
            $busy = DB::table('trips')
                ->where('company_id', $this->companyId())
                ->whereDate('scheduled_date', $date)
                ->pluck('driver_id')
                ->filter()
                ->all();
            $query->where('status', 'active')->where('available_status', 'available')->whereNotIn('id', $busy);
        }

        return $query->limit(100)->get()->toArray();
    }

    public function beforeStore(string $table, array $payload): void
    {
        if ($table !== 'vehicle_assignments') {
            return;
        }

        foreach (['vehicle_id', 'driver_id'] as $field) {
            if (empty($payload[$field])) {
                continue;
            }

            DB::table('vehicle_assignments')
                ->where($field, $payload[$field])
                ->where('company_id', $payload['company_id'] ?? $this->companyId())
                ->whereNull('to_date')
                ->update([
                    'to_date' => now()->toDateString(),
                    'release_reason' => 'Auto-closed before new active assignment',
                    'updated_at' => now(),
                ]);
        }
    }
}
