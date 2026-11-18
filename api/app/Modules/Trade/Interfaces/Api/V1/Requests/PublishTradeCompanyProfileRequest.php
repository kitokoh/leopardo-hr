<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Opt-in de publication du profil commercial dans l'annuaire public (#9617).
 *
 * ## Pourquoi une route séparée
 *
 * `is_public` est un acte **délibéré** : l'entreprise choisit d'apparaître dans
 * `/entreprises` et sur `/entreprises/{slug}`. Le mêler au formulaire de profil
 * ferait publier par inadvertance à chaque enregistrement d'un champ commercial.
 *
 * ## Fail-closed
 *
 * Activer sans slug ni nom commercial est refusé en 422 : un profil activé mais
 * incomplet serait filtré par `isPubliclyVisible()` et l'utilisateur croirait
 * avoir publié.
 */
class PublishTradeCompanyProfileRequest extends FormRequest
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
        $publish = $this->boolean('is_public');

        return [
            'is_public' => ['required', 'boolean'],
            'public_slug' => [
                $publish ? 'required' : 'nullable',
                'string',
                'min:3',
                'max:80',
                'regex:/^[a-z0-9][a-z0-9\-]*$/',
            ],
            'public_name' => [
                $publish ? 'required' : 'nullable',
                'string',
                'max:160',
            ],
            'public_description' => ['nullable', 'string', 'max:2000'],
            'public_city' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'public_slug.required' => 'Un slug public est requis pour publier l’entreprise.',
            'public_name.required' => 'Un nom commercial public est requis pour publier l’entreprise.',
            'public_slug.regex' => 'Le slug public ne peut contenir que des minuscules, des chiffres et des tirets.',
        ];
    }
}
