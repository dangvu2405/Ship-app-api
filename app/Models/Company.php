<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, \App\Traits\HasAuditLogs;

    protected $fillable = [
        'code',
        'name',
        'tax_code',
        'address',
        'phone',
        'email',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }
}

