<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Établissement d'hôtellerie / locatif — HOSP-002 (#7944, BC-32).
 *
 * Hôtel, résidence hôtelière, immeuble d'appartements ou maison d'hôtes.
 * Code unique par tenant ; slug public unique GLOBAL, généré au passage
 * `is_public=true` (vitrine /stay — HOSP-006/008).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string|null $address
 * @property string|null $city
 * @property string $country
 * @property string $timezone
 * @property string $currency
 * @property string|null $phone
 * @property string|null $email
 * @property int|null $star_rating
 * @property array<string, mixed>|null $amenities
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $status
 * @property bool $is_public
 * @property string|null $public_slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HospitalityProperty extends Model
{
    use BelongsToCompany;

    public const TYPE_HOTEL = 'hotel';

    public const TYPE_RESIDENCE = 'residence';

    public const TYPE_APARTMENT_BUILDING = 'apartment_building';

    public const TYPE_GUESTHOUSE = 'guesthouse';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_HOTEL,
        self::TYPE_RESIDENCE,
        self::TYPE_APARTMENT_BUILDING,
        self::TYPE_GUESTHOUSE,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $table = 'hospitality_properties';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'address',
        'city',
        'country',
        'timezone',
        'currency',
        'phone',
        'email',
        'star_rating',
        'amenities',
        'latitude',
        'longitude',
        'status',
        'is_public',
        'public_slug',
    ];

    protected $casts = [
        'amenities' => 'array',
        'is_public' => 'boolean',
        'star_rating' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'status' => 'string',
        'type' => 'string',
    ];

    /**
     * @return HasMany<HospitalityRoomType, $this>
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(HospitalityRoomType::class, 'property_id');
    }

    /**
     * @return HasMany<HospitalityUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(HospitalityUnit::class, 'property_id');
    }
}
