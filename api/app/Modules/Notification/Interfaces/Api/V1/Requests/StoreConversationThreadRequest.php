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
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,txt,csv,png,jpg,jpeg,webp'],
        ];
    }
}
