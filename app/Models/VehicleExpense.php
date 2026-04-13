<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleExpense extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'driver_id',
        'type',
        'amount',
        'note',
        'expense_date',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (VehicleExpense $expense): void {
            if ($expense->driver_id !== null) {
                $companyId = Driver::query()->whereKey($expense->driver_id)->value('company_id');
                if ($companyId !== null) {
                    $expense->company_id = (int) $companyId;

                    return;
                }
            }

            if ($expense->vehicle_id !== null) {
                $companyId = Vehicle::query()->whereKey($expense->vehicle_id)->value('company_id');
                if ($companyId !== null) {
                    $expense->company_id = (int) $companyId;
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
