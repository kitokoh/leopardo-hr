<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelArticleCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-901 (#6104) — Validation de création d'un article.
 *
 * Le contrat public (OpenAPI `api_post_travel_community_articles`, tests de la
 * suite) expose `title` + `body` ; le modèle stocke `slug` + `body_redacted`.
 * Les deux champs internes étaient exigés du client : une requête conforme à
 * la documentation était donc rejetée en 422 (#8396).
 *
 * Ils sont désormais **dérivés côté serveur** :
 * - `slug` à partir de `title` s'il n'est pas fourni (suffixé si collision) ;
 * - `body_redacted` = `body`.
 */
class StoreTravelArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelArticlePolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        return [
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists((new TravelArticleCategory)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            // Optionnels a l'entree : derives ci-dessous quand ils manquent.
            'slug' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique((new TravelArticle)->getTable(), 'slug')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required_without:body_redacted', 'string', 'max:50000'],
            'body_redacted' => ['required_without:body', 'string', 'max:50000'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'flagged'])],
        ];
    }

    /**
     * Dérive `slug` et `body_redacted` avant la validation, pour que les
     * règles de type/unicité s'appliquent à des valeurs réelles.
     */
    protected function prepareForValidation(): void
    {
        $body = $this->input('body_redacted') ?? $this->input('body');

        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = Str::slug((string) $this->input('title', ''));
        }

        $this->merge(array_filter([
            'slug' => $slug !== '' ? $this->uniqueSlug($slug) : $slug,
            'body_redacted' => $body,
        ], static fn ($v): bool => $v !== null));
    }

    /** Suffixe le slug tant qu'il est déjà pris dans le tenant. */
    private function uniqueSlug(string $slug): string
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;
        $candidate = $slug;
        $suffix = 2;

        while (TravelArticle::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->exists()
        ) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
