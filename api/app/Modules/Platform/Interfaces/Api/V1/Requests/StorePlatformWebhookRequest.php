<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'endpoint webhook plateforme (liste d'événements bornée).
 */
class StorePlatformWebhookRequest extends FormRequest
{
    public const EVENTS = ['employee.created', 'employee.updated', 'leave.approved', 'leave.rejected', 'attendance.synced', 'payroll.processed', 'payroll.validated', 'loan.disbursed', 'expense.submitted', 'expense.approved', 'webhook.test'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['company_id' => ['required', 'uuid', 'exists:companies,id'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:' . implode(',', self::EVENTS)],
            'active' => ['sometimes', 'boolean']];
    }
}
