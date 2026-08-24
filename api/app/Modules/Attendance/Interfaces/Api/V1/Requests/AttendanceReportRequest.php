<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rapport de pointage par période (issue #5268).
 *
 * period=day|week|month (défaut month — rétro-compatible) ;
 * ancres : date (Y-m-d), week (Y-m-d — n'importe quel jour de la semaine),
 * month (Y-m) ; filtres : department_id, employee_id ; format json|csv|pdf.
 */
class AttendanceReportRequest extends FormRequest
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
            'period' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'week' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'format' => ['nullable', Rule::in(['json', 'csv', 'pdf'])],
        ];
    }
}
