<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantPosSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Session de caisse POS (RESTO-208, issue #6173).
 *
 * Une seule session ouverte par branche (UNIQUE tenant, branche, statut) ;
 * `variance_minor` = `counted_cash_minor` − `expected_cash_minor` (minor units).
 * `version` protège la clôture contre les écritures concurrentes.
 */
class RestaurantPosSession extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantPosSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'opened_at',
        'closed_at',
        'opened_by_user_id',
        'closed_by_user_id',
        'opening_cash_minor',
        'expected_cash_minor',
        'counted_cash_minor',
        'variance_minor',
        'variance_reason',
        'status',
        'version',
    ];

    protected $attributes = [
        'status' => 'open',
        'opening_cash_minor' => 0,
        'version' => 1,
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash_minor' => 'integer',
        'expected_cash_minor' => 'integer',
        'counted_cash_minor' => 'integer',
        'variance_minor' => 'integer',
        'status' => PosSessionStatus::class,
        'version' => 'integer',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class, 'pos_session_id');
    }
}
