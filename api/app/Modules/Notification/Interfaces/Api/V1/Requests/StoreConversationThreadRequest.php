<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Requests;

use App\Modules\Notification\Domain\Models\ConversationThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationThreadRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
            'subject_type' => ['nullable', 'string', Rule::in(ConversationThread::SUBJECT_TYPES)],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
            'recipient_id' => ['nullable', 'integer'],
            // #8746 — clé de contexte d'idempotence : deux créations avec la
            // même clé renvoient le MÊME fil au lieu d'en créer deux. Distincte
            // de `client_message_id` (#8747), qui porte sur un message.
            'context_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.:\-]+$/'],
            // #8748 — plus de liste `mimes:` ici : le type est validé sur le
            // contenu réel (finfo) par le service. `file` + `max` restent.
            'attachment' => ['nullable', 'file', 'max:5120'],
        ];
    }
}
