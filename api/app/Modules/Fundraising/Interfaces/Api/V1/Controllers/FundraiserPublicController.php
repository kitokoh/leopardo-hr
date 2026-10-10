<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Fundraising\Application\Actions\InitiateContributionAction;
use App\Modules\Fundraising\Domain\Enums\ContributionStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraiserPublicLink;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Domain\Models\FundraisingPaymentRoute;
use App\Modules\Fundraising\Domain\Support\FundraisingFeatures;
use App\Modules\Fundraising\Interfaces\Api\V1\Requests\InitiateContributionRequest;
use App\Modules\Fundraising\Interfaces\Api\V1\Resources\FundraiserPublicResource;
use App\Modules\Fundraising\Interfaces\Api\V1\Resources\SupporterResource;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API PUBLIQUE des cagnottes (verticale FUNDRAISING — spec §5.1) : route
 * isolée SANS auth (`throttle:shop-public`), zéro donnée interne.
 *
 * Résolution du tenant : annuaire public `fundraiser_public_links`
 * (slug court → company_id), gardes fail-closed BOS-050 via
 * `PublicTenantResolver` (société suspendue/expirée ou verticale
 * `fundraising` inactive ⇒ 404 uniforme anti-énumération — argent =
 * kill switch strict, contrairement à la vitrine), puis lecture/écriture
 * dans le schema tenant via `TenantManager::withinTenant()`.
 *
 * DTOs publics dédiés (FundraiserPublicResource, SupporterResource) :
 * jamais d'id, company_id, email, téléphone ni beneficiary_contact.
 */
