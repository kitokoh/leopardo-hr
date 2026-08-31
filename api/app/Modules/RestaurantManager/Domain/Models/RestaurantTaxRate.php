<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantTaxRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Taux de TVA applicable aux produits — RESTO-203, issue #6168.
 *
 * `rate_bps` exprime le taux en points de base (ex. 1900 = 19 %) ; `is_default`
 * désigne le taux appliqué par défaut aux nouveaux produits.
 */
class RestaurantTaxRate extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantTaxRateFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'rate_bps',
        'is_default',
        'status',
    ];

    protected $casts = [
        'rate_bps' => 'integer',
        'is_default' => 'boolean',
        'status' => RestaurantRecordStatus::class,
    ];
}
