<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantConflictStrategy;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Restaurant OS — REST-OS-7 Conflit de synchronisation (#8609).
 *
 * Conserve les DEUX valeurs en presence (locale et serveur), les terminaux
 * emetteurs, la strategie retenue et qui a tranche. Aucun ecrasement silencieux :
 * un conflit non resolu reste visible et bloquant pour l'operation concernee.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $restaurant_sync_operation_id
 * @property string $entity_type
 * @property string|null $entity_id
 * @property string $strategy
 * @property array<string, mixed>|null $local_value
 * @property array<string, mixed>|null $server_value
 * @property string|null $local_terminal_id
 * @property string|null $server_terminal_id
 * @property string|null $resolution
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class RestaurantSyncConflict extends Model
{
    use BelongsToCompany;

    protected $table = 'restaurant_sync_conflicts';

    protected $fillable = [
        'company_id',
        'restaurant_sync_operation_id',
        'entity_type',
        'entity_id',
        'strategy',
        'local_value',
        'server_value',
        'local_terminal_id',
        'server_terminal_id',
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

    public function strategyEnum(): RestaurantConflictStrategy
    {
        return RestaurantConflictStrategy::tryFrom($this->strategy) ?? RestaurantConflictStrategy::ManualReview;
    }

    public function isResolved(): bool
    {
        return $this->resolution !== null;
    }

    /**
     * @return BelongsTo<RestaurantSyncOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(RestaurantSyncOperation::class, 'restaurant_sync_operation_id');
    }
}
