<?php

declare(strict_types=1);

namespace App\Core\Feature\Interfaces\Api\V1\Requests;

use App\Modules\Billing\Domain\Enums\PlanCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plan')) {
            try {
                $this->merge(['plan' => PlanCode::normalize((string) $this->input('plan'))->value]);
            } catch (\InvalidArgumentException) {
                // Let the canonical allow-list rule return the validation error.
            }
        }
    }

    public function rules(): array
    {
        return [
            'feature_key' => 'required|string|max:50',
            'plan' => ['required', Rule::in(PlanCode::values())],
            'enabled' => 'required|boolean',
            'limit_value' => 'nullable|integer|min:0',
        ];
    }
}
