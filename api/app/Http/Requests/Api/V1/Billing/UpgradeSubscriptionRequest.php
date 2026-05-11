<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;

class UpgradeSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'plan' => 'required|in:starter,business,enterprise',
            'payment_method' => 'nullable|in:stripe,chargily,bank_transfer,manual',
        ];
    }
}
