<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour des modules d'une société depuis la console plateforme
 * (PUT /api/v1/platform/companies/{company}/features).
 *
 * `features` est un dictionnaire `module => booléen`. Les clés inconnues sont
 * ignorées par le contrôleur, qui ne retient que les modules du registre.
 */
class UpdateCompanyFeaturesRequest extends FormRequest
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
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ];
    }
}
