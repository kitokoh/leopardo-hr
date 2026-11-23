<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #9847 (BC-14 INTEGRATION) — mise à jour d'une connexion plateforme.
 *
 * Tous les champs sont `sometimes` : un PATCH partiel ne doit pas être refusé
 * parce qu'un champ requis n'est pas renvoyé (il est déjà en base). La fusion
 * avec l'existant puis la validation de schéma se font dans le contrôleur.
 */
class UpdatePlatformConnectorRequest extends FormRequest
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
            'label' => ['sometimes', 'string', 'max:120'],
            'config' => ['sometimes', 'array'],
            'secrets' => ['sometimes', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
