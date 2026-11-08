<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Requests;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Enums\LibraryDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une ressource de la bibliothèque (surface tenant).
 *
 * Tous les champs sont optionnels (`sometimes`). Le statut n'est jamais
 * modifiable ici : il passe par les endpoints dédiés publish/unpublish/archive.
 */
class UpdateLibraryResourceRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:200'],
            'slug' => ['sometimes', 'alpha_dash', 'max:210'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'author_id' => ['sometimes', 'nullable', 'integer'],
            'collection_id' => ['sometimes', 'nullable', 'integer'],
            'document_type' => ['sometimes', Rule::in($this->enumValues(LibraryDocumentType::cases()))],
            'language' => ['sometimes', 'string', 'size:2'],
            'access_model' => ['sometimes', Rule::in($this->enumValues(LibraryAccessModel::cases()))],
            'price_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'publisher' => ['sometimes', 'nullable', 'string', 'max:150'],
            'published_year' => ['sometimes', 'nullable', 'integer', 'min:-3000', 'max:2100'],
            'pages' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'license_id' => ['sometimes', 'string', 'max:60'],
            'rights_holder' => ['sometimes', 'nullable', 'string', 'max:200'],
            'allow_download' => ['sometimes', 'boolean'],
            'allow_redistribution' => ['sometimes', 'boolean'],
            'geo_restrictions' => ['sometimes', 'nullable', 'array'],
            'geo_restrictions.*' => ['string', 'size:2'],
            'file_key' => ['sometimes', 'nullable', 'string', 'max:300'],
            'file_format' => ['sometimes', 'nullable', 'string', 'max:20'],
            'file_size' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'featured' => ['sometimes', 'boolean'],
            'is_new' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, string>
     */
    private function enumValues(array $cases): array
    {
        return array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);
    }
}
