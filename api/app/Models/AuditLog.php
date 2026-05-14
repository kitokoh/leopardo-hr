<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<mixed> $old_values
 * @property array<mixed> $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<mixed> $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Employee, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @param string $companyId
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForCompany(Builder $q, string $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @param string $type
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForModel(Builder $q, string $type, int $id): Builder
    {
        return $q->where('auditable_type', $type)->where('auditable_id', $id);
    }
}
