<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zone de la salle (terrasse, rez-de-chaussee, etc.) — RESTO-201, issue #6166.
 *
 * Le nom de la zone est unique par (tenant, branche) ; `color` est un code
 * hexadécimal optionnel utilisé pour l'affichage du plan de salle.
 */
class RestaurantZone extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'color',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status' => RestaurantRecordStatus::class,
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
