<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de `GET /v1/employees/stats` (KPIs RH).
 *
 * Extrait de `EmployeeController::stats()` — la validation en ligne y violait
 * la décision « FormRequest partout » (#9699/#9706).
 */
class EmployeeStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy `viewAny` tranchée par le contrôleur
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
