<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Support\CountryDefaults;

/**
 * Valeurs par défaut du paramétrage comptable — COMPTABILITE_CONCEPTION.md §4.
 *
 * Issue #5232 : à la création d'une entreprise (événement CompanyCreated), une
 * ligne AccountingSettings est provisionnée avec :
 *   - devise + langue dérivées du registre pays existant (CountryDefaults) ;
 *   - taux de TVA standard par pays (défauts légaux, modifiables ensuite dans
 *     l'UI settings) ;
 *   - séries de numérotation par défaut (préfixe par type de document).
 *
 * Les mêmes défauts sont exposés à la volée par GET /accounting/settings quand
 * aucune ligne n'est encore persistée (résilience si le provisioning n'a pas
 * pu écrire, ex. migrations tenant non appliquées).
 */
final class AccountingSettingsDefaults
{
    /**
     * Taux de TVA standard par pays (en %) — défauts légaux 2026, modifiables
     * par l'entreprise (la TVA est paramétrable, jamais codée en dur dans les
     * calculs : COMPTABILITE_CONCEPTION.md §8). Un pays absent du registre
     * retombe sur 19 % (défaut documenté) ; la devise/langue, elles, viennent
     * toujours de CountryDefaults.
     *
     * @var array<string, int|float>
     */
    private const TVA_STANDARD_BY_COUNTRY = [
        'DZ' => 19.0,
        'MA' => 20.0,
        'TN' => 19.0,
        'SN' => 18.0,
        'CI' => 18.0,
        'ML' => 18.0,
        'BF' => 18.0,
        'BJ' => 18.0,
        'TG' => 18.0,
        'NE' => 19.0,
        'CM' => 19.25,
        'GA' => 18.0,
        'CG' => 18.9,
        'TD' => 18.0,
        'CF' => 19.0,
        'GQ' => 15.0,
        'FR' => 20.0,
        'TR' => 20.0,
        'GB' => 20.0,
        'US' => 0.0,
        'CA' => 5.0,
    ];

    /**
     * Séries de numérotation par défaut (préfixe par type de document).
     * Le service de numérotation (#5223) lit `AccountingSettings.number_series`
     * sous la forme `[type => préfixe]` — voir SequentialDocumentNumbering.
     *
     * @var array<string, string>
     */
    private const DEFAULT_SERIES = [
        'invoice' => 'FAC',
        'proforma' => 'PRO',
        'quote' => 'DEV',
        'credit_note' => 'AVOIR',
        'delivery_note' => 'BL',
        'receipt' => 'REC',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function for(?string $country): array
    {
        // find() : résolution STRICTE (pas de fallback silencieux vers DZ) —
        // pour un pays inconnu on retombe sur les défauts du registre (DZ).
        $defaults = CountryDefaults::find($country) ?? CountryDefaults::for(null);
        $countryCode = strtoupper(trim((string) $country));

        return [
            'currency' => strtoupper($defaults['currency']),
            'document_language' => strtolower($defaults['language']),
            'template_style' => 'modern',
            'payment_terms' => null,
            'legal_mentions' => null,
            'tva_rates' => [
                [
                    'label' => 'TVA standard',
                    'rate' => self::TVA_STANDARD_BY_COUNTRY[$countryCode] ?? 19.0,
                ],
            ],
            'number_series' => self::DEFAULT_SERIES,
        ];
    }
}
