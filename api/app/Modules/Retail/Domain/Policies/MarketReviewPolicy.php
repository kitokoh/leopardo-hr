<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\MarketReview;

/**
 * RBAC de la modération des avis Leopardo Marché (BC-17 RETAIL, #7814).
 *
 * Modération (approve/reject) réservée au responsable du tenant VENDEUR
 * concerné (sous-rôles `principal`/`rh`, `company_id` de l'avis = tenant de
 * l'acteur) — calque sur RetailOnlineSettingsPolicy (#7807). deny-by-default :
 * aucun rôle = refus (fail-closed, pattern Catalog #6880). La table vit dans
 * le schéma public : le bornage tenant se fait PAR VALEUR (company_id).
 */
class MarketReviewPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, MarketReview $review): bool
    {
        return $review->company_id === (string) $actor->company_id;
    }

    public function moderate(Employee $actor, MarketReview $review): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $review->company_id === (string) $actor->company_id;
    }
}
