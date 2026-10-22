<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmConflictStrategy;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T5.2 Conflit de synchronisation.
 *
 * Conserve les DEUX valeurs en presence (locale et serveur), la strategie
 * retenue et qui a tranche. Aucun ecrasement silencieux : un conflit non resolu
 * reste visible et bloquant pour l'operation concernee.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_sync_operation_id
 * @property string $entity_type
 * @property int|null $entity_id
 * @property string $strategy
 * @property array<string, mixed>|null $local_value
 * @property array<string, mixed>|null $server_value
 * @property string|null $resolution
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmSyncConflict extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_sync_conflicts';

    protected $fillable = [
        'company_id',
        'farm_sync_operation_id',
        'entity_type',
        'entity_id',
        'strategy',
        'local_value',
        'server_value',
        'resolution',
        'resolved_by',
        'resolved_at',
        'note',
    ];

    protected $casts = [
        'local_value' => 'array',
        'server_value' => 'array',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function strategyEnum(): FarmConflictStrategy
    {
        return FarmConflictStrategy::tryFrom($this->strategy) ?? FarmConflictStrategy::ManualReview;
    }

    public function isResolved(): bool
    {
        return $this->resolution !== null;
    }

    /**
     * @return BelongsTo<FarmSyncOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(FarmSyncOperation::class, 'farm_sync_operation_id');
    }
}
