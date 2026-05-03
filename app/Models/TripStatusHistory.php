<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bảng `trip_status_histories` baseline dump: không có `company_id`, `reason`, `metadata`.
 * Tenant scope lọc qua `trip.company_id`.
 */
final class TripStatusHistory extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public static function getTenantThroughRelation(): ?string
    {
        return 'trip';
    }

    protected $fillable = [
        'trip_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'trip_id' => 'integer',
            'changed_by' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
