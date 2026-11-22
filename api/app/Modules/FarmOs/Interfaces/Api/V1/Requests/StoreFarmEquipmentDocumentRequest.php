<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Models\FarmEquipmentDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Document associe a un materiel (#9732).
 *
 * `external_url` est un lien FOURNI par l'exploitant : Farm OS ne fabrique
 * jamais d'URL de document.
 */
final class StoreFarmEquipmentDocumentRequest extends FormRequest
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
            'document_type' => ['required', 'string', Rule::in(FarmEquipmentDocument::TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'file_id' => ['nullable', 'integer'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
