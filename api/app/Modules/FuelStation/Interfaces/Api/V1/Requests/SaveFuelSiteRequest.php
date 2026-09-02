<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Enums\FuelSiteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / remplacement d'un site opérationnel (FUEL-011, #5805).
 */
class SaveFuelSiteRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::in(FuelSiteStatus::values())],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
