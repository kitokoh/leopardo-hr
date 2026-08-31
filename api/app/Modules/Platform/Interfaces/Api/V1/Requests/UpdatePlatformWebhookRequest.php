<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'endpoint webhook plateforme.
 */
class UpdatePlatformWebhookRequest extends FormRequest
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
        return ['url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'in:' . implode(',', self::EVENTS)],
            'active' => ['sometimes', 'boolean']];
    }
}
