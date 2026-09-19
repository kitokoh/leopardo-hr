<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailPosSessionStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Session de caisse POS du module Retail (BC-17 RETAIL, #7674).
 *
 * Une seule session `open` par (tenant, emplacement) — index unique partiel
 * Postgres + garde applicative (RetailPosService). Montants en minor units ;
 * `variance_minor` = `counted_cash_minor` - `expected_cash_minor` (signe).
 * `version` protege la cloture contre les ecritures concurrentes.
 * Ecritures UNIQUEMENT via RetailPosService (transaction).
 *
 * @property int $id
 * @property string $company_id
 * @property int $location_id
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property int $opened_by_user_id
 * @property int|null $closed_by_user_id
 * @property int $opening_cash_minor
 * @property int|null $expected_cash_minor
 * @property int|null $counted_cash_minor
 * @property int|null $variance_minor
 * @property string|null $variance_reason
 * @property RetailPosSessionStatus $status
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailPosSession extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_pos_sessions';

    protected $fillable = [
        'company_id',
        'location_id',
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash_minor' => 'integer',
            'expected_cash_minor' => 'integer',
            'counted_cash_minor' => 'integer',
            'variance_minor' => 'integer',
            'status' => RetailPosSessionStatus::class,
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RetailLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(RetailLocation::class, 'location_id');
    }

    /**
     * @return HasMany<RetailOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(RetailOrder::class, 'pos_session_id');
    }
}
