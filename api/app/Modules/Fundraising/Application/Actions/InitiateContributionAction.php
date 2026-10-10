<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\DTOs\GatewayPaymentInitiation;
use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use App\Modules\Fundraising\Domain\Enums\ContributionStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Domain\Support\ReferenceGenerator;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingGatewayFactory;

/**
 * Initiation d'une contribution publique (verticale FUNDRAISING — spec
 * §5.1) : crée la contribution `pending` puis initie le paiement via la
 * passerelle résolue par la méthode (carte / mobile money / manuel).
 *
 * Garde-fous (spec §6) :
 * - cagnotte `active` ET dans sa fenêtre de collecte, sinon
 *   FUNDRAISER_NOT_ACTIVE (la 404 anti-énumération est déjà assurée par
 *   l'annuaire public en amont) ;
 * - montant dans [min_amount, max_amount] de la cagnotte (ou défauts
 *   config) — INVALID_CONTRIBUTION_AMOUNT ;
 * - honeypot `website` : rejet silencieux côté payload, 422 côté code ;
 * - téléphone requis pour mobile money ;
 * - passerelle non configurée ⇒ 503 PAYMENT_GATEWAY_NOT_CONFIGURED
 *   (fail-closed).
 *
 * S'exécute DANS le contexte tenant propriétaire (withinTenant) : la
 * contribution est créée dans le schema tenant avec le scope company_id.
 * L'écriture de l'annuaire de routage paiement (schema public) est faite
 * par l'appelant APRÈS retour (hors contexte tenant).
 */
final class InitiateContributionAction
{
    public function __construct(
        private readonly FundraisingGatewayFactory $gatewayFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $data  payload validé (InitiateContributionRequest)
     * @return array{contribution: FundraisingContribution, initiation: GatewayPaymentInitiation}
     */
    public function execute(Fundraiser $fundraiser, array $data): array
    {
        // Honeypot anti-bot (champ leurre invisible pour les humains).
        if (! empty($data['website'])) {
            throw FundraisingException::invalidContributionAmount('requête rejetée');
        }

        if (
            ! $fundraiser->status->acceptsContributions()
            || ! $fundraiser->isWithinCollectionWindow()
        ) {
            throw FundraisingException::fundraiserNotActive();
        }

        $amount = (float) $data['amount'];
        $this->assertAmountWithinLimits($fundraiser, $amount);

        $method = ContributionMethod::from((string) $data['payment_method']);

        if ($method === ContributionMethod::MOBILE_MONEY && empty($data['contributor_phone'])) {
            throw FundraisingException::invalidContributionAmount('numéro mobile money requis');
        }

        $gateway = $this->gatewayFactory->forMethod($method);

        if (! $gateway->isConfigured()) {
            throw FundraisingException::gatewayNotConfigured($gateway->gatewayName());
        }

        /** @var FundraisingContribution $contribution */
        $contribution = FundraisingContribution::query()->create([
            'fundraiser_id' => $fundraiser->id,
            'reference' => ReferenceGenerator::contribution(),
            'amount' => $amount,
            'currency' => (string) $fundraiser->currency,
            'payment_method' => $method,
            'provider' => $gateway->gatewayName(),
            'status' => ContributionStatus::PENDING,
            'contributor_name' => $data['contributor_name'] ?? null,
            'contributor_email' => $data['contributor_email'] ?? null,
            'contributor_phone' => $data['contributor_phone'] ?? null,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'message' => $data['message'] ?? null,
        ]);

        $initiation = $gateway->initiate($contribution);

        $contribution->provider_reference = $initiation->providerReference;
        $contribution->metadata = array_filter([
            'instructions' => $initiation->instructions,
            'ussd_code' => $initiation->ussdCode,
        ]);
        $contribution->save();

        return ['contribution' => $contribution, 'initiation' => $initiation];
    }

    private function assertAmountWithinLimits(Fundraiser $fundraiser, float $amount): void
    {
        /** @var array<string, mixed> $limits */
        $limits = config('fundraising.limits', []);
        $min = $fundraiser->min_amount !== null
            ? (float) $fundraiser->min_amount
            : (float) ($limits['default_min_amount'] ?? 100);
        $max = $fundraiser->max_amount !== null
            ? (float) $fundraiser->max_amount
            : (float) ($limits['default_max_amount'] ?? 10000000);

        if ($amount < $min) {
            throw FundraisingException::invalidContributionAmount('minimum '.$min.' '.$fundraiser->currency);
        }

        if ($amount > $max) {
            throw FundraisingException::invalidContributionAmount('maximum '.$max.' '.$fundraiser->currency);
        }
    }
}
