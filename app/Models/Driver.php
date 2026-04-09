<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
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

    protected $casts = [
        'expired_date' => 'date',
        'driver_insurance_expired_date' => 'date',
        'health_certificate_expired_date' => 'date',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
