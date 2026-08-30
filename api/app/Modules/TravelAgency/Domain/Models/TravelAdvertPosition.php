<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelAdvertPositionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Position de publication d'annonce (TRAVEL-905, issue #6108).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $label
 *
 * @mixin Builder<static>
 */
class TravelAdvertPosition extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelAdvertPositionFactory> */
    use HasFactory;

    protected $table = 'travel_advert_positions';

    protected $fillable = [
        'company_id',
        'code',
        'label',
    ];

    protected $casts = [];
}
