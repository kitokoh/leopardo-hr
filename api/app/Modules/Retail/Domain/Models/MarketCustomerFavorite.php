<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Issue #7814 — Favori d'un acheteur Leopardo Marché (produit ou boutique).
 *
 * Table PLATEFORME (`market_customer_favorites`, schéma public, pas de
 * company_id — le favori appartient à l'acheteur, PAS au vendeur). Cible
 * référencée PAR VALEUR : `product_id` global (avis produit) OU
 * `seller_slug` public (boutique) — mutuellement exclusifs, validés serveur.
 * La lecture publique repasse par RetailMarketplaceService (fail-closed :
 * cible plus visible → favori non résolu).
 *
 * @property int $id
 * @property int $customer_account_id
 * @property string $target_type product|seller
 * @property int|null $product_id
 * @property string|null $seller_slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketCustomerFavorite extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_SELLER = 'seller';

    protected $table = 'market_customer_favorites';

    protected $fillable = [
        'customer_account_id',
        'target_type',
        'product_id',
        'seller_slug',
    ];
}
