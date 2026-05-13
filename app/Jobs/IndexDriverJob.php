<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Driver;
use App\Models\RagIndex;
use App\Services\EmbeddingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Upserts a single Driver into rag_index with a natural-language description
 * in Vietnamese, then embeds it via Ollama.
 *
 * Dispatch via:
 *   IndexDriverJob::dispatch($driver)->onQueue('indexing');
 *
 * Bulk re-index:
 *   php artisan tinker --execute="App\Models\Driver::each(fn(\$d) => App\Jobs\IndexDriverJob::dispatch(\$d));"
 */
class IndexDriverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Driver $driver,
    ) {}

    public function handle(EmbeddingService $embedder): void
    {
        $driver = $this->driver->fresh(['company']);

        if ($driver === null) {
            return;
        }

        $content = $this->buildDescription($driver);
        $embedding = $embedder->embed($content);   // NULL if Ollama is down — that's fine

        RagIndex::withoutGlobalScopes()->updateOrCreate(
            ['source_table' => 'drivers', 'source_id' => $driver->id],
            [
                'company_id' => $driver->company_id,
                'content' => $content,
                'embedding' => $embedding,
                'metadata' => [
                    'name' => $driver->name,
                    'code' => $driver->code,
                    'status' => $driver->status,
                    'available_status' => $driver->available_status,
                    'company_code' => $driver->company?->code,
                    'license_class' => $driver->license_class,
                    'license_expiry' => $driver->expired_date?->toDateString(),
                ],
                'source_updated_at' => $driver->updated_at ?? Carbon::now(),
            ],
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('IndexDriverJob failed', [
            'driver_id' => $this->driver->id,
            'error' => $exception->getMessage(),
        ]);
    }

    private function buildDescription(Driver $driver): string
    {
        $parts = [];

        $parts[] = sprintf(
            'Tài xế %s (mã: %s), thuộc công ty %s.',
            $driver->name,
            $driver->code,
            $driver->company?->code ?? 'chưa xác định',
        );

        $parts[] = sprintf(
            'Trạng thái hiện tại: %s. Trạng thái phân công: %s.',
            $this->translateStatus($driver->status),
            $this->translateAvailableStatus($driver->available_status),
        );

        if ($driver->license_class) {
            $expiry = $driver->expired_date;
            $daysLeft = $expiry ? Carbon::today()->diffInDays($expiry, false) : null;
            $expiryDesc = $expiry
                ? sprintf('%s (%s ngày)', $expiry->format('d/m/Y'), $daysLeft >= 0 ? "còn {$daysLeft}" : 'đã hết hạn')
                : 'chưa có';

            $parts[] = sprintf('Bằng lái hạng %s, hết hạn: %s.', $driver->license_class, $expiryDesc);
        }

        if ($driver->driver_insurance_expired_date) {
            $parts[] = sprintf('Bảo hiểm tài xế hết hạn: %s.', $driver->driver_insurance_expired_date->format('d/m/Y'));
        }

        if ($driver->health_certificate_expired_date) {
            $parts[] = sprintf('Giấy khám sức khỏe hết hạn: %s.', $driver->health_certificate_expired_date->format('d/m/Y'));
        }

        if ($driver->phone) {
            $parts[] = sprintf('Số điện thoại: %s.', $driver->phone);
        }

        return implode(' ', $parts);
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'active' => 'đang hoạt động',
            'inactive' => 'ngừng hoạt động',
            default => $status,
        };
    }

    private function translateAvailableStatus(?string $status): string
    {
        return match ($status) {
            'available' => 'sẵn sàng nhận chuyến',
            'on_trip' => 'đang trên chuyến',
            'off_duty' => 'nghỉ',
            null => 'chưa xác định',
            default => $status,
        };
    }
}
