<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Requests;

use App\Modules\Connectors\Domain\Models\PlatformConnectorConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #9847 (BC-14 INTEGRATION) — changement de statut d'une connexion plateforme.
 *
 * Le statut est un champ FERMÉ : `Rule::in` sur la liste portée par le modèle,
 * pour qu'ajouter un statut ne demande qu'une modification du modèle.
 */
class UpdatePlatformConnectorStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(PlatformConnectorConnection::STATUSES)],
        ];
    }
}
