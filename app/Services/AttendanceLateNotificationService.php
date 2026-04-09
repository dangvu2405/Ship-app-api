<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use App\Notifications\LateAttendanceNotification;
use Carbon\Carbon;

class AttendanceLateNotificationService
{
    public function isLate(Attendance $attendance): bool
    {
        if ($attendance->status === 'late') {
            return true;
        }

        $rawCheckIn = $attendance->getRawOriginal('check_in');
        if (! is_string($rawCheckIn) || $rawCheckIn === '') {
            return false;
        }

        $lateAfter = (string) config('ship.attendance.late_after', '08:15');
        $checkInTime = Carbon::parse($rawCheckIn);
        $lateAfterTime = Carbon::parse($checkInTime->format('Y-m-d') . ' ' . $lateAfter);

        return $checkInTime->greaterThan($lateAfterTime);
    }

    /**
     * @return int Number of users notified
     */
    public function notifyForAttendance(Attendance $attendance, ?User $actor = null): int
    {
        if (! $this->isLate($attendance)) {
            return 0;
        }

        $attendance->loadMissing('employee.user');

        $recipientIds = [];

        $employeeUserId = $attendance->employee?->user?->id;
        if (is_int($employeeUserId)) {
            $recipientIds[] = $employeeUserId;
        }

        $adminIds = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->pluck('id')
            ->all();

        $recipientIds = array_values(array_unique(array_merge($recipientIds, $adminIds)));

        if ($recipientIds === []) {
            return 0;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new LateAttendanceNotification($attendance, $actor));
        }

        return $recipients->count();
    }

    /**
     * @return array<string, int|string>
     */
    public function notifyByDate(string $date, ?User $actor = null): array
    {
        $records = Attendance::query()
            ->with('employee.user')
            ->whereDate('date', $date)
            ->get();

        $lateRecords = $records->filter(fn (Attendance $attendance): bool => $this->isLate($attendance));

        $notifiedCount = 0;
        foreach ($lateRecords as $attendance) {
            $notifiedCount += $this->notifyForAttendance($attendance, $actor);
        }

        return [
            'date' => $date,
            'records' => $records->count(),
            'late_records' => $lateRecords->count(),
            'notified_users' => $notifiedCount,
        ];
    }
}
