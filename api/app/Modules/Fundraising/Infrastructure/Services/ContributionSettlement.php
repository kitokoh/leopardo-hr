<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Domain\Enums\ContributionStatus;
use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraiserPublicLink;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Règlement d'une contribution (verticale FUNDRAISING — spec §4.3).
 *
 * Point UNIQUE de comptabilisation : passage `pending → completed` +
 * crédit des compteurs dénormalisés de la cagnotte + auto-complétion à
 * l'objectif, le tout dans UNE transaction avec verrous ligne
 * (`lockForUpdate`). Garanties :
 *
 * - une contribution déjà `completed` n'est JAMAIS re-créditée (garde de
 *   statut sous verrou) ;
 * - le compteur n'est jamais recalculé en lecture (incrément atomique) ;
 * - utilisé par ApplyPaymentUpdate (webhook / verify) et
 *   ConfirmManualContribution (responsable) — même invariant partout.
 *
 * S'exécute dans le contexte du tenant propriétaire (withinTenant ou
 * middleware tenant) — jamais sur la surface publique brute.
 */
final class ContributionSettlement
{
    /**
     * Marque la contribution payée et crédite la cagnotte (idempotent).
     *
     * @return bool true si le crédit a été appliqué, false si la
     *              contribution était déjà soldée (double livraison)
     */
    public function settle(FundraisingContribution $contribution, ?CarbonImmutable $paidAt = null): bool
    {
        return DB::transaction(function () use ($contribution, $paidAt): bool {
            /** @var FundraisingContribution|null $locked */
            $locked = FundraisingContribution::query()
                ->whereKey($contribution->id)
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof FundraisingContribution) {
                return false;
            }

            if ($locked->status !== ContributionStatus::PENDING) {
                // Déjà soldée (ou échouée/remboursée) : jamais de second crédit.
                return false;
            }

            $locked->status = ContributionStatus::COMPLETED;
            $locked->paid_at = $paidAt ?? CarbonImmutable::now();
            $locked->save();

            /** @var Fundraiser|null $fundraiser */
            $fundraiser = Fundraiser::query()
                ->whereKey($locked->fundraiser_id)
                ->lockForUpdate()
                ->first();

            if ($fundraiser instanceof Fundraiser) {
                $fundraiser->collected_amount = (string) round(
                    (float) $fundraiser->collected_amount + (float) $locked->amount,
                    2
                );
                $fundraiser->contributions_count = $fundraiser->contributions_count + 1;

                // Auto-complétion à l'objectif (la collecte reste possible
                // jusqu'à `closed` — décision produit façon GoFundMe, spec §3.1).
                if (
                    $fundraiser->status === FundraiserStatus::ACTIVE
                    && $fundraiser->goal_amount !== null
                    && (float) $fundraiser->collected_amount >= (float) $fundraiser->goal_amount
                ) {
                    $fundraiser->status = FundraiserStatus::COMPLETED;
                    $this->syncPublicLinkStatus($fundraiser);
                }

                $fundraiser->save();
            }

            return true;
        });
    }

    /**
     * Marque la contribution échouée (idempotent — déjà soldée ⇒ inchangé).
     */
    public function fail(FundraisingContribution $contribution): bool
    {
        return DB::transaction(function () use ($contribution): bool {
            /** @var FundraisingContribution|null $locked */
            $locked = FundraisingContribution::query()
                ->whereKey($contribution->id)
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof FundraisingContribution) {
                return false;
            }

            if ($locked->status !== ContributionStatus::PENDING) {
                return false;
            }

            $locked->status = ContributionStatus::FAILED;
            $locked->save();

            return true;
        });
    }

    /**
     * Statut miroir de l'annuaire public (schema public) — best effort :
     * l'annuaire est reconstruit aux prochaines transitions de toute façon.
     */
    private function syncPublicLinkStatus(Fundraiser $fundraiser): void
    {
        FundraiserPublicLink::query()
            ->where('slug', $fundraiser->slug)
            ->update(['status' => $fundraiser->status->value]);
    }
}
