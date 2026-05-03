<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

abstract class CetaModel extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    public function usesSoftDeletes(): bool
    {
        return Schema::hasColumn($this->getTable(), 'deleted_at');
    }

    protected static function booted(): void
    {
        static::saving(static function (Model $model): void {
            if (Schema::hasColumn($model->getTable(), 'company_id') && empty($model->getAttribute('company_id'))) {
                $model->setAttribute('company_id', app(\App\Tenancy\TenantContext::class)->getCompanyId());
            }
        });
    }
}
