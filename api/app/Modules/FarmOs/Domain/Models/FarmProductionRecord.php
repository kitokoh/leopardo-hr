<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmProductionType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Production animale (lait, oeufs, laine, miel...).
 *
 * Quantite DECLAREE : Farm OS n'estime jamais un rendement et ne deduit jamais
 * une production d'un effectif.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_livestock_batch_id
 * @property int|null $farm_animal_id
 * @property string $production_type
 * @property string $quantity
 * @property string $unit
 * @property string|null $quality_grade
 * @property Carbon $produced_at
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property string|null $client_production_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmProductionRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_production_records';

    protected $fillable = [
        'company_id',
        'farm_livestock_batch_id',
        'farm_animal_id',
        'production_type',
        'quantity',
        'unit',
        'quality_grade',
        'produced_at',
        'notes',
        'recorded_by',
        'client_production_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'produced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): ?FarmProductionType
    {
        return FarmProductionType::tryFrom($this->production_type);
    }

    /**
     * @return BelongsTo<FarmLivestockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FarmLivestockBatch::class, 'farm_livestock_batch_id');
    }
}
