<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\MarketplaceReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Avis verifie d'un acheteur marketplace (BC-17 RETAIL, #7814).
 *
 * Table centrale (schema public). Un avis (note 1..5 + commentaire) par
 * (buyer, commande, produit), autorise UNIQUEMENT si la commande du buyer
 * contient le produit ET est `delivered` (avis verifie post-livraison —
 * regle appliquee par RetailBuyerReviewService). Moderation
 * `pending|approved|rejected` (auto-approve v1) : seuls les avis
 * `approved` sont publics.
 *
 * @property int $id
 * @property int $buyer_id
 * @property string $company_id
 * @property int $product_id
 * @property int $order_id
 * @property int $rating
 * @property string|null $comment
 * @property MarketplaceReviewStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class MarketplaceReview extends Model
{
    protected $table = 'marketplace_reviews';

    /** @var list<string> */
    protected $fillable = [
        'buyer_id',
        'company_id',
        'product_id',
        'order_id',
        'rating',
        'comment',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buyer_id' => 'integer',
            'product_id' => 'integer',
            'order_id' => 'integer',
            'rating' => 'integer',
            'status' => MarketplaceReviewStatus::class,
        ];
    }
}
