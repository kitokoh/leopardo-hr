<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantOfflineOperationType;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantSyncStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Restaurant OS — REST-OS-7 Operation locale poussee par un terminal (#8609).
 *
 * `client_operation_id` est genere par le TERMINAL et unique par entreprise :
 * c'est lui qui rend le rejeu idempotent. `payload_hash` detecte un meme
 * identifiant pousse avec un contenu different (conflit, pas un rejeu).
 *
 * `terminal_id` + `target_entity_type`/`target_entity_id` rendent possible la
 * detection de conflit INTER-TERMINAUX : deux postes deconnectes ayant confirme
 * des operations incompatibles sur la meme entite.
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
 * @property string|null $terminal_id
 * @property string|null $target_entity_type
 * @property string|null $target_entity_id
 * @property int|null $submitted_by
 * @property Carbon $occurred_at
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class RestaurantSyncOperation extends Model
{
    use BelongsToCompany;

    protected $table = 'restaurant_sync_operations';

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
        'terminal_id',
        'target_entity_type',
        'target_entity_id',
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

    public function typeEnum(): ?RestaurantOfflineOperationType
    {
        return RestaurantOfflineOperationType::tryFrom($this->operation_type);
    }

    public function statusEnum(): RestaurantSyncStatus
    {
        return RestaurantSyncStatus::tryFrom($this->status) ?? RestaurantSyncStatus::Pending;
    }

    /**
     * @return HasMany<RestaurantSyncConflict, $this>
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(RestaurantSyncConflict::class, 'restaurant_sync_operation_id');
    }
}
