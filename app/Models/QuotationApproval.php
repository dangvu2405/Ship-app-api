<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'actor_id',
        'action',
        'reason',
        'snapshot',
    ];

    protected $casts = [
        'quotation_id' => 'integer',
        'actor_id' => 'integer',
        'snapshot' => 'array',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

