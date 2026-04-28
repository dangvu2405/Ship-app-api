<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeScheduleApplication extends Model
{
    use BelongsToOffice;

    protected $table = 'office_schedule_applications';

    protected $fillable = [
        'office_id',
        'work_schedule_template_id',
        'start_date',
        'end_date',
        'applied_by',
        'drivers_affected',
        'rows_created',
        'meta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'work_schedule_template_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'applied_by' => 'integer',
            'drivers_affected' => 'integer',
            'rows_created' => 'integer',
            'meta' => 'array',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleTemplate::class, 'work_schedule_template_id');
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
