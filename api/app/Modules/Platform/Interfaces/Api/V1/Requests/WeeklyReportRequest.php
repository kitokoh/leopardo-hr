<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rapport hebdomadaire IA (AIWorkflow).
 */
class WeeklyReportRequest extends FormRequest
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
        return ['week_start' => ['nullable', 'date']];
    }
}
