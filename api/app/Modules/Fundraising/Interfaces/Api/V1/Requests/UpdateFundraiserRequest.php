<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Requests;

use App\Modules\Fundraising\Domain\Enums\FundraisingCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'édition d'une cagnotte (verticale FUNDRAISING). Tous les
 * champs sont optionnels (patch sémantique) ; le slug et les compteurs ne
 * sont JAMAIS éditables (absents des règles = ignorés du validated()).
 */
final class UpdateFundraiserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC : FundraiserPolicy@update (contrôleur)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:10000'],
            'beneficiary_name' => ['sometimes', 'required', 'string', 'max:190'],
            'beneficiary_contact' => ['nullable', 'string', 'max:190'],
            'category' => ['nullable', Rule::enum(FundraisingCategory::class)],
            'goal_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999'],
            'suggested_amounts' => ['nullable', 'array', 'max:10'],
            'suggested_amounts.*' => ['numeric', 'min:0.01'],
            'min_amount' => ['nullable', 'numeric', 'min:0.01'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'cover_image_path' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
