<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Type de chambre d'un établissement — HOSP-002 (#7944, BC-32).
 *
 * Capacités et prix de base (en minor units). Code unique par
 * (tenant, établissement).
 *
 * @property int $id
 * @property string $company_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $capacity_adults
 * @property int $capacity_children
 * @property int $base_price_minor
 * @property string $currency
 * @property array<string, mixed>|null $amenities
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HospitalityRoomType extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $table = 'hospitality_room_types';

    protected $fillable = [
        'company_id',
        'property_id',
        'code',
        'name',
        'description',
        'capacity_adults',
        'capacity_children',
        'base_price_minor',
        'currency',
        'amenities',
        'status',
    ];

    protected $casts = [
        'amenities' => 'array',
        'capacity_adults' => 'integer',
        'capacity_children' => 'integer',
        'base_price_minor' => 'integer',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<HospitalityProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(HospitalityProperty::class, 'property_id');
    }

    /**
     * @return HasMany<HospitalityUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(HospitalityUnit::class, 'room_type_id');
    }
}
