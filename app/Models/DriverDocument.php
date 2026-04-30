<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class DriverDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'driver_id',
        'doc_type',
        'doc_name',
        'doc_number',
        'issued_date',
        'expiry_date',
        'issuer',
        'file_url',
        'alert_before_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'driver_id' => 'integer',
            'issued_date' => 'date',
            'expiry_date' => 'date',
            'alert_before_days' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
