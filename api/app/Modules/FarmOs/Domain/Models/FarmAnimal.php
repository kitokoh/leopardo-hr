<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmAnimalSex;
use App\Modules\FarmOs\Domain\Enums\FarmAnimalStatus;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Animal (identification individuelle).
 *
 * `birth_date` et `origin` sont DECLARES : Farm OS ne deduit jamais un age ni
 * une origine d'un document ou d'un identifiant.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_livestock_batch_id
 * @property int|null $farm_site_id
 * @property string $tag_number
 * @property string|null $name
 * @property string $species
 * @property string|null $breed
 * @property string $sex
 * @property Carbon|null $birth_date
 * @property string $origin
 * @property string|null $location
 * @property string $status
 * @property string|null $notes
 * @property string|null $client_animal_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmAnimal extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_animals';

    /** Origines du registre FERME (extension par nouvelle case). */
    public const ORIGINS = ['born', 'purchased', 'transferred', 'unknown'];

    protected $fillable = [
        'company_id',
        'farm_livestock_batch_id',
        'farm_site_id',
        'tag_number',
        'name',
        'species',
        'breed',
        'sex',
        'birth_date',
        'origin',
        'location',
        'status',
        'notes',
        'client_animal_id',
        'created_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
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
     * @return BelongsTo<FarmLivestockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FarmLivestockBatch::class, 'farm_livestock_batch_id');
    }

    /**
     * Age en jours, ou `null` si la date de naissance n'est pas connue : Farm OS
     * n'invente jamais un age.
     */
    public function ageInDays(?Carbon $at = null): ?int
    {
        if ($this->birth_date === null) {
            return null;
        }

        return (int) $this->birth_date->diffInDays($at ?? Carbon::now());
    }
}
