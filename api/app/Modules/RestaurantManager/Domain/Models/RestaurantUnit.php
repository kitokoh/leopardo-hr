<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Unité de mesure (kg, l, u...) — RESTO-203, issue #6168.
 *
 * Référentiel par valeur : `restaurant_ingredients.unit_code` et
 * `restaurant_product_ingredients.unit_code` pointent sur `code` (unique par tenant).
 */
class RestaurantUnit extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantUnitFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'status',
    ];

    protected $casts = [
        'status' => RestaurantRecordStatus::class,
    ];
}
