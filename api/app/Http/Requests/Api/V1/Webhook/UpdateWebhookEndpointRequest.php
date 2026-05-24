<?php

namespace App\Http\Requests\Api\V1\Webhook;

use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasManagerRole('principal');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookController::AVAILABLE_EVENTS)],
            'active' => ['boolean'],
        ];
    }
}
