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
