<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create-or-update du profil public d'officine (#9616).
 *
 * ## Règles de validation
 *
 * - `public_slug` : format d'URL public (`[a-z0-9][a-z0-9-]*`), 3 à 80
 *   caractères. Le format est imposé ici **et** par l'index unique de la table :
 *   un slug malformé produirait une URL publique invalide, un doublon
 *   produirait deux officines sur la même adresse.
 * - `public_name` : requis **dès que `enabled` est vrai**. Un profil activé
 *   sans nom ne serait pas publiable (`isPubliclyVisible()` le refuse) : mieux
 *   vaut un 422 explicite qu'une publication silencieusement sans effet.
 * - `services` : liste libre bornée (20 entrées, 60 caractères chacune). Aucun
 *   référentiel imposé — les services déclarés varient par pays.
 *
 * Aucun champ réglementé ici : ni stock, ni lot, ni ordonnance, ni prix.
 */
class UpdatePharmacyPublicProfileRequest extends FormRequest
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
        $enabled = $this->boolean('enabled');

        return [
            'enabled' => ['nullable', 'boolean'],
            'public_slug' => [
                $enabled ? 'required' : 'nullable',
                'string',
                'min:3',
                'max:80',
                'regex:/^[a-z0-9][a-z0-9\-]*$/',
            ],
            'public_name' => [
                $enabled ? 'required' : 'nullable',
                'string',
                'max:160',
            ],
            'public_city' => ['nullable', 'string', 'max:120'],
            'public_description' => ['nullable', 'string', 'max:2000'],
            'public_phone' => ['nullable', 'string', 'max:40'],
            'on_duty' => ['nullable', 'boolean'],
            'services' => ['nullable', 'array', 'max:20'],
            'services.*' => ['string', 'max:60'],
        ];
    }

    /**
     * Message explicite quand l'activation est demandée sans identité publique.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'public_slug.required' => 'Un slug public est requis pour publier l’officine.',
            'public_name.required' => 'Un nom public est requis pour publier l’officine.',
            'public_slug.regex' => 'Le slug public ne peut contenir que des minuscules, des chiffres et des tirets.',
        ];
    }
}
