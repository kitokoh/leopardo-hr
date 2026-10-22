<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmAnimalSex;
use App\Modules\FarmOs\Domain\Enums\FarmAnimalStatus;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Lot de cheptel (identification par lot).
 *
 * `head_count` est l'effectif PRESENT, tenu par le journal
 * `farm_livestock_events` : il n'est jamais modifie directement par un appelant,
 * seulement par {@see \App\Modules\FarmOs\Domain\Services\FarmLivestockService}.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_site_id
 * @property string $name
 * @property string|null $reference
 * @property string $species
 * @property string|null $breed
 * @property string $sex
 * @property int $head_count
 * @property string|null $location
 * @property Carbon|null $started_on
 * @property string $status
 * @property string|null $notes
 * @property string|null $client_batch_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmLivestockBatch extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_livestock_batches';

    protected $fillable = [
        'company_id',
        'farm_site_id',
        'name',
        'reference',
        'species',
        'breed',
        'sex',
        'head_count',
        'location',
        'started_on',
        'status',
        'notes',
        'client_batch_id',
        'created_by',
    ];

    protected $casts = [
        'head_count' => 'integer',
        'started_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function speciesEnum(): ?FarmSpecies
    {
        return FarmSpecies::tryFrom($this->species);
    }

    public function sexEnum(): FarmAnimalSex
    {
        return FarmAnimalSex::tryFrom($this->sex) ?? FarmAnimalSex::Unknown;
    }

    public function statusEnum(): FarmAnimalStatus
    {
        return FarmAnimalStatus::tryFrom($this->status) ?? FarmAnimalStatus::Active;
    }

    /**
     * @return HasMany<FarmAnimal, $this>
     */
    public function animals(): HasMany
    {
        return $this->hasMany(FarmAnimal::class, 'farm_livestock_batch_id');
    }

    /**
     * @return HasMany<FarmLivestockEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(FarmLivestockEvent::class, 'farm_livestock_batch_id');
    }
}
