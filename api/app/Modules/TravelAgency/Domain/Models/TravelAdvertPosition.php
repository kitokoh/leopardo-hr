<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-905 (#6108) — Position de publication d'une annonce payante
 * (tenant-scoped).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 *
 * @mixin Builder<static>
 */
class TravelAdvertPosition extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_advert_positions';

    protected $fillable = ['company_id', 'code', 'name', 'description'];
}
