<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelArticleCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-901 (#6104) — Validation de création d'un article.
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
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique((new TravelArticle)->getTable(), 'slug')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'title' => ['required', 'string', 'max:200'],
            'body_redacted' => ['required', 'string', 'max:50000'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'flagged'])],
        ];
    }
}
