<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LarkEventLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'request_id',
        'event_type',
        'signature_valid',
        'replay_blocked',
        'status',
        'headers',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'replay_blocked' => 'boolean',
        'headers' => 'array',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
