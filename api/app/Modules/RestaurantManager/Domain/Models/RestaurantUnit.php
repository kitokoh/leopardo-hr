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

    /**
     * Valeurs par defaut alignees sur le schema SQL.
     *
     * Eloquent ne lit JAMAIS les `default()` SQL : juste apres `save()`,
     * l'attribut vaut `null` en memoire tant que la ligne n'a pas ete
     * relue. Les Resources faisaient `$this->status->value` sur ce `null`
     * et repondaient 500 sur toute creation (cf. #8396, meme classe de
     * bug que MarketingAsset).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected $casts = [
        'status' => RestaurantRecordStatus::class,
    ];
}
