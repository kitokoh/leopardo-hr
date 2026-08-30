<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-901 (#6104) — Validation de mise à jour d'un article (PATCH).
 */
class UpdateTravelArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelArticlePolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        /** @var int|null $articleId */
        $articleId = $this->route('travelArticle')?->id;

        return [
            'category_id' => ['nullable', 'integer'],
            'slug' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique((new TravelArticle)->getTable(), 'slug')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                )->ignore($articleId),
            ],
            'title' => ['sometimes', 'string', 'max:200'],
            'body_redacted' => ['sometimes', 'string', 'max:50000'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'flagged'])],
        ];
    }
}
