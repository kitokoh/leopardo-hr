<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un compte du plan comptable — issue #5422.
 * Le code est normalisé (chiffres uniquement) par le contrôleur.
 */
class StoreChartAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', AccountType::values())],
            'class' => ['required', 'integer', 'between:1,8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
