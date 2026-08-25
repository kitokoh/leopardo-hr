<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Période du tableau de bord comptable — issue #5230.
 *
 * `from`/`to` optionnels (défaut : mois courant), au format date ISO
 * (`Y-m-d`). `from > to` est accepté côté service (permuté) ; la validation
 * garantit uniquement le format.
 */
class AccountingDashboardRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
