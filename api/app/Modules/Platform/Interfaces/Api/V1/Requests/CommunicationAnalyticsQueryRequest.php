<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Analytique communication plateforme (fenêtre en jours).
 */
class CommunicationAnalyticsQueryRequest extends FormRequest
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
        return ['days' => ['sometimes', 'integer', 'min:1', 'max:90']];
    }
}
