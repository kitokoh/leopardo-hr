<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\DTOs\GatewayPaymentUpdate;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Domain\Models\FundraisingPaymentEvent;
use App\Modules\Fundraising\Infrastructure\Services\ContributionSettlement;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;

/**
 * Application d'une mise à jour de paiement (webhook vérifié ou
 * vérification active) — verticale FUNDRAISING, spec §4.3.
 *
 * Double garde d'idempotence :
 * 1. `(provider, event_id)` déjà journalisé ⇒ `duplicate` (200 côté
 *    webhook, aucun retraitement) — la contrainte unique rattrape toute
 *    course (QueryException → duplicate) ;
 * 2. `ContributionSettlement` refuse tout second crédit d'une contribution
 *    déjà soldée (garde de statut sous verrou ligne).
 *
 * S'exécute DANS le contexte tenant propriétaire (la résolution
 * annuaire → withinTenant est faite par le contrôleur webhook).
 */
final class ApplyPaymentUpdateAction
{
    public function __construct(
        private readonly ContributionSettlement $settlement,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{status: 'applied'|'duplicate'|'ignored', contribution: FundraisingContribution|null}
     */
    public function execute(string $provider, GatewayPaymentUpdate $update): array
    {
        $alreadyProcessed = FundraisingPaymentEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $update->eventId)
            ->exists();

        if ($alreadyProcessed) {
            return ['status' => 'duplicate', 'contribution' => null];
        }

        /** @var FundraisingContribution|null $contribution */
        $contribution = FundraisingContribution::query()
            ->where('provider', $provider)
            ->where('provider_reference', $update->providerReference)
            ->first();

        // Repli : certains événements provider (ex. Stripe
        // `payment_intent.payment_failed`) portent la RÉFÉRENCE PUBLIQUE de
        // la contribution (FC-…) plutôt que la référence provider (session).
        if (! $contribution instanceof FundraisingContribution) {
            $contribution = FundraisingContribution::query()
                ->where('provider', $provider)
                ->where('reference', $update->providerReference)
                ->first();
        }

        if (! $contribution instanceof FundraisingContribution) {
            $this->logger->warning('Fundraising: payment update for unknown contribution', [
                'provider' => $provider,
                'provider_reference' => $update->providerReference,
            ]);

            return ['status' => 'ignored', 'contribution' => null];
        }

        if ($update->paid) {
            $paidAt = $update->paidAt !== null ? CarbonImmutable::parse($update->paidAt) : null;
            $applied = $this->settlement->settle($contribution, $paidAt);
        } else {
            $applied = $this->settlement->fail($contribution);
        }

        $this->journal($provider, $update, $contribution);

        return [
            'status' => $applied ? 'applied' : 'duplicate',
            'contribution' => $contribution->refresh(),
        ];
    }

    /**
     * Journalisation d'audit + idempotence. Une violation de l'unicité
     * `(provider, event_id)` = livraison concurrente déjà traitée : on
     * l'absorbe sans bruit (la garde métier a déjà tranché).
     */
    private function journal(string $provider, GatewayPaymentUpdate $update, FundraisingContribution $contribution): void
    {
        try {
            FundraisingPaymentEvent::query()->create([
                'company_id' => $contribution->company_id,
                'provider' => $provider,
                'event_id' => $update->eventId,
                'contribution_id' => $contribution->id,
                'payload' => null,
                'processed_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $this->logger->info('Fundraising: duplicate payment event absorbed', [
                'provider' => $provider,
                'event_id' => $update->eventId,
            ]);
        }
    }
}
