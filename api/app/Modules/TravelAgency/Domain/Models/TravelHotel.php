<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelHotelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Hôtel du catalogue TravelAgency (TRAVEL-214, issue #6027).
 *
 * `classification` bornée 1-5 étoiles (contrainte DB
 * `travel_hotels_classification_check`).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property int $city_id
 * @property int $classification
 * @property string|null $address
 * @property string|null $contact_phone
 * @property string|null $description_redacted
 * @property TravelRecordStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelHotel extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelHotelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'city_id',
        'classification',
        'address',
        'contact_phone',
        'description_redacted',
        'status',
    ];

    protected $casts = [
        'classification' => 'integer',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class, 'city_id');
    }

    /**
     * @return HasMany<TravelHotelRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(TravelHotelRoom::class, 'hotel_id');
    }
}
