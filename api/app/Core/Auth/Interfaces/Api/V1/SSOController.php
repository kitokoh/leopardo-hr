<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\SSO\OidcFlowService;
use App\Core\Auth\Infrastructure\Services\SSO\SSOService;
use App\Core\Auth\Infrastructure\Services\SSO\SSOValidationNotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'data' => [
                'enabled' => $sso['enabled'],
                'provider' => $sso['provider'],
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
            'entity_id' => 'nullable|string|url',
            'sso_url' => 'nullable|string|url',
            'slo_url' => 'nullable|string|url',
            'certificate' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            // OpenID Connect (issue #2231) — champs du flux authorize/callback.
            'issuer' => 'nullable|string|url',
            'authorize_url' => 'nullable|string|url',
            'token_url' => 'nullable|string|url',
            'jwks_uri' => 'nullable|string|url',
            'redirect_uri' => 'nullable|string|url',
            'scopes' => 'nullable|string|max:255',
        ]);

        $config = $this->ssoService->configureSSO(
            $actor->company_id,
            $validated['provider'],
            $validated,
        );

        return response()->json([
            'data' => $config->toArray(),
            'message' => 'SSO configure avec succes.',
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
            'message' => 'SSO desactive.',
        ]);
    }

    public function samlCallback(Request $request, string $companyId): JsonResponse
    {
        $samlResponse = $request->input('SAMLResponse', '');

        if (empty($samlResponse)) {
            return response()->json(['error' => 'SAMLResponse manquant.'], 400);
        }

        try {
            $result = $this->ssoService->handleSAMLResponse($companyId, $samlResponse);

            return response()->json([
                'data' => $result,
                'message' => 'SAML assertion recue.',
            ]);
        } catch (SSOValidationNotImplementedException $e) {
            return response()->json(['error' => $e->getMessage()], 501);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
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
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function oidcCallback(Request $request, string $companyId): JsonResponse
    {
        $tokenData = $request->only(['code', 'state', 'id_token']);

        if (empty($tokenData['code']) && empty($tokenData['id_token'])) {
            return response()->json(['error' => 'Code ou id_token manquant.'], 400);
        }

        try {
            $result = $this->oidcFlowService->complete($companyId, $tokenData);

            return response()->json([
                'data' => $result,
                'message' => 'Connexion OIDC réussie.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
