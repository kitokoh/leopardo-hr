<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $module
 * @property string|null $request_id
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<mixed> $old_values
 * @property array<mixed> $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<mixed> $metadata
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'module',
        'request_id',
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
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForCompany(Builder $q, string $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForModel(Builder $q, string $type, int $id): Builder
    {
        return $q->where('auditable_type', $type)->where('auditable_id', $id);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForModule(Builder $q, string $module): Builder
    {
        return $q->where('module', $module);
    }

    /**
     * API d'écriture unifiée du journal d'audit global (#5439).
     *
     * @param  string  $module  Domaine métier : attendance|payroll|planning|hr|auth|…
     * @param  string  $action  Action tracée (ex. planning.absence.approve).
     * @param  Model|null  $subject  Entité concernée (morph auditable) — optionnelle.
     * @param  Employee|User|null  $actor  Acteur authentifié — optionnel.
     * @param  array<string, mixed>  $oldValues  État avant (JSON).
     * @param  array<string, mixed>  $newValues  État après (JSON).
     * @param  string|null  $requestId  Identifiant de corrélation (#1874).
     * @param  array<string, mixed>  $metadata  Données complémentaires.
     */
    public static function record(
        string $module,
        string $action,
        ?Model $subject = null,
        Employee|User|null $actor = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $requestId = null,
        array $metadata = [],
    ): self {
        $companyId = $actor instanceof Employee ? $actor->company_id : null;
        if ($companyId === null && $subject !== null) {
            $companyId = $subject->getAttribute('company_id');
        }
        if ($companyId === null && function_exists('currentCompany')) {
            $companyId = currentCompany()->id;
        }

        $ip = null;
        $userAgent = null;
        $request = app()->bound('request') ? app('request') : null;
        if ($request instanceof Request) {
            $ip = $request->ip();
            $userAgent = $request->userAgent();
        }

        return self::query()->create([
            'company_id' => $companyId !== null ? (string) $companyId : null,
            'user_id' => $actor?->id,
            'action' => $action,
            'module' => $module,
            'request_id' => $requestId ?? (function_exists('correlation_id') ? correlation_id() : null),
            'auditable_type' => $subject !== null ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 500) : null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
