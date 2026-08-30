<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-902 (#6105) — Validation stricte de création d'un commentaire.
 * `article_id` doit référencer un article DU tenant courant ; contenu borné.
 */
class StoreTravelCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelCommentPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        return [
            'article_id' => [
                'required',
                'integer',
                Rule::exists((new TravelArticle)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
