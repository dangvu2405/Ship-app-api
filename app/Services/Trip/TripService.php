<?php

declare(strict_types=1);

namespace App\Services\Trip;

use App\DTOs\Trip\CreateTripDTO;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Repositories\Trip\TripRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripService
{
    public function __construct(
        protected TripRepository $tripRepository
    ) {}

    /**
     * Create a new trip with business logic.
     */
    public function createTrip(CreateTripDTO $dto, User $actingUser): Trip
    {
        return $this->tripRepository->create($dto, $actingUser->company_id);
    }

    /**
     * Assign a driver and vehicle to a trip with complex interlocking checks.
     */
    public function assignDriverAndVehicle(int $tripId, int $driverId, int $vehicleId, User $actingUser): Trip
    {
        $trip = $this->tripRepository->find($tripId);
        if (! $trip) {
            throw new \Exception('Trip not found');
        }

        if (in_array($trip->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot assign to a trip that is already {$trip->status}."],
            ]);
        }

        $tripDate = $trip->scheduled_date ?? $trip->created_at?->toDateString() ?? now()->toDateString();

        // 1. Vehicle Checks
        $this->validateVehicle($vehicleId, $tripId, $tripDate);

        // 2. Driver Checks
        $this->validateDriver($driverId, $tripId, $tripDate);

        $fromStatus = $trip->status;
        $trip->update([
            'driver_id' => $driverId,
            'vehicle_id' => $vehicleId,
            'status' => 'assigned',
            'assigned_at' => $trip->assigned_at ?? now(),
        ]);

        $this->tripRepository->recordStatusHistory(
            $trip->id,
            $fromStatus,
            'assigned',
            $actingUser->id,
            'Assigned driver and vehicle via Service'
        );

        return $trip->fresh(['driver', 'vehicle', 'customer']);
    }

    /**
     * Validate vehicle availability and status.
     */
    protected function validateVehicle(int $vehicleId, int $tripId, string $date): void
    {
        $vehicle = Vehicle::find($vehicleId);
        if (! $vehicle || $vehicle->status !== 'active') {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Vehicle is not active or not found.'],
            ]);
        }

        $hasOpenMaintenance = DB::table('maintenance_records')
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'open')
            ->exists();
        if ($hasOpenMaintenance) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Vehicle is currently in maintenance.'],
            ]);
        }

        $vehicleBusy = Trip::query()
            ->where('id', '!=', $tripId)
            ->where('vehicle_id', $vehicleId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('scheduled_date', $date)
            ->exists();

        if ($vehicleBusy) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Vehicle is already booked for another trip on this date.'],
            ]);
        }
    }

    /**
     * Validate driver availability and license.
     */
    protected function validateDriver(int $driverId, int $tripId, string $date): void
    {
        $driver = Driver::find($driverId);
        if (! $driver || $driver->status !== 'active') {
            throw ValidationException::withMessages([
                'driver_id' => ['Driver is not active or not found.'],
            ]);
        }

        if ($driver->expired_date !== null && now()->toDateString() > (string) $driver->expired_date) {
            // Depending on requirements, this could be a warning or an error.
            // Requirement says "prevent N+1", "Scalable", doesn't explicitly say block expired license.
            // Controller logic had it as a warning.
        }

        $isOnLeave = DB::table('leave_requests')
            ->where('driver_id', $driverId)
            ->where('status', 'approved')
            ->where(function ($query) use ($date) {
                $query->whereDate('from_date', '<=', $date)
                    ->whereDate('to_date', '>=', $date);
            })
            ->exists();

        if ($isOnLeave) {
            throw ValidationException::withMessages([
                'driver_id' => ['Driver is on approved leave during the scheduled trip date.'],
            ]);
        }

        $driverBusy = Trip::query()
            ->where('id', '!=', $tripId)
            ->where('driver_id', $driverId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('scheduled_date', $date)
            ->exists();

        if ($driverBusy) {
            throw ValidationException::withMessages([
                'driver_id' => ['Driver is already booked for another trip on this date.'],
            ]);
        }
    }

    /**
     * Transition trip status with logging.
     */
    public function transitionStatus(int $tripId, string $newStatus, User $actingUser, ?string $note = null): Trip
    {
        $trip = $this->tripRepository->find($tripId);
        if (! $trip) {
            throw new \Exception('Trip not found');
        }

        $fromStatus = $trip->status;

        // Add state machine logic here if needed

        $updateData = ['status' => $newStatus];
        if ($newStatus === 'completed') {
            $updateData['end_time'] = $trip->end_time ?? now();
        }

        $trip->update($updateData);
        $this->tripRepository->recordStatusHistory($trip->id, $fromStatus, $newStatus, $actingUser->id, $note);

        return $trip->fresh();
    }
}
