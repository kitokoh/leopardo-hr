<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Verrouillage d'une journée de pointage (issue #5265).
 *
 * L'employé cible est restreint à l'entreprise du manager appelant
 * (fail-closed cross-tenant : un employé d'un autre tenant est introuvable).
 */
class StoreDayClosureRequest extends FormRequest
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
        /** @var \App\Core\Auth\Domain\Models\Employee|null $user */
        $user = $this->user();

        return [
            'employee_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $user?->company_id),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'Employé introuvable dans votre entreprise.',
            'date.date_format' => 'La date doit être au format YYYY-MM-DD.',
        ];
    }
}
