<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\AI;

use Illuminate\Foundation\Http\FormRequest;

class WeeklyReportAIWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week_start' => 'nullable|date',
        ];
    }
}
