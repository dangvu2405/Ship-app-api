<?php

declare(strict_types=1);

namespace App\Repositories\Trip;

use App\DTOs\Trip\CreateTripDTO;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

class TripRepository
{
    /**
     * Create a new Trip and its related stops and surcharges within a transaction.
     *
     * @param CreateTripDTO $dto
     * @param int $companyId
     * @return Trip
     */
    public function create(CreateTripDTO $dto, int $companyId): Trip
    {
        return DB::transaction(function () use ($dto, $companyId) {
            $tripData = $dto->toArray();
            $tripData['company_id'] = $companyId;
            $tripData['status'] = 'pending';

            // Calculate surcharge amount and total revenue
            $surchargeAmount = 0;
            foreach ($dto->surcharges as $surcharge) {
                $surchargeAmount += $surcharge->amount;
            }
            
            $tripData['surcharge_amount'] = $surchargeAmount;
            $tripData['total_revenue'] = $dto->base_price + $surchargeAmount;

            /** @var Trip $trip */
            $trip = Trip::create($tripData);

            // Create stops
            if (!empty($dto->stops)) {
                $stopsData = array_map(function ($stop) use ($companyId) {
                    $data = $stop->toArray();
                    $data['company_id'] = $companyId;
                    return $data;
                }, $dto->stops);
                
                $trip->stops()->createMany($stopsData);
            }

            // Create surcharges
            if (!empty($dto->surcharges)) {
                $surchargesData = array_map(function ($surcharge) use ($companyId) {
                    $data = $surcharge->toArray();
                    $data['company_id'] = $companyId;
                    return $data;
                }, $dto->surcharges);
                
                $trip->surcharges()->createMany($surchargesData);
            }

            // Return with eager loaded relations to prevent N+1 later
            return $trip->load(['customer', 'stops', 'surcharges', 'cargoType']);
        });
    }

    /**
     * Find a trip by ID with eager loading.
     */
    public function find(int|string $id): ?Trip
    {
        return Trip::with(['customer', 'driver', 'vehicle', 'stops', 'surcharges', 'cargoType'])->find($id);
    }

    /**
     * Update trip status history.
     */
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
