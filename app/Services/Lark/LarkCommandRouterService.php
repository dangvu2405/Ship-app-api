<?php

declare(strict_types=1);

namespace App\Services\Lark;

use App\Models\Driver;
use App\Models\Payroll;
use App\Models\Trip;
use App\Models\User;

class LarkCommandRouterService
{
    public function handle(User $user, string $commandText): string
    {
        $commandText = trim($commandText);
        if ($commandText === '') {
            return 'Empty command. Try /trip {id}, /status {busy|available|off}, /payroll {MM-YYYY}';
        }

        $parts = preg_split('/\s+/', $commandText) ?: [];
        $command = strtolower((string) ($parts[0] ?? ''));

        return match ($command) {
            '/trip' => $this->handleTripCommand($user, $parts),
            '/status' => $this->handleStatusCommand($user, $parts),
            '/payroll' => $this->handlePayrollCommand($user, $parts),
            default => 'Unknown command. Supported: /trip, /status, /payroll',
        };
    }

    private function handleTripCommand(User $user, array $parts): string
    {
        if (! $user->hasRole('admin') && ! $user->hasRole('coordinator') && ! $user->hasRole('hr') && ! $user->hasRole('driver')) {
            return 'Forbidden: you do not have permission to view trip.';
        }

        $tripId = (int) ($parts[1] ?? 0);
        if ($tripId <= 0) {
            return 'Usage: /trip {id}';
        }

        $trip = Trip::with(['customer', 'driver', 'vehicle'])->find($tripId);
        if (! $trip) {
            return 'Trip not found.';
        }

        return sprintf(
            'Trip #%d (%s)\n%s -> %s\nStatus: %s\nDriver ID: %d\nVehicle ID: %d',
            $trip->id,
            (string) $trip->code,
            (string) $trip->start_point,
            (string) $trip->end_point,
            (string) $trip->status,
            (int) $trip->driver_id,
            (int) $trip->vehicle_id,
        );
    }

    private function handleStatusCommand(User $user, array $parts): string
    {
        if (! $user->hasRole('driver') && ! $user->hasRole('admin')) {
            return 'Forbidden: only driver/admin can update driver status.';
        }

        $status = strtolower((string) ($parts[1] ?? ''));
        $map = ['busy' => 'busy', 'available' => 'available', 'off' => 'unavailable'];
        if (! isset($map[$status])) {
            return 'Usage: /status {busy|available|off}';
        }

        $employeeId = (int) $user->employee_id;
        $driver = Driver::where('employee_id', $employeeId)->first();
        if (! $driver) {
            return 'Driver profile not found for current user.';
        }

        $driver->update(['available_status' => $map[$status]]);

        return sprintf('Driver status updated to: %s', $status);
    }

    private function handlePayrollCommand(User $user, array $parts): string
    {
        if ($user->hasRole('driver')) {
            return 'Forbidden: driver cannot access payroll command.';
        }

        if (! $user->hasRole('admin') && ! $user->hasRole('hr') && ! $user->hasRole('accountant')) {
            return 'Forbidden: you do not have permission to view payroll.';
        }

        $period = (string) ($parts[1] ?? '');
        if (! preg_match('/^(0[1-9]|1[0-2])-(\d{4})$/', $period, $matches)) {
            return 'Usage: /payroll {MM-YYYY}';
        }

        $month = (int) $matches[1];
        $year = (int) $matches[2];
        $payroll = Payroll::where('month', $month)->where('year', $year)->first();

        if (! $payroll) {
            return sprintf('No payroll found for %02d-%d.', $month, $year);
        }

        return sprintf('Payroll #%d for %02d-%d\nStatus: %s', $payroll->id, $month, $year, (string) $payroll->status);
    }
}
