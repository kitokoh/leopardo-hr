<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Issue #7814 — Avis MODÉRÉ d'un acheteur Leopardo Marché (produit ou
 * boutique).
 *
 * Table PLATEFORME (`market_reviews`, schéma public) : l'avis est écrit par
 * un compte acheteur plateforme et lu cross-tenant par la vitrine publique.
 * `company_id` est porté PAR VALEUR (colonne nue, sans FK ni scope tenant —
 * la table vit dans le schéma public) : il borne la MODÉRATION au vendeur
 * concerné, via MarketReviewPolicy (principal/rh du tenant).
 *
 * Cycle de vie : `pending` (création, avis vérifié post-livraison) →
 * `approved` (public) | `rejected` (terminal). Seuls les avis `approved`
 * sont exposés publiquement (fail-closed).
 *
 * @property int $id
 * @property int $customer_account_id
 * @property string $company_id
 * @property string $target_type product|seller
 * @property int|null $product_id
 * @property int $order_id
 * @property int $rating
 * @property string|null $comment
 * @property string $status pending|approved|rejected
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketReview extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_SELLER = 'seller';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'market_reviews';

    protected $fillable = [
        'customer_account_id',
        'company_id',
        'target_type',
        'product_id',
        'order_id',
        'rating',
        'comment',
        'status',
        'moderated_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'moderated_at' => 'datetime',
    ];
}
