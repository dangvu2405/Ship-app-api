<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Office;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use App\Services\ApplyOfficeScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Áp dụng lịch hàng loạt khi số dòng (tài xế × ngày) vượt ngưỡng sync HTTP — xem config ship.office_schedule_apply.sync_max_rows.
 */
final class ApplyOfficeScheduleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $officeId,
        public readonly int $templateId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $actorUserId,
        public readonly ?string $notes,
        public readonly bool $replaceDrafts,
    ) {}

    public function handle(ApplyOfficeScheduleService $service): void
    {
        $office = Office::query()->findOrFail($this->officeId);
        $template = WorkScheduleTemplate::query()->findOrFail($this->templateId);
        $actor = User::query()->findOrFail($this->actorUserId);

        $service->apply(
            office: $office,
            template: $template,
            startDate: $this->startDate,
            endDate: $this->endDate,
            actor: $actor,
            notes: $this->notes,
            replaceDrafts: $this->replaceDrafts,
        );
    }

    public function failed(?Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('ApplyOfficeScheduleJob failed', [
            'office_id'   => $this->officeId,
            'template_id' => $this->templateId,
            'start_date'  => $this->startDate,
            'end_date'    => $this->endDate,
            'actor'       => $this->actorUserId,
            'error'       => $exception?->getMessage(),
        ]);
    }
}
