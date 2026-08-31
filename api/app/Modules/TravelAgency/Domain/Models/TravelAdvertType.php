<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-905 (#6108) — Type d'annonce payante (tenant-scoped).
use Database\Factories\TravelAdvertTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Type d'annonce payante (TRAVEL-905, issue #6108).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 *
 * @mixin Builder<static>
use Illuminate\Database\Eloquent\Model;

/**
 * Type d'annonce (TRAVEL-905, issue #6108).
 * @property string $label
 *
 * @mixin Builder<static>
 */
class TravelAdvertType extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_advert_types';

    protected $fillable = ['company_id', 'code', 'name', 'description'];
    protected $fillable = [
        'company_id', 'code', 'name', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
    /** @use HasFactory<TravelAdvertTypeFactory> */
    use HasFactory;

    protected $table = 'travel_advert_types';

    protected $fillable = [
        'company_id',
        'code',
        'label',
    ];

    protected $casts = [];
}
