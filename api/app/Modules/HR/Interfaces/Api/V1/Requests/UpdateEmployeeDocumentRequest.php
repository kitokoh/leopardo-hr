<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return $actor->hasManagerRole('principal', 'rh');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(EmployeeDocument::TYPES)],
            'status' => ['sometimes', Rule::in(EmployeeDocument::STATUSES)],
            'document_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
