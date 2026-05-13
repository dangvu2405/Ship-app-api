<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'month',
        'year',
        'status',
        'locked_at',
        'notes',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
