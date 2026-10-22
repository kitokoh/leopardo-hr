<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmLivestockEventType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Mouvement d'effectif (journal immuable).
 *
 * Chaque changement d'effectif d'un lot laisse EXACTEMENT une ligne ici. La
 * quantite est toujours POSITIVE : le sens est porte par `event_type`.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_livestock_batch_id
 * @property int|null $farm_animal_id
 * @property string $event_type
 * @property int $quantity
 * @property int|null $head_count_after
 * @property Carbon $occurred_at
 * @property int|null $counterparty_id
 * @property string|null $unit_price
 * @property string|null $currency
 * @property string|null $reason
 * @property int|null $recorded_by
 * @property string|null $client_event_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmLivestockEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_livestock_events';

    protected $fillable = [
        'company_id',
        'farm_livestock_batch_id',
        'farm_animal_id',
        'event_type',
        'quantity',
        'head_count_after',
        'occurred_at',
        'counterparty_id',
        'unit_price',
        'currency',
        'reason',
        'recorded_by',
        'client_event_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'head_count_after' => 'integer',
        'unit_price' => 'decimal:2',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): ?FarmLivestockEventType
    {
        return FarmLivestockEventType::tryFrom($this->event_type);
    }

    /**
     * @return BelongsTo<FarmLivestockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FarmLivestockBatch::class, 'farm_livestock_batch_id');
    }
}
