<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Fundraising\Application\Actions\ApplyPaymentUpdateAction;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\FundraisingPaymentRoute;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Webhooks des providers de paiement des contributions (verticale
 * FUNDRAISING — spec §4.3/§5.1). PUBLIC, sans auth : la SEULE preuve est
 * la signature HMAC vérifiée par la passerelle (fail-closed — secret
 * absent ou signature invalide ⇒ 401 WEBHOOK_SIGNATURE_INVALID).
 *
 * Routage tenant : annuaire public `fundraising_payment_routes`
 * ((provider, provider_reference) → company) posé à l'initiation, puis
 * application dans le schema tenant via `TenantManager::withinTenant()`.
 *
 * Réponses : 200 `applied|duplicate|ignored` (jamais de 4xx pour un
 * événement inactionnable — sinon le provider re-tente indéfiniment) ;
 * 401 uniquement pour une signature invalide.
 */
final class FundraisingWebhookController extends Controller
{
    public function __construct(
        private readonly FundraisingGatewayFactory $gatewayFactory,
        private readonly ApplyPaymentUpdateAction $applyPaymentUpdate,
        private readonly TenantManager $tenantManager,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        try {
            $gateway = $this->gatewayFactory->forProvider($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $signatureHeader = (string) $request->header(
            $provider === 'stripe' ? 'Stripe-Signature' : 'X-Signature',
            ''
        );

        $payload = $gateway->verifyWebhookSignature($request->getContent(), $signatureHeader);

        if ($payload === null) {
            throw FundraisingException::webhookSignatureInvalid();
        }

        $update = $gateway->extractPayment($payload);

        if ($update === null) {
            return response()->json(['status' => 'ignored']);
        }

        /** @var FundraisingPaymentRoute|null $route */
        $route = FundraisingPaymentRoute::query()
            ->where('provider', $gateway->gatewayName())
            ->where('provider_reference', $update->providerReference)
            ->first();

        if (! $route instanceof FundraisingPaymentRoute) {
            return response()->json(['status' => 'ignored']);
        }

        /** @var Company|null $company */
        $company = Company::query()->find($route->company_id);

        if (! $company instanceof Company) {
            return response()->json(['status' => 'ignored']);
        }

        $result = $this->tenantManager->withinTenant(
            $company,
            fn (): array => $this->applyPaymentUpdate->handle($gateway->gatewayName(), $update)
        );

        return response()->json(['status' => $result['status']]);
    }
}
