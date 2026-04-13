<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait HasAuditLogs
{
    /**
     * Binds events to the model to generate audit logs automatically.
     */
    public static function bootHasAuditLogs()
    {
        static::created(function (Model $model) {
            self::logAction('created', $model, [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $original = array_intersect_key($model->getOriginal(), $model->getChanges());
            self::logAction('updated', $model, $original, $model->getChanges());
        });

        static::deleted(function (Model $model) {
            self::logAction('deleted', $model, $model->getAttributes(), []);
        });
    }

    /**
     * Create the audit log entry
     */
    protected static function logAction(string $action, Model $model, array $oldValues, array $newValues)
    {
        // Don't log if running from console/seeder sometimes
        $userId = auth()->id() ?? null;

        $companyId = null;
        if ($model->offsetExists('company_id')) {
            $raw = $model->getAttribute('company_id');
            $companyId = $raw !== null ? (int) $raw : null;
        }

        AuditLog::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'action' => $action,
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'old_data' => empty($oldValues) ? null : $oldValues,
            'new_data' => empty($newValues) ? null : $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() !== null ? substr((string) request()->userAgent(), 0, 512) : null,
            'request_id' => request()->header('X-Request-Id'),
        ]);
    }
}
