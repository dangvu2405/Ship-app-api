<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Driver daily attendance (legacy `attendances` table).
 * Table may be absent when non-spec table cleanup migrations have run (non-test env).
 */
final class Attendance extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'driver_id',
        'date',
        'check_in',
        'check_out',
        'work_hours',
        'overtime_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'driver_id' => 'integer',
            'date' => 'date',
            'work_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
