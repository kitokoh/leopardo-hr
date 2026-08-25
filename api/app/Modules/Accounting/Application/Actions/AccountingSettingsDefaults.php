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
 *   - taux de TVA par pays (défauts légaux 2026, modifiables ensuite dans
 *     l'UI settings) — multi-taux quand le pays en a plusieurs (ex. DZ 19/9) ;
 *   - mentions légales par défaut par pays ;
 *   - séries de numérotation par défaut (préfixe par type de document).
 *
 * Les mêmes défauts sont exposés à la volée par GET /accounting/settings quand
 * aucune ligne n'est encore persistée (résilience si le provisioning n'a pas
 * pu écrire, ex. migrations tenant non appliquées).
 */
final class AccountingSettingsDefaults
{
    /**
     * Taux de TVA par pays (en %) — défauts légaux 2026, modifiables par
     * l'entreprise (la TVA est paramétrable, jamais codée en dur dans les
     * calculs : COMPTABILITE_CONCEPTION.md §8). Issue #5271 : multi-taux par
     * pays (DZ 19/9, MA 20, TN 19, SN 18, CI 18, ML 18, BF 18, BJ 18, TG 18,
     * NE 19, CM 19,25, GA 18, CG 18,9, TD 18, CF 19, GQ 15, FR 20, TR 20,
     * GB 20, US 0, CA 5). Un pays absent retombe sur le taux standard 19 %.
     *
     * @var array<string, array<int, array{label: string, rate: int|float}>>
     */
    private const TVA_RATES_BY_COUNTRY = [
        'DZ' => [
            ['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 19],
            ['label' => 'TVA réduite', 'label_key' => 'reduced', 'rate' => 9],
        ],
        'MA' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 20]],
        'TN' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 19]],
        'SN' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'CI' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'ML' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'BF' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'BJ' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'TG' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'NE' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 19]],
        'CM' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 19.25]],
        'GA' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'CG' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18.9]],
        'TD' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 18]],
        'CF' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 19]],
        'GQ' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 15]],
        'FR' => [['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 20]],
        'TR' => [['label' => 'KDV standart', 'label_key' => 'standard', 'rate' => 20]],
        'GB' => [['label' => 'VAT standard', 'label_key' => 'standard', 'rate' => 20]],
        'US' => [['label' => 'Sales tax', 'label_key' => 'sales_tax', 'rate' => 0]],
        'CA' => [['label' => 'GST', 'label_key' => 'gst', 'rate' => 5]],
    ];

    /**
     * Mentions légales par défaut par pays (issue #5271) — exemples types,
     * modifiables par l'entreprise. Un pays absent → aucune mention (null).
     *
     * @var array<string, string>
     */
    private const LEGAL_MENTIONS_BY_COUNTRY = [
        'DZ' => 'RC {rc} — NIF {nif} — Article 54 de la loi de finances (TVA) — Capital social : {capital} DZD',
        'MA' => 'RC {rc} — IF {if} — ICE {ice} — Patente : {patente} — Capital social : {capital} MAD',
        'TN' => 'Matricule fiscal {matricule} — Registre de commerce {rc} — Capital social : {capital} TND',
        'SN' => 'RCCM {rccm} — NINEA {ninea} — Capital social : {capital} XOF',
        'CI' => 'RCCM {rccm} — NIF {nif} — Capital social : {capital} XOF',
        'FR' => 'SIRET {siret} — TVA intracommunautaire : {tva_intra} — Capital social : {capital} EUR',
        'TR' => 'Vergi No {vergi_no} — Ticaret Sicil No {sicil_no} — Sermaye : {capital} TRY',
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
            'legal_mentions' => self::LEGAL_MENTIONS_BY_COUNTRY[$countryCode] ?? null,
            'tva_rates' => self::TVA_RATES_BY_COUNTRY[$countryCode] ?? [
                ['label' => 'TVA standard', 'label_key' => 'standard', 'rate' => 19],
            ],
            'number_series' => self::DEFAULT_SERIES,
        ];
    }
}
