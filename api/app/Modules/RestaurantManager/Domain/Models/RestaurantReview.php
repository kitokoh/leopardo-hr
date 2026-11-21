<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantReviewStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis client public (RESTO-902, issue #7747).
 *
 * Un avis est lié à une commande servie/livrée par sa `order_reference`
 * (`RST-` + aléatoire — non énumérable, même stockage que RESTO-805) :
 * l'unicité (tenant, order_reference) garantit UN SEUL avis par commande.
 * Modération : `pending` (défaut) → `published` | `rejected` par un gérant
 * de la branche. Seuls les avis `published` sont exposés publiquement et
 * comptent dans la note moyenne.
 *
 * #9713 — le restaurateur peut répondre publiquement à un avis (`reply`,
 * `replied_at`, `replied_by`). La réponse n'est exposée que si l'avis est
 * `published` : un avis rejeté ne publie jamais sa réponse.
 */
class RestaurantReview extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_reference',
        'rating',
        'comment',
        'author_name',
        'status',
        'reply',
        'replied_at',
        'replied_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => RestaurantReviewStatus::class,
        'replied_at' => 'datetime',
        'replied_by' => 'integer',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
