<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelClassFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Classe de service de la verticale TravelAgency (TRAVEL-204, issue #6017).
 *
 * Référentiel tenant-scoped (ex. Économique/Business) — code unique par
 * tenant, priorité d'affichage. Consommée par `travel_trip_prices`
 * (tarif par trajet/classe).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $label
 * @property string $color
 * @property int $priority
 * @property TravelRecordStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelClass extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelClassFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'color',
        'priority',
        'status',
    ];

    protected $casts = [
        'priority' => 'integer',
        'status' => TravelRecordStatus::class,
    ];
}
