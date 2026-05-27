<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Approval;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'model_type' => 'required|string|max:100',
            'levels' => 'required|array|min:1',
            'levels.*.level' => 'required|integer|min:1',
            'levels.*.approver_type' => 'required|string',
            'auto_approve_below' => 'nullable|numeric|min:0',
            'escalation_hours' => 'nullable|integer|min:1',
            'active' => 'boolean',
        ];
    }
}
