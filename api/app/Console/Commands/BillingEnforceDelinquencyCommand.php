<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * billing:enforce-delinquency — Politique EXPLICITE de recouvrement des
 * tenants en défaut (DEP-BC21 #5897/#6248, backlog BC-21).
 *
 * Deux phases, appliquées de façon idempotente (rejouable) :
 *   1. `active` → `past_due` si la période courante est expirée OU si la
 *      dernière facture en cours (sent/overdue) a dépassé sa
 *      `due_date` ;
 *   2. `past_due` au-delà du délai de grâce (--grace-days, défaut 7 j) →
 *      `expired`. La grâce est calculée sur la `due_date` de la dernière
 *      facture impayée si elle existe, sinon sur `current_period_end`.
 *
 * L'enforcement opérationnel de l'accès est porté par `companies.status`
 * (active/suspended), distinct de l'état de la souscription. La récupération
 * (paiement) passe par `transitionTo(Active)`.
 *
 * Usage :
 *   php artisan billing:enforce-delinquency                # grâce 7 jours
 *   php artisan billing:enforce-delinquency --grace-days=14
 */
class BillingEnforceDelinquencyCommand extends Command
{
    protected $signature = 'billing:enforce-delinquency
        {--grace-days=7 : délai de grâce avant expiration (défaut 7)}';

    protected $description = 'Applique la politique de recouvrement : actif à période expirée ou facture en retard → past_due, past_due hors grâce → expired (l\'enforcement d\'accès reste porté par companies.status).';

    public function handle(): int
    {
        $graceDays = max(0, (int) $this->option('grace-days'));
        $cutoff = now()->subDays($graceDays);

        // ── Phase 1 : active → past_due ──────────────────────────────────────
        $pastDue = 0;
        $actives = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->get();

        foreach ($actives as $subscription) {
            /** @var Subscription $subscription */
            $periodExpired = $subscription->current_period_end !== null
                && $subscription->current_period_end->lt(now());

            $invoiceDue = $this->latestOutstandingInvoiceDueAt($subscription);
            $invoiceLate = $invoiceDue !== null && $invoiceDue->lt(now());

            if ($periodExpired || $invoiceLate) {
                $subscription->transitionTo(SubscriptionStatus::PastDue);
                $pastDue++;
            }
        }

        // ── Phase 2 : past_due hors délai de grâce → expired ─────────────────
        $expired = 0;
        $overGrace = Subscription::query()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->get();

        foreach ($overGrace as $subscription) {
            /** @var Subscription $subscription */
            $graceReference = $this->graceReference($subscription);

            if ($graceReference !== null && $graceReference->lt($cutoff)) {
                $subscription->transitionTo(SubscriptionStatus::Expired);
                $expired++;
            }
        }

        $this->info("[billing:enforce-delinquency] {$pastDue} abonnement(s) passé(s) en past_due, {$expired} expiré(s) (grâce {$graceDays} j).");

        return self::SUCCESS;
    }

    /**
     * Date d'échéance de la dernière facture impayée (sent/overdue).
     */
    private function latestOutstandingInvoiceDueAt(Subscription $subscription): ?Carbon
    {
        $dueDate = $subscription->invoices()
            ->whereIn('status', [
                InvoiceStatus::Sent->value,
                InvoiceStatus::Overdue->value,
            ])
            ->orderByDesc('due_date')
            ->value('due_date');

        // `value()` applique le cast `date` du modèle → Carbon (pas une string).
        if ($dueDate instanceof Carbon) {
            return $dueDate;
        }

        if (! is_string($dueDate) || $dueDate === '') {
            return null;
        }

        return Carbon::parse($dueDate);
    }

    /**
     * Référence de grâce : due_date de la dernière facture impayée si elle
     * existe, sinon current_period_end de l'abonnement.
     */
    private function graceReference(Subscription $subscription): ?Carbon
    {
        return $this->latestOutstandingInvoiceDueAt($subscription) ?? $subscription->current_period_end;
    }
}
