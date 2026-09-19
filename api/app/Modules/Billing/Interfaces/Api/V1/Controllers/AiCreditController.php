<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\Models\AiCreditLedger;
use App\Modules\Billing\Infrastructure\Services\AiCreditService;
use App\Modules\Billing\Infrastructure\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Crédits IA achetables (#7764, spec MISSION_ESPACE_CLIENT §3.4).
 *
 * Surface tenant `principal` only (même groupe RBAC que /billing/*) :
 *   - GET  /billing/ai-credits          → solde + packs + historique paginé ;
 *   - POST /billing/ai-credits/checkout → session Stripe `mode=payment`
 *     one-shot (ou crédit immédiat en mode sandbox SANDBOX_CHECKOUT, cohérent
 *     avec le checkout abonnement sandbox du front — #2628 : le sandbox est un
 *     opt-in EXPLICITE, jamais déduit d'une clé Stripe absente).
 *
 * L'achat est strictement FACULTATIF : aucun blocage d'abonnement ni de
 * module si le tenant n'achète jamais de crédits.
 */
class AiCreditController extends Controller
{
    public function __construct(
        private readonly AiCreditService $aiCreditService,
        private readonly StripeService $stripeService,
    ) {}

    /**
     * GET /billing/ai-credits
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = strval($user->company_id);

        $history = AiCreditLedger::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return new JsonResponse([
            'data' => [
                'balance' => $this->aiCreditService->balance($companyId),
                'packs' => $this->aiCreditService->packs(),
                'packs_version' => AiCreditService::PACKS_VERSION,
                'history' => $history->items(),
            ],
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    /**
     * POST /billing/ai-credits/checkout
     *
     * Achat one-shot d'un pack de tokens IA (mode=payment).
     */
    public function checkout(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $company = Company::findOrFail($user->company_id);

        $validated = $request->validate([
            'pack' => ['required', Rule::in(array_keys(AiCreditService::PACKS))],
            'success_url' => 'required|url|max:500',
            'cancel_url' => 'required|url|max:500',
        ]);

        $pack = strval($validated['pack']);
        $packDefinition = $this->aiCreditService->pack($pack);
        if ($packDefinition === null) {
            // Défensif : déjà garanti par Rule::in ci-dessus.
            abort(422);
        }
        $tokens = $packDefinition['tokens'];
        $amountCents = $packDefinition['price_eur_cents'];

        // ── Mode sandbox (opt-in explicite, dev/staging) ─────────────────
        // Cohérent avec SANDBOX_CHECKOUT du front (#2628) : paiement simulé,
        // crédit immédiat du ledger avec une référence sandbox idempotente,
        // redirection vers success_url avec les mêmes marqueurs que le funnel
        // abonnement (`sandbox=1&session_id=...`).
        if (config('billing.sandbox_checkout')) {
            $sessionId = 'sandbox_ai_'.Str::random(24);
            $this->aiCreditService->credit(strval($company->id), $tokens, $sessionId, (int) $user->id);

            $successUrl = strval($validated['success_url']);
            $separator = str_contains($successUrl, '?') ? '&' : '?';

            return new JsonResponse([
                'data' => [
                    'checkout_url' => $successUrl.$separator.http_build_query([
                        'sandbox' => '1',
                        'session_id' => $sessionId,
                        'pack' => $pack,
                        'tokens' => $tokens,
                    ]),
                    'session_id' => $sessionId,
                    'sandbox' => true,
                ],
            ]);
        }

        if (! config('services.stripe.secret')) {
            return new JsonResponse([
                'error' => 'STRIPE_NOT_CONFIGURED',
                'message' => __('errors.STRIPE_NOT_CONFIGURED'),
            ], 503);
        }

        try {
            $session = $this->stripeService->createCreditCheckoutSession(
                company: $company,
                pack: $pack,
                tokens: $tokens,
                amountCents: $amountCents,
                successUrl: strval($validated['success_url']),
                cancelUrl: strval($validated['cancel_url']),
            );

            return new JsonResponse([
                'data' => [
                    'checkout_url' => $session['url'],
                    'session_id' => $session['session_id'],
                    'sandbox' => false,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AiCredit: failed to create checkout session', [
                'company_id' => $company->id,
                'pack' => $pack,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'CHECKOUT_FAILED',
                'message' => __('errors.PAYMENT_SESSION_FAILED'),
            ], 500);
        }
    }
}
