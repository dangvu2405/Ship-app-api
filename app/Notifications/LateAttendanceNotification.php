<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LateAttendanceNotification extends Notification
{
    public function __construct(
        private readonly Attendance $attendance,
        private readonly ?User $actor = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employeeName = $this->attendance->employee?->name ?? 'Nhân viên';
        $actor = $this->actor?->username ?? $this->actor?->email ?? 'Hệ thống';
        $checkIn = $this->attendance->getRawOriginal('check_in') ?? 'N/A';

        return (new MailMessage)
            ->subject('[Ship] Cảnh báo chấm công đi muộn')
            ->line("{$employeeName} có bản ghi chấm công đi muộn.")
            ->line('Ngày: '.$this->attendance->date?->format('Y-m-d'))
            ->line('Giờ check-in: '.$checkIn)
            ->line('Nguồn ghi nhận: '.$actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'attendance.late',
            'attendance_id' => $this->attendance->id,
            'employee_id' => $this->attendance->employee_id,
            'employee_name' => $this->attendance->employee?->name,
            'date' => $this->attendance->date?->format('Y-m-d'),
            'check_in' => $this->attendance->getRawOriginal('check_in'),
            'status' => $this->attendance->status,
            'triggered_by' => $this->actor?->id,
        ];
    }
}
