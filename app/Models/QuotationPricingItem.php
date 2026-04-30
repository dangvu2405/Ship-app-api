<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationPricingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'type',
        'code',
        'label',
        'amount',
        'is_mandatory',
    ];

    protected $casts = [
        'quotation_id' => 'integer',
        'amount' => 'decimal:2',
        'is_mandatory' => 'boolean',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}

