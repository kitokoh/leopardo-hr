<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Console\Command;

/**
 * billing:enforce-delinquency — Politique EXPLICITE de recouvrement des
 * tenants en défaut (DEP-BC21 #5897, backlog BC-21).
 *
 * Deux phases, appliquées de façon idempotente (rejouable) :
 *   1. `active` dont la période courante est expirée → `past_due` ;
 *   2. `past_due` au-delà du délai de grâce (--grace-days, défaut 7 j) →
 *      `expired` (souscription en défaut — l'enforcement opérationnel de
 *      l'accès est porté par `companies.status`).
 *
 * La récupération (paiement) passe par `transitionTo(Active)` : un tenant
 * `expired` redevient `active` sans créer d'état illégal.
 *
 * Usage :
 *   php artisan billing:enforce-delinquency                # grâce 7 jours
 *   php artisan billing:enforce-delinquency --grace-days=14
 */
class BillingEnforceDelinquencyCommand extends Command
{
    protected $signature = 'billing:enforce-delinquency
        {--grace-days=7 : délai de grâce avant suspension (défaut 7)}';

    protected $description = 'Applique la politique de recouvrement : actif expiré → past_due, past_due hors grâce → suspended.';

    public function handle(): int
    {
        $graceDays = max(0, (int) $this->option('grace-days'));

        // Phase 1 : période expirée → past_due.
        $expired = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->get();

        $pastDue = 0;
        foreach ($expired as $subscription) {
            /** @var Subscription $subscription */
            $subscription->transitionTo(SubscriptionStatus::PastDue);
            $pastDue++;
        }

        // Phase 2 : past_due hors délai de grâce → expired.
        $cutoff = now()->subDays($graceDays);
        $overGrace = Subscription::query()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $cutoff)
            ->get();

        $expired = 0;
        foreach ($overGrace as $subscription) {
            /** @var Subscription $subscription */
            $subscription->transitionTo(SubscriptionStatus::Expired);
            $expired++;
        }

        $this->info("[billing:enforce-delinquency] {$pastDue} abonnement(s) passé(s) en past_due, {$expired} expiré(s) (grâce {$graceDays} j).");

        return self::SUCCESS;
    }
}
