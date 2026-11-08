<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Requests;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Enums\LibraryDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une ressource de la bibliothèque (surface tenant).
 *
 * Le statut n'est jamais fourni par le client : toute ressource naît
 * `draft` (aucune publication automatique). Le prix est en minor units
 * (entier ≥ 0) + devise ISO 4217 — jamais de flottants.
 */
class StoreLibraryResourceRequest extends FormRequest
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
            'slug' => ['nullable', 'alpha_dash', 'max:210'],
            'description' => ['nullable', 'string', 'max:20000'],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer'],
            'author_id' => ['nullable', 'integer'],
            'collection_id' => ['nullable', 'integer'],
            'document_type' => ['nullable', Rule::in($this->enumValues(LibraryDocumentType::cases()))],
            'language' => ['nullable', 'string', 'size:2'],
            'access_model' => ['nullable', Rule::in($this->enumValues(LibraryAccessModel::cases()))],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'publisher' => ['nullable', 'string', 'max:150'],
            'published_year' => ['nullable', 'integer', 'min:-3000', 'max:2100'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'license_id' => ['nullable', 'string', 'max:60'],
            'rights_holder' => ['nullable', 'string', 'max:200'],
            'allow_download' => ['nullable', 'boolean'],
            'allow_redistribution' => ['nullable', 'boolean'],
            'geo_restrictions' => ['nullable', 'array'],
            'geo_restrictions.*' => ['string', 'size:2'],
            'file_key' => ['nullable', 'string', 'max:300'],
            'file_format' => ['nullable', 'string', 'max:20'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
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
