<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Préparation du rapport paie IA (AIWorkflow).
 */
class PreparePayrollReportRequest extends FormRequest
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
        return ['period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after:period_start']];
    }
}
