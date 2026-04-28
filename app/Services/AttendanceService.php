<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AttendanceService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        $useLegacy  = Schema::hasTable('attendances');
        $dateColumn = $useLegacy ? 'date' : 'work_date';

        $query = $useLegacy
            ? DB::table('attendances')
            : DB::table('driver_work_schedules')->select([
                'id',
                'driver_id',
                DB::raw('work_date as date'),
                DB::raw('start_time as check_in'),
                DB::raw('end_time as check_out'),
                DB::raw('NULL as work_hours'),
                DB::raw('NULL as overtime_hours'),
                DB::raw("CASE WHEN status IN ('approved', 'locked') THEN 'present' ELSE status END as status"),
                'created_at',
                'updated_at',
            ]);

        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', (int) $filters['driver_id']);
        }
        if (! empty($filters['date'])) {
            $query->where($dateColumn, $filters['date']);
        }
        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween($dateColumn, [$filters['from'], $filters['to']]);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = isset($filters['per_page']) ? min((int) $filters['per_page'], 200) : 50;

        return $query->orderByDesc($dateColumn)->paginate($perPage > 0 ? $perPage : 50);
    }

    /**
     * @return LengthAwarePaginator
     */
    public function getLateList(array $filters): LengthAwarePaginator
    {
        if (! Schema::hasTable('attendances')) {
            return DB::table('driver_work_schedules')
                ->where('status', 'submitted')
                ->when(! empty($filters['driver_id']), fn ($q) => $q->where('driver_id', (int) $filters['driver_id']))
                ->when(
                    ! empty($filters['from']) && ! empty($filters['to']),
                    fn ($q) => $q->whereBetween('work_date', [$filters['from'], $filters['to']]),
                )
                ->orderByDesc('work_date')
                ->paginate(50);
        }

        $query = DB::table('attendances')->where('status', 'late');

        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', (int) $filters['driver_id']);
        }
        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('date', [$filters['from'], $filters['to']]);
        }

        return $query->orderByDesc('date')->paginate(50);
    }

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
