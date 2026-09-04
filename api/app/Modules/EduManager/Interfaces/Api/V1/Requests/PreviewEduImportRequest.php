<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Modules\EduManager\Domain\Models\EduImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Preview d'un import CSV (EDU-017, #5833).
 */
class PreviewEduImportRequest extends FormRequest
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
            'entity_type' => ['required', Rule::in(EduImport::ENTITIES)],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
