<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\SSO\OidcFlowService;
use App\Core\Auth\Infrastructure\Services\SSO\SSOService;
use App\Core\Auth\Infrastructure\Services\SSO\SSOValidationNotImplementedException;
use App\Http\Controllers\Controller;
use App\Rules\NotPrivateUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SSOController extends Controller
{
    public function __construct(
        private readonly SSOService $ssoService,
        private readonly OidcFlowService $oidcFlowService,
    ) {}

    public function providers(): JsonResponse
    {
        return response()->json([
            'data' => $this->ssoService->getSupportedProviders(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        $sso = $this->ssoService->getCompanySSO($actor->company_id);

        // #5614 — Pour OIDC, la validation est disponible sans gate SAML_ENABLED.
        // Pour SAML, le flag config('services.saml.enabled') reste requis jusqu'à
        // l'implémentation de la librairie OneLogin (audit #1694).
        $samlGate = $sso['provider'] !== 'oidc'
            ? (bool) config('services.saml.enabled', false)
            : true;

        return response()->json([
            'data' => [
                'enabled' => $sso['enabled'],
                'provider' => $sso['provider'],
                'validation_available' => $sso['validation_available'] && $samlGate,
            ],
        ]);
    }

    public function configure(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        $validated = $request->validate([
            'provider' => 'required|string|in:saml,oidc',
            // Issue #2231 / audit SSRF : TOUS les champs URL (SAML et OIDC)
            // passent par NotPrivateUrl — le serveur peut suivre sso_url,
            // slo_url, issuer/jwks (métadonnées) et redirect_uri ; une cible
            // privée/réservée/unresolvable doit être refusée (fail-closed).
            'entity_id' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'sso_url' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'slo_url' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'certificate' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'issuer' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'authorize_url' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'token_url' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'jwks_uri' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'redirect_uri' => ['nullable', 'string', 'url', new NotPrivateUrl],
            'scopes' => 'nullable|string|max:255',
        ]);

        $config = $this->ssoService->configureSSO(
            $actor->company_id,
            $validated['provider'],
            $validated,
        );

        return response()->json([
            'data' => $config->toArray(),
            'message' => __('errors.SSO_CONFIGURED'),
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        $this->ssoService->disableSSO($actor->company_id);

        return response()->json([
            'message' => __('errors.SSO_DISABLED'),
        ]);
    }

    public function samlCallback(Request $request, string $companyId): JsonResponse
    {
        // #3890 : gate de feature explicite — le moteur de validation SAML
        // n'est pas livré ; tant que services.saml.enabled est false (défaut),
        // aucun IdP ne peut aboutir (fail-closed assumé et documenté).
        if (! (bool) config('services.saml.enabled', false)) {
            return response()->json(['error' => 'SAML_FEATURE_DISABLED'], 501);
        }

        $samlResponse = $request->input('SAMLResponse', '');

        if (empty($samlResponse)) {
            return response()->json(['error' => 'SAML_RESPONSE_MISSING', 'message' => __('errors.SAML_RESPONSE_MISSING')], 400);
        }

        try {
            $result = $this->ssoService->handleSAMLResponse($companyId, $samlResponse);

            return response()->json([
                'data' => $result,
                'message' => __('errors.SAML_ASSERTION_RECEIVED'),
            ]);
        } catch (SSOValidationNotImplementedException $e) {
            return response()->json(['error' => 'SAML_AUTH_NOT_IMPLEMENTED'], 501);
        } catch (\RuntimeException $e) {
            Log::error('sso.saml_auth_failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'SAML_AUTH_FAILED', 'message' => __('errors.SAML_AUTH_FAILED')], 422);
        }
    }

    /**
     * Issue #2231 — démarre le flux OIDC : retourne l'URL d'autorisation de
     * l'IdP (state + nonce) que le frontend doit ouvrir.
     */
    public function oidcAuthorize(Request $request, string $companyId): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->oidcFlowService->buildAuthorizeUrl($companyId),
            ]);
        } catch (\RuntimeException $e) {
            Log::error('sso.oidc_authorize_failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'OIDC_AUTHORIZE_FAILED', 'message' => __('errors.OIDC_AUTHORIZE_FAILED')], 422);
        }
    }

    public function oidcCallback(Request $request, string $companyId): JsonResponse
    {
        $tokenData = $request->only(['code', 'state', 'id_token']);

        if (empty($tokenData['code']) && empty($tokenData['id_token'])) {
            return response()->json(['error' => 'OIDC_CODE_MISSING', 'message' => __('errors.OIDC_CODE_MISSING')], 400);
        }

        try {
            $result = $this->oidcFlowService->complete($companyId, $tokenData);

            return response()->json([
                'data' => $result,
                'message' => __('errors.OIDC_LOGIN_SUCCESS'),
            ]);
        } catch (\RuntimeException $e) {
            Log::error('sso.oidc_callback_failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'OIDC_CALLBACK_FAILED', 'message' => __('errors.OIDC_CALLBACK_FAILED')], 422);
        }
    }
}
