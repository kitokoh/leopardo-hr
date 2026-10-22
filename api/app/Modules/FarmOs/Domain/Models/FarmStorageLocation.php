<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmStorageLocationType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.2 Emplacement de stockage (grange, silo, entrepot, chambre froide).
 *
 * `capacity` est DECLAREE et NULLABLE : une capacite inconnue reste inconnue,
 * Farm OS ne l'estime jamais a partir d'un volume de recolte.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_site_id
 * @property string $name
 * @property string|null $reference
 * @property string $location_type
 * @property string|null $capacity
 * @property string|null $capacity_unit
 * @property string|null $notes
 * @property string|null $client_location_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmStorageLocation extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_storage_locations';

    protected $fillable = [
        'company_id',
        'farm_site_id',
        'name',
        'reference',
        'location_type',
        'capacity',
        'capacity_unit',
        'notes',
        'client_location_id',
        'created_by',
    ];

    protected $casts = [
        'capacity' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): FarmStorageLocationType
    {
        return FarmStorageLocationType::tryFrom($this->location_type) ?? FarmStorageLocationType::Other;
    }

    /**
     * @return HasMany<FarmHarvestLot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(FarmHarvestLot::class, 'storage_location_id');
    }
}
