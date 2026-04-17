<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KnowledgeArticle extends Model
{
    protected $fillable = [
        'company_id',
        'tenant_priority',
        'category',
        'title',
        'content',
        'tags',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'tenant_priority' => 'integer',
        'tags' => 'array',
        'is_active' => 'boolean',
    ];

    /** Scope: chỉ lấy bài đang hoạt động, ưu tiên bài của công ty trước, sau đó system-wide */
    public function scopeVisibleTo(Builder $query, ?int $companyId): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($companyId): void {
                $q->whereNull('company_id');
                if ($companyId !== null) {
                    $q->orWhere('company_id', $companyId);
                }
            });
    }
}
