<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grille tarifaire des annonces (TRAVEL-906, issue #6109). Unités mineures, devise cohérente tenant.
 */
class TravelAdvertPrice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'type_id', 'position_id', 'price_per_image_minor', 'price_per_character_minor', 'currency',
    ];

    protected $casts = [
        'price_per_image_minor' => 'integer',
        'price_per_character_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelAdvertType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertType::class, 'type_id');
    }

    /**
     * @return BelongsTo<TravelAdvertPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertPosition::class, 'position_id');
    }
}
