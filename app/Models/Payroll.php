<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'month',
        'year',
        'status',
        'locked_at',
        'notes',
        'snapshot_json',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'locked_at' => 'datetime',
        'snapshot_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
