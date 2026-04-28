<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use BelongsToOffice;
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        // Personal info
        'code',
        'name',
        'email',
        'phone',
        'dob',
        'gender',
        'address',
        'avatar_url',
        'national_id_no',
        'national_id_issue_date',
        'national_id_issue_place',
        'social_insurance_no',
        'health_insurance_no',
        'insurance_registered_at',
        // Organization
        'company_id',
        'office_id',
        'department_id',
        'position_id',
        // Status & dates
        'status',
        'join_date',
        'resign_date',
        // Bank info
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        // Driver-specific
        'license_no',
        'license_image_url',
        'identity_image_url',
        'driver_insurance_no',
        'driver_insurance_expired_date',
        'health_certificate_no',
        'health_certificate_expired_date',
        'license_class',
        'expired_date',
        'available_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'company_id' => 'integer',
            'dob' => 'date',
            'national_id_issue_date' => 'date',
            'insurance_registered_at' => 'date',
            'join_date' => 'date',
            'resign_date' => 'date',
            'expired_date' => 'date',
            'driver_insurance_expired_date' => 'date',
            'health_certificate_expired_date' => 'date',
        ];

        if (config('ship.encrypt_pii_fields', false)) {
            $casts['phone'] = 'encrypted';
            $casts['national_id_no'] = 'encrypted';
            $casts['bank_account_no'] = 'encrypted';
            $casts['social_insurance_no'] = 'encrypted';
            $casts['health_insurance_no'] = 'encrypted';
        }

        return $casts;
    }

    protected static function booted(): void
    {
        static::saving(function (Driver $driver): void {
            if ($driver->office_id === null) {
                return;
            }

            $companyId = Office::query()->whereKey($driver->office_id)->value('company_id');

            if ($companyId !== null) {
                $driver->company_id = (int) $companyId;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * The user account linked to this driver profile (FK: drivers.user_id → users.id).
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'driver_id');
    }

    public function vehicleExpenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class, 'driver_id');
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class, 'driver_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
