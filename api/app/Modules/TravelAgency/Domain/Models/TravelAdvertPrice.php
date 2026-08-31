<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TRAVEL-906 (#6109) — Tarif d'annonce (type × position, devise tenant).
 *
 * Montants en unités mineures (jamais de flottant). La devise doit
 * correspondre à celle du tenant (validation applicative).
 *
 * @property int $id
 * @property string $company_id
 * @property int $advert_type_id
 * @property int $advert_position_id
 * @property int $price_image_minor
 * @property int $price_character_minor
 * @property string $currency
 *
 * @mixin Builder<static>
 */
class TravelAdvertPrice extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_advert_prices';

    protected $fillable = [
        'company_id',
        'advert_type_id',
        'advert_position_id',
        'price_image_minor',
        'price_character_minor',
        'currency',
    ];

    protected $casts = [
        'price_image_minor' => 'integer',
        'price_character_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelAdvertType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertType::class, 'advert_type_id');
    }

    /**
     * @return BelongsTo<TravelAdvertPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertPosition::class, 'advert_position_id');
    }
}
