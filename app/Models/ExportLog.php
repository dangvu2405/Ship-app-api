<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'file_name',
        'file_path',
        'record_count',
    ];

    protected $casts = [
        'record_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
