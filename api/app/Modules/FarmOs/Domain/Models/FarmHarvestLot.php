<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.2 Lot de recolte (tracabilite).
 *
 * INVARIANT CENTRAL : `quantity_harvested` est ecrite UNE SEULE FOIS a la
 * creation et n'est JAMAIS mise a jour — c'est la reference de tracabilite.
 * `quantity_remaining` est un CACHE derive du journal `farm_storage_movements` :
 * il n'est jamais incremente directement par un appelant, seulement par
 * {@see \App\Modules\FarmOs\Domain\Services\FarmHarvestService}, dans la meme
 * transaction. C'est ce qui garantit qu'une meme quantite n'est jamais comptee
 * deux fois entre recolte, stock, vente et reporting.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $parcel_id
 * @property int|null $crop_cycle_id
 * @property int|null $farm_site_id
 * @property int|null $farm_operation_id
 * @property string $lot_code
 * @property string|null $crop_name
 * @property string $quantity_harvested
 * @property string $unit
 * @property string|null $quality_grade
 * @property int|null $storage_location_id
 * @property Carbon $harvested_at
 * @property string $quantity_remaining
 * @property string $status
 * @property string|null $notes
 * @property string|null $client_harvest_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmHarvestLot extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_harvest_lots';

    /** Statuts du registre FERME (extension par nouvelle case). */
    public const STATUSES = ['open', 'closed'];

    protected $fillable = [
        'company_id',
        'parcel_id',
        'crop_cycle_id',
        'farm_site_id',
        'farm_operation_id',
        'lot_code',
        'crop_name',
        'quantity_harvested',
        'unit',
        'quality_grade',
        'storage_location_id',
        'harvested_at',
        'quantity_remaining',
        'status',
        'notes',
        'client_harvest_id',
        'created_by',
    ];

    protected $casts = [
        'quantity_harvested' => 'decimal:3',
        'quantity_remaining' => 'decimal:3',
        'harvested_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return HasMany<FarmStorageMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(FarmStorageMovement::class, 'farm_harvest_lot_id');
    }

    /**
     * @return BelongsTo<FarmStorageLocation, $this>
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(FarmStorageLocation::class, 'storage_location_id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Quantite deja sortie du lot (pertes + ventes + consommation).
     *
     * Calculee depuis le JOURNAL, jamais depuis un compteur separe : c'est ce
     * qui rend le rapprochement recolte / stock / vente verifiable.
     */
    public function quantityDispatched(): string
    {
        $total = 0.0;

        foreach ($this->movements as $movement) {
            if ($movement->typeEnum()->isOutbound()) {
                $total += (float) $movement->quantity;
            }
        }

        return number_format($total, 3, '.', '');
    }
}
