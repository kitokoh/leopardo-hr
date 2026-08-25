<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de la liste des fermetures de journée (issue #5265).
 */
class ListDayClosureRequest extends FormRequest
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
            'date' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
