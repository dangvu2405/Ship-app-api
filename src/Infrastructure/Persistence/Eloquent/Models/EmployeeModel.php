<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $dob
 * @property string|null $gender
 * @property string|null $address
 * @property string $type
 * @property string $status
 * @property string $join_date
 * @property string|null $resign_date
 * @property int $office_id
 * @property int|null $department_id
 * @property int $position_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class EmployeeModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'employees';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'email',
        'phone',
        'dob',
        'gender',
        'address',
        'type',
        'status',
        'join_date',
        'resign_date',
        'office_id',
        'department_id',
        'position_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'join_date' => 'date',
        'resign_date' => 'date',
        'office_id' => 'integer',
        'department_id' => 'integer',
        'position_id' => 'integer',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(OfficeModel::class, 'office_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionModel::class, 'position_id');
    }

    public function driver(): HasOne
    {
        return $this->hasOne(DriverModel::class, 'employee_id');
    }

    public function payrollDetails(): HasMany
    {
        return $this->hasMany(PayrollDetailModel::class, 'employee_id');
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleAssignmentModel::class, 'driver_id');
    }
}
