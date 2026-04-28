<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Models\Office;
use App\Models\OfficeScheduleApplication;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ApplyOfficeScheduleService
{
    /**
     * @return array{driver_count: int, day_count: int, row_count: int}
     */
    public function estimateBulkRows(Office $office, string $startDate, string $endDate): array
    {
        $driverCount = (int) Driver::query()->where('office_id', $office->id)->where('status', 'active')->count();
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $dayCount = (int) $start->diffInDays($end) + 1;

        return [
            'driver_count' => $driverCount,
            'day_count' => $dayCount,
            'row_count' => $driverCount * $dayCount,
        ];
    }

    private function insertChunkSize(): int
    {
        return (int) config('ship.office_schedule_apply.insert_chunk_size', 400);
    }

    /**
     * Áp template lịch cho toàn bộ tài xế thuộc văn phòng trong khoảng ngày (bản ghi draft trên driver_work_schedules).
     *
     * @return array{application_id: int, drivers_count: int, days_count: int, rows_created: int}
     */
    public function apply(
        Office $office,
        WorkScheduleTemplate $template,
        string $startDate,
        string $endDate,
        User $actor,
        ?string $notes = null,
        bool $replaceDrafts = true,
    ): array {
        if ((int) $template->company_id !== (int) $office->company_id) {
            throw new InvalidArgumentException('Khung giờ không thuộc cùng công ty với văn phòng.', 422);
        }

        if (! $template->is_active) {
            throw new InvalidArgumentException('Khung giờ đã bị vô hiệu hóa.', 422);
        }

        $driver_ids = Driver::query()
            ->where('office_id', $office->id)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        if ($driver_ids === []) {
            throw new InvalidArgumentException('Văn phòng không có tài xế nào.', 422);
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        if ($end->lt($start)) {
            throw new InvalidArgumentException('end_date phải >= start_date.', 422);
        }

        $dates = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        $startTime = Carbon::parse($template->start_time)->format('H:i:s');
        $endTime = Carbon::parse($template->end_time)->format('H:i:s');
        $now = now();

        $rows = [];
        foreach ($driver_ids as $driver_id) {
            foreach ($dates as $work_date) {
                $rows[] = [
                    'driver_id' => $driver_id,
                    'company_id' => $office->company_id,
                    'office_id' => $office->id,
                    'work_date' => $work_date,
                    'shift_code' => $template->shift_code,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'vehicle_id' => null,
                    'status' => 'draft',
                    'notes' => $notes,
                    'submitted_by' => null,
                    'submitted_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'locked_by' => null,
                    'locked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return DB::transaction(function () use (
            $office,
            $template,
            $startDate,
            $endDate,
            $actor,
            $rows,
            $replaceDrafts,
            $driver_ids,
            $dates,
        ): array {
            $hasBlocking = DB::table('driver_work_schedules')
                ->whereIn('driver_id', $driver_ids)
                ->whereBetween('work_date', [$startDate, $endDate])
                ->where('shift_code', $template->shift_code)
                ->whereNotIn('status', ['draft'])
                ->exists();

            if ($hasBlocking) {
                throw new InvalidArgumentException(
                    'Đã có lịch submitted/approved/locked trùng ca trong khoảng ngày — không thể áp dụng hàng loạt.',
                    422,
                );
            }

            if ($replaceDrafts) {
                DB::table('driver_work_schedules')
                    ->where('office_id', $office->id)
                    ->where('shift_code', $template->shift_code)
                    ->whereBetween('work_date', [$startDate, $endDate])
                    ->where('status', 'draft')
                    ->delete();
            }

            $chunkSize = $this->insertChunkSize();
            if ($replaceDrafts) {
                foreach (array_chunk($rows, $chunkSize) as $chunk) {
                    DB::table('driver_work_schedules')->insert($chunk);
                }
                $inserted = count($rows);
            } else {
                $inserted = 0;
                foreach (array_chunk($rows, $chunkSize) as $chunk) {
                    $inserted += DB::table('driver_work_schedules')->insertOrIgnore($chunk);
                }
            }

            $application = OfficeScheduleApplication::query()->create([
                'office_id' => $office->id,
                'work_schedule_template_id' => $template->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'applied_by' => $actor->id,
                'drivers_affected' => count($driver_ids),
                'rows_created' => $inserted,
                'meta' => [
                    'replace_drafts' => $replaceDrafts,
                    'days' => count($dates),
                ],
            ]);

            return [
                'application_id' => $application->id,
                'drivers_count' => count($driver_ids),
                'days_count' => count($dates),
                'rows_created' => $inserted,
            ];
        });
    }
}
