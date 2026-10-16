<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #8533 — demande de brief de production assiste par IA.
 *
 * Le brief est un objet libre (concept, objectif, public, produit…) : le
 * vocabulaire metier vient de l'utilisateur, pas du module (regle
 * d'horizontalite du module Marketing).
 */
class SuggestProductionBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'brief' => ['required', 'array'],
            'format' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:8'],
        ];
    }
}
