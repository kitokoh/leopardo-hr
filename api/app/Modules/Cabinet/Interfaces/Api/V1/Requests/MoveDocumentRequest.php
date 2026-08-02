<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveDocumentRequest extends FormRequest
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
            'folder_id' => ['nullable', 'integer', 'exists:cabinet_folders,id'],
        ];
    }
}
