<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelAdvertPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grille tarifaire des annonces (TRAVEL-906, issue #6109).
 *
 * Montants en unités mineures ; devise cohérente avec celle du tenant ;
 * une seule grille par (type, position).
 *
 * @property int $id
 * @property string $company_id
 * @property int $advert_type_id
 * @property int $advert_position_id
 * @property int $price_per_image_minor
 * @property int $price_per_character_minor
 * @property string $currency
 *
 * @mixin Builder<static>
 */
class TravelAdvertPrice extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelAdvertPriceFactory> */
    use HasFactory;

    protected $table = 'travel_advert_prices';

    protected $fillable = [
        'company_id',
        'advert_type_id',
        'advert_position_id',
        'price_per_image_minor',
        'price_per_character_minor',
        'currency',
    ];

    protected $casts = [
        'price_per_image_minor' => 'integer',
        'price_per_character_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelAdvertType, $this>
     */
    public function advertType(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertType::class, 'advert_type_id');
    }

    /**
     * @return BelongsTo<TravelAdvertPosition, $this>
     */
    public function advertPosition(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertPosition::class, 'advert_position_id');
    }
}
