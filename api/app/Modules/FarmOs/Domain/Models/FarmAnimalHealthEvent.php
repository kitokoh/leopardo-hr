<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmHealthEventType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Evenement sanitaire (vaccination, traitement, rappel).
 *
 * Farm OS ENREGISTRE ce que l'exploitant declare. Il ne diagnostique jamais, ne
 * prescrit jamais de dosage et ne remplace jamais un professionnel qualifie.
 * `withdrawal_until` est stocke tel que declare, sans calcul.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_livestock_batch_id
 * @property int|null $farm_animal_id
 * @property string $event_type
 * @property string $title
 * @property string|null $product_name
 * @property string|null $dosage
 * @property Carbon $administered_at
 * @property Carbon|null $next_due_at
 * @property Carbon|null $withdrawal_until
 * @property int|null $veterinarian_id
 * @property string|null $cost_amount
 * @property string|null $currency
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property string|null $client_health_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmAnimalHealthEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_animal_health_events';

    protected $fillable = [
        'company_id',
        'farm_livestock_batch_id',
        'farm_animal_id',
        'event_type',
        'title',
        'product_name',
        'dosage',
        'administered_at',
        'next_due_at',
        'withdrawal_until',
        'veterinarian_id',
        'cost_amount',
        'currency',
        'notes',
        'recorded_by',
        'client_health_id',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
        'next_due_at' => 'datetime',
        'withdrawal_until' => 'date',
        'cost_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): ?FarmHealthEventType
    {
        return FarmHealthEventType::tryFrom($this->event_type);
    }

    /**
     * @return BelongsTo<FarmLivestockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FarmLivestockBatch::class, 'farm_livestock_batch_id');
    }

    /**
     * Un rappel est-il du ? Aucune echeance n'est inventee : sans `next_due_at`
     * declare, la reponse est `false`.
     */
    public function isDueAt(?Carbon $at = null): bool
    {
        if ($this->next_due_at === null) {
            return false;
        }

        return $this->next_due_at->lte($at ?? Carbon::now());
    }
}
