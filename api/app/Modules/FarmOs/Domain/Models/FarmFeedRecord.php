<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Alimentation du cheptel (ration, consommation, cout).
 *
 * `farm_input_id` peut referencer un aliment du catalogue d'intrants T2.1 : on
 * ne cree pas un second catalogue d'aliments. `cost_amount` NULLABLE : un cout
 * inconnu reste inconnu.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_livestock_batch_id
 * @property int|null $farm_animal_id
 * @property string $feed_name
 * @property int|null $farm_input_id
 * @property string $quantity
 * @property string $unit
 * @property string|null $cost_amount
 * @property string|null $currency
 * @property Carbon $fed_at
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property string|null $client_feed_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmFeedRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_feed_records';

    protected $fillable = [
        'company_id',
        'farm_livestock_batch_id',
        'farm_animal_id',
        'feed_name',
        'farm_input_id',
        'quantity',
        'unit',
        'cost_amount',
        'currency',
        'fed_at',
        'notes',
        'recorded_by',
        'client_feed_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'cost_amount' => 'decimal:2',
        'fed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FarmLivestockBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FarmLivestockBatch::class, 'farm_livestock_batch_id');
    }
}
