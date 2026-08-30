<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table du plan de salle (RESTO-201, issue #6166).
 *
 * `label` est unique par (tenant, branche) ; `zone_id` est optionnel
 * (table sans zone attribuée). `is_mergeable` autorise la fusion de tables
 * pour les grands groupes.
 */
class RestaurantTable extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'zone_id',
        'label',
        'capacity',
        'min_covers',
        'is_mergeable',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
        'capacity' => 2,
        'is_mergeable' => false,
    ];

    protected $casts = [
        'capacity' => 'integer',
        'min_covers' => 'integer',
        'is_mergeable' => 'boolean',
        'status' => RestaurantRecordStatus::class,
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(RestaurantZone::class, 'zone_id');
    }
}
