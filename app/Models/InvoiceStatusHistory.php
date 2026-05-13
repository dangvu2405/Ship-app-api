<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceStatusHistory extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'note',
        'metadata',
    ];

    public static function getTenantThroughRelation(): ?string
    {
        return 'invoice';
    }

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'invoice_id' => 'integer',
            'changed_by' => 'integer',
            'changed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
