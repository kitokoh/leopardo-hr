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

        // #8396 — deux contrats coexistaient : OpenAPI documente
        // `{article_id, content}` mais la route imbrique l'article dans l'URL
        // (`/articles/{travelArticle}/comments`) et les clients envoient
        // `body`. Résultat : toute création conforme partait en 422 sur
        // `article_id` manquant. `article_id` n'est donc plus requis (l'URL
        // fait foi, la validation ci-dessous le contraint quand il est
        // fourni), et `body` est accepté comme alias de `content`.
        return [
            'article_id' => [
                'nullable',
                'integer',
                Rule::exists((new TravelArticle)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'content' => ['required_without:body', 'nullable', 'string', 'min:2', 'max:2000'],
            'body' => ['required_without:content', 'nullable', 'string', 'min:2', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required_without' => 'Le contenu du commentaire est obligatoire.',
            'body.required_without' => 'Le contenu du commentaire est obligatoire.',
            'content.min' => 'Le commentaire doit contenir au moins 2 caractères.',
            'body.min' => 'Le commentaire doit contenir au moins 2 caractères.',
            'content.max' => 'Le commentaire ne doit pas dépasser 2000 caractères.',
            'body.max' => 'Le commentaire ne doit pas dépasser 2000 caractères.',
        ];
    }
}
