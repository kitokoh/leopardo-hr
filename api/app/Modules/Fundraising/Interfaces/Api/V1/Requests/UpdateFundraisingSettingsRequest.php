<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Requests;

use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation du paramétrage Fundraising (PUT /fundraising/settings).
 *
 * `enabled_methods` est volontairement un tableau non vide de méthodes
 * connues : une verticale sans aucun moyen de paiement offert est une
 * configuration boiteuse — le responsable désactive alors plutôt le module
 * (feature flag) que les contributeurs face à une cagnotte impossible à
 * alimenter.
 */
final class UpdateFundraisingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC portée par le groupe de routes (api.manager:principal,rh).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled_methods' => ['required', 'array', 'min:1'],
            'enabled_methods.*' => [
                'string',
                'distinct',
                Rule::in(array_map(
                    static fn (ContributionMethod $method): string => $method->value,
                    ContributionMethod::cases(),
                )),
            ],
            'default_min_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'default_max_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'default_suggested_amounts' => ['nullable', 'array', 'max:12'],
            'default_suggested_amounts.*' => ['numeric', 'min:1', 'max:999999999999'],
            'allow_anonymous' => ['sometimes', 'boolean'],
            'allow_messages' => ['sometimes', 'boolean'],
            'show_supporters' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cohérence des garde-fous : vérifiée APRÈS les règles scalaires — la
     * comparaison `gt:` native est indécidable quand le champ comparé est
     * absent (payload partiel), ce qui rejetterait « max seul » à tort.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $min = $this->input('default_min_amount');
            $max = $this->input('default_max_amount');

            if (is_numeric($min) && is_numeric($max) && (float) $max <= (float) $min) {
                $validator->errors()->add(
                    'default_max_amount',
                    __('validation.gt.numeric', ['attribute' => 'default_max_amount', 'value' => 'default_min_amount']),
                );
            }
        });
    }
}
