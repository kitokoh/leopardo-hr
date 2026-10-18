<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmReproductionEventType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Evenement de reproduction.
 *
 * `expected_due_at` est DECLARE, ou calcule depuis `gestation_days` SAISI par
 * l'exploitant. Farm OS ne possede aucune table de durees de gestation par
 * espece et ne pretend donc jamais connaitre une date de mise bas.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_livestock_batch_id
 * @property int|null $farm_animal_id
 * @property string $event_type
 * @property Carbon $occurred_at
 * @property Carbon|null $expected_due_at
 * @property int|null $gestation_days
 * @property int|null $offspring_count
 * @property int|null $sire_animal_id
 * @property string|null $method
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property string|null $client_reproduction_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmReproductionEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_reproduction_events';

    protected $fillable = [
        'company_id',
        'farm_livestock_batch_id',
        'farm_animal_id',
        'event_type',
        'occurred_at',
        'expected_due_at',
        'gestation_days',
        'offspring_count',
        'sire_animal_id',
        'method',
        'notes',
        'recorded_by',
        'client_reproduction_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'expected_due_at' => 'datetime',
        'gestation_days' => 'integer',
        'offspring_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): ?FarmReproductionEventType
    {
        return FarmReproductionEventType::tryFrom($this->event_type);
    }

    /**
     * @return BelongsTo<FarmLivestockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FarmLivestockBatch::class, 'farm_livestock_batch_id');
    }
}
