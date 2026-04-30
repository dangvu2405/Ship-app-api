<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripDocument extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'trip_id',
        'doc_type',
        'doc_name',
        'file_url',
        'file_size_kb',
        'uploaded_by',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'trip_id' => 'integer',
        'file_size_kb' => 'integer',
        'uploaded_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
