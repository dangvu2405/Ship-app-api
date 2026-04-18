<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkScheduleTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'work_schedule_templates';

    protected $fillable = [
        'company_id',
        'name',
        'shift_code',
        'start_time',
        'end_time',
        'is_active',
        'description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
