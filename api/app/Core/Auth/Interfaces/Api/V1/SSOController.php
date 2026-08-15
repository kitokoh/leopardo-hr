<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\SSO\SSOService;
use App\Core\Auth\Infrastructure\Services\SSO\SSOValidationNotImplementedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
                'message' => 'SAML assertion recue.',
            ]);
        } catch (SSOValidationNotImplementedException $e) {
            return response()->json(['error' => $e->getMessage()], 501);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /sso/oidc/{companyId}/authorize — redirige vers l'IdP (QA #2231).
     */
    public function oidcAuthorize(Request $request, string $companyId): RedirectResponse|JsonResponse
    {
        try {
            $redirectUri = $request->input('redirect_uri')
                ?? url('/api/v1/sso/oidc/'.$companyId.'/callback');

            $result = $this->ssoService->buildOidcAuthorizeUrl($companyId, (string) $redirectUri);

            return redirect()->away($result['url']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function oidcCallback(Request $request, string $companyId): JsonResponse
    {
        $code = (string) $request->input('code', '');
        $state = (string) $request->input('state', '');
        $idToken = (string) $request->input('id_token', '');

        if ($code === '' && $idToken === '') {
            return response()->json(['error' => 'Code ou id_token manquant.'], 400);
        }

        try {
            // Flux authorization_code : échange du code puis validation ID token.
            if ($code !== '') {
                $redirectUri = url('/api/v1/sso/oidc/'.$companyId.'/callback');
                $tokenData = $this->ssoService->exchangeOidcCode($companyId, $code, $redirectUri);
                $idToken = (string) ($tokenData['id_token'] ?? '');
                if ($idToken === '') {
                    return response()->json(['error' => 'Aucun id_token dans la réponse du token endpoint.'], 422);
                }
                $tokenData['state'] = $state;
            } else {
                $tokenData = ['id_token' => $idToken, 'state' => $state];
            }

            $result = $this->ssoService->handleOIDCCallback($companyId, $tokenData);

            /** @var Employee|null $employee */
            $employee = Employee::query()
                ->where('company_id', $companyId)
                ->where('email', $result['user_email'])
                ->first();

            if ($employee === null) {
                Log::warning('OIDC login: no employee matched', [
                    'company_id' => $companyId,
                    'email' => $result['user_email'],
                ]);

                return response()->json(['error' => 'Aucun compte Leopardo ne correspond à cet email.'], 404);
            }

            $token = $employee->createToken('oidc-sso');

            return (new EmployeeResource($employee))
                ->additional([
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'sso' => 'oidc',
                ])
                ->response();
        } catch (SSOValidationNotImplementedException $e) {
            return response()->json(['error' => $e->getMessage()], 501);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
