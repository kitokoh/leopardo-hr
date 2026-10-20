<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Requests;

use App\Modules\Notification\Domain\Models\ConversationReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8750 [BOS-CHAT] — signalement d'un message ou d'un compte.
 *
 * Une cible exactement : `message_id` (message d'un fil auquel l'acteur
 * participe) OU `employee_id` (compte). Les deux à la fois rendraient le
 * traitement ambigu pour le modérateur.
 */
class StoreConversationReportRequest extends FormRequest
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
        return [
            'message_id' => ['nullable', 'integer', 'min:1', 'required_without:employee_id', 'prohibits:employee_id'],
            'employee_id' => ['nullable', 'integer', 'min:1', 'required_without:message_id'],
            'reason' => ['required', 'string', Rule::in(ConversationReport::REASONS)],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message_id.required_without' => 'Indiquez le message ou l\'employé que vous signalez.',
            'employee_id.required_without' => 'Indiquez le message ou l\'employé que vous signalez.',
            'message_id.prohibits' => 'Signalez soit un message, soit un compte — pas les deux.',
        ];
    }
}
