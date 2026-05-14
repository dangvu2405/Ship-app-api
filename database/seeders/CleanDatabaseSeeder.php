<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CleanDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'ABC Transport']);

        $user = User::factory()->create([
            'full_name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->companies()->attach($company->id, ['is_default' => true]);

        Customer::factory()->count(10)->create(['company_id' => $company->id]);
        Driver::factory()->count(5)->create([
            'company_id' => $company->id,
            'status' => 'active',
            'available_status' => 'available',
        ]);
        Vehicle::factory()->count(5)->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $this->seedDemoTrips((int) $company->id, (int) $user->id);
    }

    private function seedDemoTrips(int $companyId, int $dispatcherId): void
    {
        if (! Schema::hasTable('trips')) {
            return;
        }

        $customerIds = Customer::query()
            ->where('company_id', $companyId)
            ->limit(3)
            ->pluck('id')
            ->values();
        $driverIds = Driver::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->limit(3)
            ->pluck('id')
            ->values();
        $vehicleIds = Vehicle::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->limit(3)
            ->pluck('id')
            ->values();

        if ($customerIds->isEmpty() || $driverIds->isEmpty() || $vehicleIds->isEmpty()) {
            return;
        }

        $now = now();
        $trips = [
            [
                'code' => 'DH-DEMO-0001',
                'customer_id' => $customerIds[0],
                'driver_id' => $driverIds[0],
                'vehicle_id' => $vehicleIds[0],
                'start_point' => 'Kho Long Bien, Ha Noi',
                'end_point' => 'KCN Dinh Vu, Hai Phong',
                'scheduled_date' => $now->copy()->addDay()->toDateString(),
                'scheduled_time_from' => '08:00:00',
                'scheduled_time_to' => '17:00:00',
                'distance_km' => 120,
                'base_price' => 4500000,
                'surcharge_amount' => 250000,
                'total_revenue' => 4750000,
                'status' => 'assigned',
            ],
            [
                'code' => 'DH-DEMO-0002',
                'customer_id' => $customerIds[1] ?? $customerIds[0],
                'driver_id' => $driverIds[1] ?? $driverIds[0],
                'vehicle_id' => $vehicleIds[1] ?? $vehicleIds[0],
                'start_point' => 'Cang Cat Lai, TP HCM',
                'end_point' => 'KCN Song Than, Binh Duong',
                'scheduled_date' => $now->copy()->toDateString(),
                'scheduled_time_from' => '09:00:00',
                'scheduled_time_to' => '15:00:00',
                'distance_km' => 38,
                'base_price' => 1800000,
                'surcharge_amount' => 0,
                'total_revenue' => 1800000,
                'status' => 'in_progress',
            ],
            [
                'code' => 'DH-DEMO-0003',
                'customer_id' => $customerIds[2] ?? $customerIds[0],
                'driver_id' => null,
                'vehicle_id' => null,
                'start_point' => 'Kho Da Nang',
                'end_point' => 'KCN Phu Bai, Hue',
                'scheduled_date' => $now->copy()->addDays(2)->toDateString(),
                'scheduled_time_from' => '07:30:00',
                'scheduled_time_to' => '14:30:00',
                'distance_km' => 95,
                'base_price' => 3200000,
                'surcharge_amount' => 0,
                'total_revenue' => 3200000,
                'status' => 'pending',
            ],
        ];

        foreach ($trips as $trip) {
            DB::table('trips')->updateOrInsert(
                ['code' => $trip['code']],
                array_filter([
                    ...$trip,
                    'company_id' => $companyId,
                    'dispatcher_id' => $dispatcherId,
                    'assigned_at' => $trip['driver_id'] !== null ? $now : null,
                    'price' => $trip['base_price'],
                    'payment_status' => 'unpaid',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], static fn ($value) => $value !== null)
            );

            if (Schema::hasTable('trip_stops')) {
                $currentTripId = (int) DB::table('trips')->where('code', $trip['code'])->value('id');
                $this->seedDemoTripStops($currentTripId, $companyId, $trip['start_point'], $trip['end_point']);
            }
        }
    }

    private function seedDemoTripStops(int $tripId, int $companyId, string $pickupAddress, string $deliveryAddress): void
    {
        $now = now();

        foreach ([
            ['stop_type' => 'pickup', 'sequence' => 1, 'address' => $pickupAddress],
            ['stop_type' => 'delivery', 'sequence' => 2, 'address' => $deliveryAddress],
        ] as $stop) {
            DB::table('trip_stops')->updateOrInsert(
                [
                    'trip_id' => $tripId,
                    'stop_type' => $stop['stop_type'],
                    'sequence' => $stop['sequence'],
                ],
                [
                    'company_id' => $companyId,
                    'address' => $stop['address'],
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
