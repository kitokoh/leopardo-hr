<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #9848 (BC-14 INTEGRATION) — mise à jour d'une connexion tenant.
 *
 * Tous les champs sont `sometimes` : un PATCH partiel ne doit pas échouer sur un
 * champ requis déjà présent en base.
 */
class UpdateTenantConnectorRequest extends FormRequest
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
        ];
    }
}
