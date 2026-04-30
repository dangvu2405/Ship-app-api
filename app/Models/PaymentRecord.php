<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PaymentRecord extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'reconciliation_session_id',
        'payment_date',
        'amount',
        'payment_method',
        'bank_reference',
        'receipt_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'customer_id' => 'integer',
            'reconciliation_session_id' => 'integer',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reconciliationSession(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSession::class);
    }
}
