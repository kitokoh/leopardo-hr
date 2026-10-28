<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantSupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fournisseur d'ingrédients (RESTO-205, issue #6170).
 *
 * Les coordonnées (téléphone, email, adresse) sont optionnelles ; le nom
 * est indexé par tenant pour la recherche de bons de commande.
 */
class RestaurantSupplier extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantSupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'contact_phone',
        'email',
        'address',
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

    /**
     * @return HasMany<RestaurantPurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(RestaurantPurchaseOrder::class, 'supplier_id');
    }
}
