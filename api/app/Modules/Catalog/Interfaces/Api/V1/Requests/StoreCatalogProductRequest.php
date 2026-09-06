<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un produit du catalogue B2B (BC-28 CATALOG, #6881).
 *
 * Prix indicatif en minor units (entier ≥ 0) + devise ISO 4217 — jamais de
 * flottants (spec SOLUTION_CATALOGUE_B2B.md §4/§8). Slug unique par tenant
 * (généré automatiquement depuis le nom si absent). Statut par défaut
 * `draft` (rien n'est public sans publication explicite).
 */
class StoreCatalogProductRequest extends FormRequest
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
        /** @var Employee $actor */
        $actor = $this->user();

        $statuses = array_map(
            static fn (CatalogProductStatus $s): string => $s->value,
            CatalogProductStatus::cases()
        );

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable',
                'alpha_dash',
                'max:160',
                Rule::unique('catalog_products', 'slug')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('catalog_categories', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_minor' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'unit' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in($statuses)],
            'meta' => ['nullable', 'array'],
        ];
    }
}
