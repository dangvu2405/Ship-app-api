<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vector-search index entry — one row per indexed source record.
 *
 * @property int $id
 * @property string $source_table
 * @property int $source_id
 * @property int $company_id
 * @property string $content Natural-language description in Vietnamese
 * @property float[]|null $embedding bge-m3 vector (1024 dims), NULL until indexed
 * @property array|null $metadata Structured filters (date, status, etc.)
 * @property \Carbon\Carbon $source_updated_at
 */
class RagIndex extends Model
{
    protected $table = 'rag_index';

    protected $fillable = [
        'source_table',
        'source_id',
        'company_id',
        'content',
        'embedding',
        'metadata',
        'source_updated_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
        'source_updated_at' => 'datetime',
    ];

    /**
     * Return a key for display/logging purposes.
     */
    public function sourceKey(): string
    {
        return "{$this->source_table}#{$this->source_id}";
    }
}
