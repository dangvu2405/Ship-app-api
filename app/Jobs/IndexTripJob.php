<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RagIndex;
use App\Models\Trip;
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
 * Upserts a single Trip into rag_index.
 *
 * Only completed/in_progress trips are indexed; pending/cancelled are removed.
 */
class IndexTripJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Trip $trip,
    ) {}

    public function handle(EmbeddingService $embedder): void
    {
        $trip = $this->trip->fresh(['driver', 'vehicle', 'customer']);

        if ($trip === null) {
            return;
        }

        // Only index actionable statuses
        if (in_array($trip->status, ['cancelled'], true)) {
            RagIndex::withoutGlobalScopes()
                ->where('source_table', 'trips')
                ->where('source_id', $trip->id)
                ->delete();

            return;
        }

        $content   = $this->buildDescription($trip);
        $embedding = $embedder->embed($content);

        RagIndex::withoutGlobalScopes()->updateOrCreate(
            ['source_table' => 'trips', 'source_id' => $trip->id],
            [
                'company_id'        => $trip->company_id,
                'content'           => $content,
                'embedding'         => $embedding,
                'metadata'          => [
                    'status'      => $trip->status,
                    'driver_id'   => $trip->driver_id,
                    'driver_name' => $trip->driver?->name,
                    'vehicle'     => $trip->vehicle?->plate_number,
                    'from'        => $trip->start_point,
                    'to'          => $trip->end_point,
                    'distance_km' => $trip->distance_km,
                    'date'        => $trip->start_time?->toDateString(),
                ],
                'source_updated_at' => $trip->updated_at ?? Carbon::now(),
            ],
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('IndexTripJob failed', [
            'trip_id' => $this->trip->id,
            'error'   => $exception->getMessage(),
        ]);
    }

    private function buildDescription(Trip $trip): string
    {
        $parts = [];

        $parts[] = sprintf(
            'Chuyến xe %s từ %s đến %s.',
            $trip->code ?? "#{$trip->id}",
            $trip->start_point ?? 'điểm xuất phát',
            $trip->end_point   ?? 'điểm đến',
        );

        if ($trip->driver) {
            $parts[] = sprintf('Tài xế: %s.', $trip->driver->name);
        }

        if ($trip->vehicle) {
            $parts[] = sprintf('Xe: %s.', $trip->vehicle->plate_number);
        }

        if ($trip->customer) {
            $parts[] = sprintf('Khách hàng: %s.', $trip->customer->name);
        }

        $statusMap = [
            'pending'     => 'chờ xuất phát',
            'in_progress' => 'đang trên đường',
            'completed'   => 'đã hoàn thành',
        ];
        $parts[] = sprintf('Trạng thái: %s.', $statusMap[$trip->status] ?? $trip->status);

        if ($trip->distance_km) {
            $parts[] = sprintf('Quãng đường: %s km.', number_format((float) $trip->distance_km, 0, '.', ','));
        }

        if ($trip->start_time) {
            $parts[] = sprintf('Khởi hành: %s.', $trip->start_time->format('d/m/Y H:i'));
        }
        if ($trip->end_time) {
            $parts[] = sprintf('Kết thúc: %s.', $trip->end_time->format('d/m/Y H:i'));
        }

        if ($trip->price) {
            $parts[] = sprintf('Giá trị chuyến: %s VNĐ.', number_format((float) $trip->price, 0, '.', ','));
        }

        return implode(' ', $parts);
    }
}
