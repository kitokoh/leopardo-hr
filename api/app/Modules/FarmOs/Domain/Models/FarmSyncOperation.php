<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmOfflineOperationType;
use App\Modules\FarmOs\Domain\Enums\FarmSyncStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T5.2 Operation locale poussee par un appareil.
 *
 * `client_operation_id` est genere par l'APPAREIL et unique par entreprise :
 * c'est lui qui rend le rejeu idempotent. `payload_hash` detecte un meme
 * identifiant pousse avec un contenu different (conflit, pas un rejeu).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $client_operation_id
 * @property string $operation_type
 * @property string $payload_hash
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempt_count
 * @property string|null $last_error
 * @property string|null $applied_entity_type
 * @property int|null $applied_entity_id
 * @property int|null $device_id
 * @property int|null $submitted_by
 * @property Carbon $occurred_at
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmSyncOperation extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_sync_operations';

    protected $fillable = [
        'company_id',
        'client_operation_id',
        'operation_type',
        'payload_hash',
        'payload',
        'status',
        'attempt_count',
        'last_error',
        'applied_entity_type',
        'applied_entity_id',
        'device_id',
        'submitted_by',
        'occurred_at',
        'applied_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt_count' => 'integer',
        'occurred_at' => 'datetime',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): ?FarmOfflineOperationType
    {
        return FarmOfflineOperationType::tryFrom($this->operation_type);
    }

    public function statusEnum(): FarmSyncStatus
    {
        return FarmSyncStatus::tryFrom($this->status) ?? FarmSyncStatus::Pending;
    }

    /**
     * @return HasMany<FarmSyncConflict, $this>
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(FarmSyncConflict::class, 'farm_sync_operation_id');
    }
}
