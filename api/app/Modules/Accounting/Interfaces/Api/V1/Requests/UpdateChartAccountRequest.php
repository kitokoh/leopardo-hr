<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'un compte du plan comptable — issue #5422.
 * Le code reste normalisé par le contrôleur (chiffres uniquement).
 */
class UpdateChartAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'label' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', AccountType::values())],
            'class' => ['sometimes', 'integer', 'between:1,8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
