<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttendanceService
{
    /**
     * Record a driver's check-in.
     *
     * @return array<string, mixed>
     */
    public function checkIn(int $driverId, string $checkInTime, User $actor): array
    {
        $date = substr($checkInTime, 0, 10);

        $existing = DB::table('attendances')
            ->where('driver_id', $driverId)
            ->where('date', $date)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException("Driver #{$driverId} has already checked in on {$date}.");
        }

        $id = DB::table('attendances')->insertGetId([
            'driver_id'  => $driverId,
            'date'       => $date,
            'check_in'   => $checkInTime,
            'status'     => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->writeAuditLog($actor, 'attendance.check_in', 'attendances', $id, null, [
            'driver_id' => $driverId,
            'date'      => $date,
            'check_in'  => $checkInTime,
        ]);

        return (array) DB::table('attendances')->where('id', $id)->first();
    }

    /**
     * Record a driver's check-out and calculate work_hours.
     *
     * @return array<string, mixed>
     */
    public function checkOut(int $driverId, string $checkOutTime, User $actor): array
    {
        $date = substr($checkOutTime, 0, 10);

        $record = DB::table('attendances')
            ->where('driver_id', $driverId)
            ->where('date', $date)
            ->first();

        if (! $record) {
            throw new InvalidArgumentException("No check-in found for driver #{$driverId} on {$date}.");
        }

        if (! empty($record->check_out)) {
            throw new InvalidArgumentException("Driver #{$driverId} has already checked out on {$date}.");
        }

        $checkIn  = strtotime((string) $record->check_in);
        $checkOut = strtotime($checkOutTime);
        $workHours = round(($checkOut - $checkIn) / 3600, 2);
        $standardHours = 8.0;
        $overtimeHours = max(0, $workHours - $standardHours);

        DB::table('attendances')
            ->where('id', $record->id)
            ->update([
                'check_out'      => $checkOutTime,
                'work_hours'     => $workHours,
                'overtime_hours' => $overtimeHours,
                'updated_at'     => now(),
            ]);

        $this->writeAuditLog($actor, 'attendance.check_out', 'attendances', (int) $record->id, null, [
            'driver_id'      => $driverId,
            'date'           => $date,
            'check_out'      => $checkOutTime,
            'work_hours'     => $workHours,
            'overtime_hours' => $overtimeHours,
        ]);

        return (array) DB::table('attendances')->where('id', $record->id)->first();
    }

    /**
     * Adjust an existing attendance record (admin override).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function adjust(int $attendanceId, array $data, User $actor): array
    {
        $record = DB::table('attendances')->where('id', $attendanceId)->first();

        if (! $record) {
            throw new InvalidArgumentException("Attendance record #{$attendanceId} not found.");
        }

        $before = (array) $record;

        DB::table('attendances')
            ->where('id', $attendanceId)
            ->update([
                ...$data,
                'updated_at' => now(),
            ]);

        $this->writeAuditLog($actor, 'attendance.adjust', 'attendances', $attendanceId, $before, $data);

        return (array) DB::table('attendances')->where('id', $attendanceId)->first();
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function writeAuditLog(
        User $actor,
        string $action,
        string $table,
        int $recordId,
        ?array $before,
        array $after,
    ): void {
        try {
            AuditLog::create([
                'user_id'    => $actor->id,
                'action'     => $action,
                'table_name' => $table,
                'record_id'  => $recordId,
                'old_data'   => $before,
                'new_data'   => $after,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Non-fatal: audit log failure must not break the main flow
        }
    }
}
