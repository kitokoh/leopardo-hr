<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Approval;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'levels' => 'sometimes|array|min:1',
            'auto_approve_below' => 'nullable|numeric|min:0',
            'escalation_hours' => 'nullable|integer|min:1',
            'active' => 'boolean',
        ];
    }
}
