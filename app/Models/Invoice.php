<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'trip_id',
        'customer_id',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'status',
        'issued_at',
        'paid_at',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Invoice $invoice): void {
            if ($invoice->company_id === null && $invoice->customer_id !== null) {
                $invoice->company_id = Customer::withoutGlobalScopes()
                    ->where('id', $invoice->customer_id)
                    ->value('company_id');
            }
        });
    }

    protected $casts = [
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function trip(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
