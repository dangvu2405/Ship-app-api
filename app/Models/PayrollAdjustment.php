<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_detail_id',
        'type',
        'reason',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payrollDetail()
    {
        return $this->belongsTo(PayrollDetail::class);
    }
}
