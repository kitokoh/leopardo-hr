<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #9847 (BC-14 INTEGRATION) — création d'une connexion plateforme.
 *
 * Décision du dépôt (#9699 / #9706) : toute validation NOUVELLE passe par un
 * FormRequest dédié, règles dans `rules()`, permission dans `authorize()`.
 *
 * La validation de SCHÉMA (champs requis du connecteur, types, options de
 * `select`) n'est PAS ici : elle dépend du `catalog_key` et vit dans
 * `ConnectorSchemaValidator`, qui rend un 422 détaillé par champ. Ce FormRequest
 * ne valide que l'enveloppe de la requête.
 */
class StorePlatformConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La permission est déjà appliquée par le middleware de route
        // (`platform.permission:connectors.manage`) ; on ne la rejoue pas ici
        // pour ne pas dupliquer la source de vérité RBAC.
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
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
