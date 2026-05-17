<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SSO;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\SSO\SSOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SSOController extends Controller
{
    public function __construct(
        private readonly SSOService $ssoService,
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
            'entity_id' => 'required|string|url',
            'sso_url' => 'required|string|url',
            'slo_url' => 'nullable|string|url',
            'certificate' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
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
                'message' => 'SAML assertion recue (stub — validation complete a implementer).',
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
            $result = $this->ssoService->handleOIDCCallback($companyId, $tokenData);

            return response()->json([
                'data' => $result,
                'message' => 'OIDC callback recu (stub — echange token a implementer).',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
