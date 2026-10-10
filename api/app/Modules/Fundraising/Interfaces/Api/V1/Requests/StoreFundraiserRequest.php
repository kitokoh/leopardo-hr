<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Requests;

use App\Modules\Fundraising\Domain\Enums\FundraisingCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de création d'une cagnotte (verticale FUNDRAISING — spec
 * §3.1). Aucun champ sensible n'est mass-assignable ailleurs (slug généré,
 * compteurs exclus, status forcé draft).
 */
final class StoreFundraiserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC : FundraiserPolicy@create (contrôleur)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:10000'],
            'beneficiary_name' => ['required', 'string', 'max:190'],
            'beneficiary_contact' => ['nullable', 'string', 'max:190'],
            'category' => ['nullable', Rule::enum(FundraisingCategory::class)],
            'goal_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'suggested_amounts' => ['nullable', 'array', 'max:10'],
            'suggested_amounts.*' => ['numeric', 'min:0.01'],
            'min_amount' => ['nullable', 'numeric', 'min:0.01'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
