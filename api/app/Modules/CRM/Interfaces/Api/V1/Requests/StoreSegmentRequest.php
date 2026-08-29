<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Support\SegmentDefinitionValidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un segment CRM — Issue #5723.
 *
 * La validation stricte de la définition (grammaire allowlistée, aucun SQL
 * utilisateur) est portée par `SegmentDefinitionValidator` appelé dans le
 * service — le FormRequest valide le contour (opérateur, taille) et renvoie
 * les erreurs de grammaire en 422.
 */
class StoreSegmentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'definition' => ['required', 'array'],
            'definition.operator' => ['required', 'string', 'in:and,or'],
            'definition.conditions' => ['required', 'array', 'min:1', 'max:'.SegmentDefinitionValidator::MAX_CONDITIONS],
            'is_active' => ['prohibited'],
            'id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}
