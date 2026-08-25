<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Support\AccountingCurrencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mise à jour du paramétrage comptable de l'entreprise — issue #5232.
 *
 * La ligne AccountingSettings est unique par entreprise (company_id unique) :
 * chaque champ est optionnel (`nullable`) et remplace la valeur existante
 * quand il est fourni.
 *
 * Validation :
 *   - devise : code ISO 4217 (3 lettres) parmi les devises du registre
 *     `AccountingCurrencies` (union CountryDefaults, multi-devises #5270) ;
 *   - langue des documents : fr/ar/tr/en (i18n ×4 du module) ;
 *   - tva_rates : liste de {label, rate} — taux entre 0 et 100 % ;
 *   - number_series : préfixe par type de document (DocumentType), 20
 *     caractères max, alphanumérique + tiret (le service de numérotation
 *     met en majuscules).
 */
class UpdateAccountingSettingsRequest extends FormRequest
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
        return [
            'currency' => ['nullable', 'string', Rule::in(AccountingCurrencies::supported())],
            'document_language' => ['nullable', Rule::in(['fr', 'ar', 'tr', 'en'])],
            'template_style' => ['nullable', 'string', 'max:60'],
            'payment_terms' => ['nullable', 'string', 'max:60'],
            'legal_mentions' => ['nullable', 'string', 'max:2000'],
            'tva_rates' => ['nullable', 'array', 'min:1', 'max:20'],
            'tva_rates.*.label' => ['required', 'string', 'max:80'],
            'tva_rates.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'number_series' => ['nullable', 'array'],
            // Préfixe vide autorisé = série par défaut (le service de
            // numérotation #5223 retombe sur DEFAULT_SERIES si vide).
            'number_series.*' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]*$/'],
        ];
    }

    /**
     * Garde sur les clés de `number_series` : seuls les types de documents
     * connus (DocumentType) sont acceptés — une clé inconnue serait ignorée
     * silencieusement par le service de numérotation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $series = $this->input('number_series');

            if (! is_array($series) || $series === []) {
                return;
            }

            $allowed = DocumentType::values();

            foreach (array_keys($series) as $key) {
                if (! in_array((string) $key, $allowed, true)) {
                    $validator->errors()->add(
                        'number_series.'.$key,
                        __('accounting.validation.series_unknown', [
                            'key' => $key,
                            'allowed' => implode(', ', $allowed),
                        ]),
                    );
                }
            }
        });
    }
}