final class FundraiserPublicController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PublicTenantResolver $publicTenantResolver,
        private readonly InitiateContributionAction $initiateContribution,
        private readonly \App\Modules\Fundraising\Infrastructure\Services\FundraisingGatewayFactory $gatewayFactory,
        private readonly \App\Modules\Fundraising\Application\Actions\ApplyPaymentUpdateAction $applyPaymentUpdate,
    ) {}

    /**
     * Fiche publique de la cagnotte.
     */
    public function show(string $slug): JsonResponse
    {
        $company = $this->resolveCompany($slug);

        $fundraiser = $this->tenantManager->withinTenant($company, function () use ($slug): ?Fundraiser {
            /** @var Fundraiser|null $fundraiser */
            $fundraiser = Fundraiser::query()->where('slug', $slug)->first();

            if (! $fundraiser instanceof Fundraiser || ! $fundraiser->status->isPubliclyVisible()) {
                return null;
            }

            return $fundraiser;
        });

        if (! $fundraiser instanceof Fundraiser) {
            abort(404);
        }

        return (new FundraiserPublicResource($fundraiser))->response();
    }

    /**
     * Mur des soutiens (contributions `completed` uniquement, anonymat
     * respecté — spec RGPD §6).
     */
    public function supporters(Request $request, string $slug): JsonResponse
    {
        $company = $this->resolveCompany($slug);

        $contributions = $this->tenantManager->withinTenant($company, function () use ($slug, $request) {
            /** @var Fundraiser|null $fundraiser */
            $fundraiser = Fundraiser::query()->where('slug', $slug)->first();

            if (! $fundraiser instanceof Fundraiser || ! $fundraiser->status->isPubliclyVisible()) {
                return null;
            }

            return FundraisingContribution::query()
                ->where('fundraiser_id', $fundraiser->id)
                ->where('status', ContributionStatus::COMPLETED->value)
                ->latest('paid_at')
                ->paginate(min(50, max(1, (int) $request->query('per_page', 20))));
        });

        if ($contributions === null) {
            abort(404);
        }

        return SupporterResource::collection($contributions)->response();
    }

    /**
     * Initiation d'une contribution (montant libre ou suggéré, carte /
     * mobile money / manuel). Retourne les instructions de paiement et la
     * référence publique de polling.
     */
    public function contribute(InitiateContributionRequest $request, string $slug): JsonResponse
    {
        $company = $this->resolveCompany($slug);

        $result = $this->tenantManager->withinTenant(
            $company,
            function () use ($slug, $request): ?array {
                /** @var Fundraiser|null $fundraiser */
                $fundraiser = Fundraiser::query()->where('slug', $slug)->first();

                if (! $fundraiser instanceof Fundraiser) {
                    return null;
                }

                return $this->initiateContribution->execute($fundraiser, $request->validated());
            }
        );

        if ($result === null) {
            abort(404);
        }

        /** @var FundraisingContribution $contribution */
        $contribution = $result['contribution'];

        // Annuaire de routage paiement (schema PUBLIC, hors contexte
        // tenant) : webhooks + polling. Idempotent sur la référence.
        // Critique : sans cette ligne, un paiement initié ne pourra JAMAIS
        // être rapproché (webhook `ignored`) — échec = alerte immédiate.
        try {
            FundraisingPaymentRoute::query()->firstOrCreate(
                [
                    'provider' => $contribution->provider,
                    'provider_reference' => (string) $contribution->provider_reference,
                ],
                [
                    'company_id' => $contribution->company_id,
                    'contribution_reference' => $contribution->reference,
                ],
            );
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::critical(
                'Fundraising: echec d\'ecriture de la route paiement — rapprochement webhook impossible pour cette contribution',
                [
                    'reference' => $contribution->reference,
                    'provider' => $contribution->provider,
                    'provider_reference' => $contribution->provider_reference,
                    'exception' => $exception::class,
                ]
            );
        }

        /** @var \App\Modules\Fundraising\Domain\DTOs\GatewayPaymentInitiation $initiation */
        $initiation = $result['initiation'];

        return response()->json([
            'data' => [
                'reference' => $contribution->reference,
                'amount' => (float) $contribution->amount,
                'currency' => $contribution->currency,
                'payment_method' => $contribution->payment_method->value,
                'status' => $contribution->status->value,
                'payment' => $initiation->toArray(),
                'status_url' => '/api/v1/public/contributions/'.$contribution->reference,
            ],
        ], 201);
    }

    /**
     * Polling public du statut d'une contribution par sa référence (après
     * push USSD mobile money ou retour de checkout) — 404 anti-énumération.
     */
    public function contributionStatus(string $reference): JsonResponse
    {
        /** @var FundraisingPaymentRoute|null $route */
        $route = FundraisingPaymentRoute::query()
            ->where('contribution_reference', $reference)
            ->first();

        if (! $route instanceof FundraisingPaymentRoute) {
            abort(404);
        }

        /** @var Company|null $company */
        $company = Company::query()->find($route->company_id);

        if (! $company instanceof Company) {
            abort(404);
        }

        // Kill switch strict (argent) : même garde fail-closed que le reste
        // de la surface publique — verticale coupée ou société suspendue ⇒
        // 404 uniforme, JAMAIS de règlement déclenché pour un tenant coupé.
        $this->publicTenantResolver->assertAccessible(
            $company,
            FundraisingFeatures::FUNDRAISING,
        );

        $payload = $this->tenantManager->withinTenant($company, function () use ($reference): ?array {
            /** @var FundraisingContribution|null $contribution */
            $contribution = FundraisingContribution::query()
                ->where('reference', $reference)
                ->first();

            if (! $contribution instanceof FundraisingContribution) {
                return null;
            }

            // Re-conciliation active (spec §4.3) : si la contribution est
            // encore `pending`, on interroge la passerelle (mobile money
            // sandbox/production, session Stripe) — les webhooks peuvent
            // tarder ; l'événement synthétique est déterministe et
            // idempotent, jamais de double crédit.
            if ($contribution->status === \App\Modules\Fundraising\Domain\Enums\ContributionStatus::PENDING) {
                try {
                    $gateway = $this->gatewayFactory->forProvider($contribution->provider);
                    $update = $gateway->verify((string) $contribution->provider_reference);

                    if ($update !== null) {
                        $this->applyPaymentUpdate->execute($gateway->gatewayName(), $update);
                        $contribution->refresh();
                    }
                } catch (\InvalidArgumentException) {
                    // Provider inconnu (manuel…) : pas de vérification active.
                }
            }

            /** @var Fundraiser|null $fundraiser */
            $fundraiser = Fundraiser::query()->find($contribution->fundraiser_id);

            return [
                'reference' => $contribution->reference,
                'status' => $contribution->status->value,
                'paid_at' => $contribution->paid_at?->toIso8601String(),
                'fundraiser_slug' => $fundraiser?->slug,
            ];
        });

        if ($payload === null) {
            throw FundraisingException::contributionNotFound();
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * Annuaire public → société, gardes fail-closed (BOS-050) : slug
     * inconnu ⇒ 404 ; société suspendue/expirée ou verticale inactive ⇒
     * 404 uniforme (kill switch strict : surface d'encaissement).
     */
    private function resolveCompany(string $slug): Company
    {
        /** @var FundraiserPublicLink|null $link */
        $link = FundraiserPublicLink::query()->where('slug', $slug)->first();

        if (! $link instanceof FundraiserPublicLink) {
            abort(404);
        }

        /** @var Company|null $company */
        $company = Company::query()->find($link->company_id);

        if (! $company instanceof Company) {
            abort(404);
        }

        return $this->publicTenantResolver->assertAccessible(
            $company,
            FundraisingFeatures::FUNDRAISING,
        );
    }
}
