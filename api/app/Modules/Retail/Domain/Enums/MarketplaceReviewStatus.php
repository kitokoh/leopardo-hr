<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Statut de moderation d'un avis marketplace (BC-17 RETAIL, #7814).
 *
 * v1 : auto-approve a la creation (`approved`) — le champ existe pour la
 * moderation v2 (backlog). Seuls les avis `approved` sont exposes
 * publiquement (GET /public/market/products/{id}/reviews et agregats
 * rating_avg / rating_count).
 */
enum MarketplaceReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
