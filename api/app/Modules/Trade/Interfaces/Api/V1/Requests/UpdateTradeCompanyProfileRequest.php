<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create-or-update du profil commercial Trade (BC-36, #9617).
 *
 * ## Ce que la requête valide
 *
 * - **directions** : `import_enabled` / `export_enabled` / `domestic_scope`
 *   sont indépendantes (aucune entreprise n'est enfermée dans un rôle
 *   irréversible — invariant du modèle `TradeCompanyProfile`).
 * - **au moins une direction** : un profil sans direction n'est pas une
 *   entreprise commerciale ; il ne doit pas exister.
 * - **devise** : ISO 4217 sur 3 lettres majuscules.
 * - **Incoterm** : liste fermée (Incoterms 2020). Une valeur libre produirait
 *   des documents douaniers invalides.
 *
 * La publication (`is_public`, `public_slug`, `public_name`) a sa propre route
 * (`PUT /trade/profile/publication`) : publier est un acte distinct de décrire
 * son activité, et le mélanger à un formulaire de profil ferait publier par
 * inadvertance à chaque enregistrement.
 */
class UpdateTradeCompanyProfileRequest extends FormRequest
{
    /** Incoterms 2020 — liste fermée. */
    public const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'import_enabled' => ['nullable', 'boolean'],
            'export_enabled' => ['nullable', 'boolean'],
            'domestic_scope' => ['nullable', 'boolean'],
            'hq_country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'markets_served' => ['nullable', 'array', 'max:50'],
            'markets_served.*' => ['string', 'max:60'],
            'product_categories' => ['nullable', 'array', 'max:50'],
            'product_categories.*' => ['string', 'max:80'],
            'default_currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'default_incoterm' => ['nullable', 'string', 'in:'.implode(',', self::INCOTERMS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_incoterm.in' => 'Incoterm inconnu : utilisez un code Incoterms 2020 (EXW, FOB, CIF…).',
            'hq_country.regex' => 'Le pays du siège doit être un code ISO 3166-1 alpha-2 (ex. DZ, FR).',
        ];
    }
}
