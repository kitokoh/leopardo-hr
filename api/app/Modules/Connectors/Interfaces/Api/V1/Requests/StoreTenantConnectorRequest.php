<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #9848 (BC-14 INTEGRATION) — création d'une connexion tenant.
 *
 * Même décision que côté plateforme : l'enveloppe est validée ici, le SCHÉMA du
 * connecteur (champs requis, types, options) l'est par
 * `ConnectorSchemaValidator`, qui dépend du `catalog_key`.
 */
class StoreTenantConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'catalog_key' => ['required', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:120'],
            'config' => ['sometimes', 'array'],
            'secrets' => ['sometimes', 'array'],
        ];
    }
}
