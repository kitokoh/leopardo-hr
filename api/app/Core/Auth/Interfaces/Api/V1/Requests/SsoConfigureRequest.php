<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use App\Rules\PublicEndpointUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la configuration SSO (SAML/OIDC).
 *
 * Issue #3318 : les URLs d'endpoints IdP sont vérifiées par le garde
 * anti-SSRF PublicEndpointUrl (https uniquement, pas d'IP privée/locale).
 * L'autorisation exige le manager principal (défense en profondeur : le
 * middleware api.manager:principal et le contrôleur vérifient déjà).
 */
class SsoConfigureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null
            && method_exists($actor, 'hasManagerRole')
            && $actor->hasManagerRole('principal');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $endpoint = ['nullable', 'string', 'max:2048', new PublicEndpointUrl()];

        return [
            'provider' => 'required|string|in:saml,oidc',
            'entity_id' => 'nullable|string|max:2048',
            'sso_url' => $endpoint,
            'slo_url' => $endpoint,
            'certificate' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            // OpenID Connect (issue #2231) — champs du flux authorize/callback.
            'issuer' => $endpoint,
            'authorize_url' => $endpoint,
            'token_url' => $endpoint,
            'jwks_uri' => $endpoint,
            'redirect_uri' => $endpoint,
            'scopes' => 'nullable|string|max:255',
        ];
    }
}
