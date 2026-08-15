<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Requests;

use App\Modules\Billing\Domain\Enums\PlanCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpgradeSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
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
            'plan' => ['required', Rule::in(PlanCode::values())],
            'payment_method' => 'nullable|in:stripe,chargily,bank_transfer,manual',
        ];
    }
}
