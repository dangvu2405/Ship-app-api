<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
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
        // Organization
        'company_id',
        'team_id',
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
        'health_certificate_no',
        'health_certificate_expired_date',
        'license_class',
        'expired_date',
        'license_alert_days',
        'annual_leave_days',
        'available_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'company_id' => 'integer',
            'team_id' => 'integer',
            'dob' => 'date',
            'national_id_issue_date' => 'date',
            'join_date' => 'date',
            'resign_date' => 'date',
            'expired_date' => 'date',
            'health_certificate_expired_date' => 'date',
            'license_alert_days' => 'integer',
            'annual_leave_days' => 'integer',
        ];

        if (config('ship.encrypt_pii_fields', false)) {
            $casts['phone'] = 'encrypted';
            $casts['national_id_no'] = 'encrypted';
            $casts['bank_account_no'] = 'encrypted';
            $casts['social_insurance_no'] = 'encrypted';
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

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'driver_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(DriverTeam::class, 'team_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class, 'driver_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
