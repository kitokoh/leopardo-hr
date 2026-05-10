<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            static::recordAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            $dirty = $model->getDirty();
            if ($dirty === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $dirty);
            static::recordAudit($model, 'updated', $old, $dirty);
        });

        static::deleted(function (Model $model): void {
            static::recordAudit($model, 'deleted', $model->getOriginal(), []);
        });
    }

    protected static function recordAudit(Model $model, string $action, array $old, array $new): void
    {
        $companyId = $model->getAttribute('company_id');
        if ($companyId === null) {
            return;
        }

        $request = request();
        $employee = $request?->user();

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $employee?->id,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? mb_substr($request->userAgent(), 0, 500) : null,
        ]);
    }
}
