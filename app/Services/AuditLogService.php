<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Primary (model-style) log method.
     * Use this whenever you have an Eloquent model instance.
     *
     * Usage:
     *   AuditLogService::log($claim, 'created', 'status', null, 'approved', 'reason text');
     */
    public static function log(
        Model $model,
        string $action,
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        mixed $reason = null
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => Auth::id(),
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'field'          => $field,
            'old_value'      => static::stringifyValue($oldValue),
            'new_value'      => static::stringifyValue($newValue),
            'action'         => $action,
            'reason'         => is_string($reason) ? $reason : static::stringifyValue($reason),
        ]);
    }

    /**
     * Log a batch or non-model action (e.g. attendance update, payroll recalculate).
     * Use this when you don't have a single model but still need an audit entry.
     *
     * Usage:
     *   AuditLogService::logAction('attendance_update', 'attendance_logs', $employeeId, $oldData, $newData);
     */
    public static function logAction(
        string $action,
        string $entityType,
        int $entityId,
        mixed $oldData = null,
        mixed $newData = null,
        ?string $reason = null
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => Auth::id(),
            'auditable_type' => $entityType,
            'auditable_id'   => $entityId,
            'field'          => null,
            'old_value'      => static::stringifyValue($oldData),
            'new_value'      => static::stringifyValue($newData),
            'action'         => $action,
            'reason'         => $reason,
        ]);
    }

    public static function logCreated(Model $model, ?string $reason = null): AuditLog
    {
        return static::log($model, 'created', null, null, null, $reason);
    }

    public static function logUpdated(Model $model, array $changes, ?string $reason = null): void
    {
        foreach ($changes as $field => $newValue) {
            $oldValue = $model->getOriginal($field);
            if ($oldValue !== $newValue) {
                static::log($model, 'updated', $field, $oldValue, $newValue, $reason);
            }
        }
    }

    public static function logDeleted(Model $model, ?string $reason = null): AuditLog
    {
        return static::log($model, 'deleted', null, null, null, $reason);
    }

    protected static function stringifyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
