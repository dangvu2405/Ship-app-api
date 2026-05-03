<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditLog;
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
        $useLegacy = Schema::hasTable('attendances');
        $dateColumn = $useLegacy ? 'date' : 'work_date';

        if ($useLegacy) {
            $query = Attendance::query();
        } else {
            $query = DB::table('driver_work_schedules')->select([
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
        }

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
     * @return LengthAwarePaginator<int, mixed>
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

        $query = Attendance::query()->where('status', 'late');

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
        if (! Schema::hasTable('attendances')) {
            throw new InvalidArgumentException('Attendances table is not available in this deployment.');
        }

        $date = substr($checkInTime, 0, 10);

        $existing = Attendance::query()
            ->where('driver_id', $driverId)
            ->whereDate('date', $date)
            ->first();

        if ($existing !== null) {
            throw new InvalidArgumentException("Driver #{$driverId} has already checked in on {$date}.");
        }

        $attendance = Attendance::query()->create([
            'driver_id' => $driverId,
            'date' => $date,
            'check_in' => $checkInTime,
            'status' => 'present',
        ]);

        $this->writeAuditLog($actor, 'attendance.check_in', 'attendances', $attendance->id, null, [
            'driver_id' => $driverId,
            'date' => $date,
            'check_in' => $checkInTime,
        ]);

        return $attendance->fresh()->toArray();
    }

    /**
     * Record a driver's check-out and calculate work_hours.
     *
     * @return array<string, mixed>
     */
    public function checkOut(int $driverId, string $checkOutTime, User $actor): array
    {
        if (! Schema::hasTable('attendances')) {
            throw new InvalidArgumentException('Attendances table is not available in this deployment.');
        }

        $date = substr($checkOutTime, 0, 10);

        $record = Attendance::query()
            ->where('driver_id', $driverId)
            ->whereDate('date', $date)
            ->first();

        if ($record === null) {
            throw new InvalidArgumentException("No check-in found for driver #{$driverId} on {$date}.");
        }

        if ($record->check_out !== null && (string) $record->check_out !== '') {
            throw new InvalidArgumentException("Driver #{$driverId} has already checked out on {$date}.");
        }

        $checkInTs = strtotime((string) $record->check_in);
        $checkOutTs = strtotime($checkOutTime);
        $workHours = round(($checkOutTs - $checkInTs) / 3600, 2);
        $standardHours = 8.0;
        $overtimeHours = max(0, $workHours - $standardHours);

        $record->update([
            'check_out' => $checkOutTime,
            'work_hours' => $workHours,
            'overtime_hours' => $overtimeHours,
        ]);

        $this->writeAuditLog($actor, 'attendance.check_out', 'attendances', $record->id, null, [
            'driver_id' => $driverId,
            'date' => $date,
            'check_out' => $checkOutTime,
            'work_hours' => $workHours,
            'overtime_hours' => $overtimeHours,
        ]);

        return $record->fresh()->toArray();
    }

    /**
     * Adjust an existing attendance record (admin override).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function adjust(int $attendanceId, array $data, User $actor): array
    {
        if (! Schema::hasTable('attendances')) {
            throw new InvalidArgumentException('Attendances table is not available in this deployment.');
        }

        $record = Attendance::query()->find($attendanceId);

        if ($record === null) {
            throw new InvalidArgumentException("Attendance record #{$attendanceId} not found.");
        }

        $before = $record->toArray();

        $payload = collect($data)
            ->only(['check_in', 'check_out', 'work_hours', 'overtime_hours', 'status'])
            ->all();

        $record->update($payload);

        $this->writeAuditLog($actor, 'attendance.adjust', 'attendances', $attendanceId, $before, $data);

        return $record->fresh()->toArray();
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
                'user_id' => $actor->id,
                'action' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'old_data' => $before,
                'new_data' => $after,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Non-fatal: audit log failure must not break the main flow
        }
    }
}
