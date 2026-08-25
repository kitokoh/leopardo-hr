<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Registries;

use App\Support\CountryDefaults;

/**
 * #5422 — Plan comptable par pays pour le module Comptabilité (grand livre).
 *
 * Registre immuable des comptes utilisés par les écritures comptables du
 * module (facturation, trésorerie, dépenses, bilan), par pays. Les libellés
 * s'appuient sur le référentiel comptable du pays (PCG, PCN, SYSCOHADA,
 * Tekdüzen Hesap Planı, UK FRS 102, US GAAP) — voir
 * docs/accounting/PLAN_COMPTABLE.md.
 *
 * Familles de comptes (alignées sur le flux documents → journal #5234) :
 *   - clients              → D (créances clients — factures émises non réglées)
 *   - suppliers            → C (dettes fournisseurs — achats)
 *   - bank                 → D (compte bancaire — encaissements/décaissements)
 *   - cash                 → D (caisse)
 *   - vat_collected        → C (TVA collectée sur les ventes)
 *   - vat_deductible       → D (TVA déductible — avoirs/achats)
 *   - sales_revenue        → C (produits des ventes/prestations)
 *   - purchases            → D (achats)
 *   - external_charges     → D (autres charges externes)
 *   - paid_in_capital      → C (capital — bilan)
 *   - retained_earnings    → C (report à nouveau / résultat — bilan)
 *
 * Chaque entrée porte un niveau de confiance (constitution §III, pattern
 * #5256) :
 *   - 'production' : code ancré dans un référentiel officiel documenté ;
 *   - 'pilot'      : code de pratique courante, à valider par un
 *                    expert-comptable local avant généralisation.
 */
final class AccountingChartOfAccounts
{
    /**
     * Plan comptable « famille PCG » — utilisé tel quel par les pays
     * francophones (DZ/MA/TN/FR) et, sous sa déclinaison SYSCOHADA, par la
     * zone OHADA (SN/CI et membres CEMAC/CEDEAO).
     *
     * @var array<string, array{code: string, label: string}>
     */
    private const PCG_ACCOUNTS = [
        'clients' => ['code' => '411', 'label' => 'Clients'],
        'suppliers' => ['code' => '401', 'label' => 'Fournisseurs'],
        'bank' => ['code' => '512', 'label' => 'Banques'],
        'cash' => ['code' => '53', 'label' => 'Caisse'],
        'vat_collected' => ['code' => '4457', 'label' => 'État — TVA collectée'],
        'vat_deductible' => ['code' => '4456', 'label' => 'État — TVA déductible'],
        'sales_revenue' => ['code' => '706', 'label' => 'Prestations de services'],
        'purchases' => ['code' => '607', 'label' => 'Achats de marchandises'],
        'external_charges' => ['code' => '628', 'label' => 'Charges externes diverses'],
        'paid_in_capital' => ['code' => '101', 'label' => 'Capital social'],
        'retained_earnings' => ['code' => '110', 'label' => 'Report à nouveau'],
    ];

    /**
     * Déclinaisons SYSCOHADA (zone OHADA — SN/CI/CM et dérivés CEMAC/CEDEAO).
     *
     * @var array<string, array{code: string, label: string}>
     */
    private const OHADA_ACCOUNTS = [
        'clients' => ['code' => '411', 'label' => 'Clients'],
        'suppliers' => ['code' => '401', 'label' => 'Fournisseurs'],
        'bank' => ['code' => '521', 'label' => 'Banques'],
        'cash' => ['code' => '571', 'label' => 'Caisse'],
        'vat_collected' => ['code' => '44571', 'label' => 'État — TVA collectée'],
        'vat_deductible' => ['code' => '44566', 'label' => 'État — TVA déductible'],
        'sales_revenue' => ['code' => '706', 'label' => 'Prestations de services'],
        'purchases' => ['code' => '607', 'label' => 'Achats de marchandises'],
        'external_charges' => ['code' => '628', 'label' => 'Charges externes diverses'],
        'paid_in_capital' => ['code' => '101', 'label' => 'Capital social'],
        'retained_earnings' => ['code' => '11', 'label' => 'Report à nouveau'],
    ];

    /**
     * Plan comptable turc (Tekdüzen Hesap Planı).
     *
     * @var array<string, array{code: string, label: string}>
     */
    private const TR_ACCOUNTS = [
        'clients' => ['code' => '120', 'label' => 'Alıcılar'],
        'suppliers' => ['code' => '320', 'label' => 'Satıcılar'],
        'bank' => ['code' => '102', 'label' => 'Bankalar'],
        'cash' => ['code' => '100', 'label' => 'Kasa'],
        'vat_collected' => ['code' => '391', 'label' => 'Hesaplanan KDV'],
        'vat_deductible' => ['code' => '191', 'label' => 'İndirilecek KDV'],
        'sales_revenue' => ['code' => '600', 'label' => 'Yurt içi satışlar'],
        'purchases' => ['code' => '153', 'label' => 'Ticari mallar'],
        'external_charges' => ['code' => '770', 'label' => 'Genel yönetim giderleri'],
        'paid_in_capital' => ['code' => '500', 'label' => 'Sermaye'],
        'retained_earnings' => ['code' => '570', 'label' => 'Geçmiş yıllar kârları'],
    ];

    /**
     * Plan comptable UK (FRS 102 — plan usuel, à valider par un expert).
     *
     * @var array<string, array{code: string, label: string}>
     */
    private const UK_ACCOUNTS = [
        'clients' => ['code' => '1100', 'label' => 'Trade debtors'],
        'suppliers' => ['code' => '2100', 'label' => 'Trade creditors'],
        'bank' => ['code' => '1200', 'label' => 'Bank'],
        'cash' => ['code' => '1210', 'label' => 'Cash in hand'],
        'vat_collected' => ['code' => '2200', 'label' => 'VAT liability'],
        'vat_deductible' => ['code' => '2201', 'label' => 'VAT recoverable'],
        'sales_revenue' => ['code' => '4000', 'label' => 'Sales'],
        'purchases' => ['code' => '5000', 'label' => 'Purchases'],
        'external_charges' => ['code' => '6200', 'label' => 'General expenses'],
        'paid_in_capital' => ['code' => '3000', 'label' => 'Share capital'],
        'retained_earnings' => ['code' => '3200', 'label' => 'Retained earnings'],
    ];

    /**
     * Plan comptable US (US GAAP — plan usuel, à valider par un expert).
     *
     * @var array<string, array{code: string, label: string}>
     */
    private const US_ACCOUNTS = [
        'clients' => ['code' => '1100', 'label' => 'Accounts receivable'],
        'suppliers' => ['code' => '2000', 'label' => 'Accounts payable'],
        'bank' => ['code' => '1000', 'label' => 'Cash — checking'],
        'cash' => ['code' => '1010', 'label' => 'Petty cash'],
        'vat_collected' => ['code' => '2300', 'label' => 'Sales tax payable'],
        'vat_deductible' => ['code' => '1290', 'label' => 'Sales tax receivable'],
        'sales_revenue' => ['code' => '4000', 'label' => 'Sales revenue'],
        'purchases' => ['code' => '5000', 'label' => 'Purchases'],
        'external_charges' => ['code' => '6200', 'label' => 'General and administrative'],
        'paid_in_capital' => ['code' => '3000', 'label' => 'Common stock'],
        'retained_earnings' => ['code' => '3500', 'label' => 'Retained earnings'],
    ];

    /**
     * Plan comptable Canada (pratique usuelle, à valider par un expert).
     *
     * @var array<string, array{code: string, label: string}>
     */
    private const CA_ACCOUNTS = [
        'clients' => ['code' => '1100', 'label' => 'Accounts receivable'],
        'suppliers' => ['code' => '2000', 'label' => 'Accounts payable'],
        'bank' => ['code' => '1000', 'label' => 'Bank'],
        'cash' => ['code' => '1010', 'label' => 'Cash on hand'],
        'vat_collected' => ['code' => '2250', 'label' => 'GST/HST payable'],
        'vat_deductible' => ['code' => '1290', 'label' => 'GST/HST recoverable'],
        'sales_revenue' => ['code' => '4000', 'label' => 'Revenue'],
        'purchases' => ['code' => '5000', 'label' => 'Purchases'],
        'external_charges' => ['code' => '6200', 'label' => 'General expenses'],
        'paid_in_capital' => ['code' => '3000', 'label' => 'Share capital'],
        'retained_earnings' => ['code' => '3500', 'label' => 'Retained earnings'],
    ];

    /**
     * Pays de la zone OHADA (déclinaison SYSCOHADA) — membres directs +
     * dérivés CEMAC/CEDEAO du registre CountryDefaults.
     *
     * @var list<string>
     */
    private const OHADA_COUNTRIES = ['SN', 'CI', 'ML', 'BF', 'BJ', 'TG', 'NE', 'CM', 'GA', 'CG', 'TD', 'CF', 'GQ'];

    /**
     * Registre des plans comptables explicites par pays.
     *
     * @var array<string, array<string, array{code: string, label: string}>>
     */
    private const EXPLICIT = [
        'TR' => self::TR_ACCOUNTS,
        'GB' => self::UK_ACCOUNTS,
        'US' => self::US_ACCOUNTS,
        'CA' => self::CA_ACCOUNTS,
    ];

    /** Familles de comptes attendues pour chaque pays. @var list<string> */
    public const ACCOUNT_FAMILIES = [
        'clients',
        'suppliers',
        'bank',
        'cash',
        'vat_collected',
        'vat_deductible',
        'sales_revenue',
        'purchases',
        'external_charges',
        'paid_in_capital',
        'retained_earnings',
    ];

    /**
     * Plan comptable du module pour un pays.
     *
     * @return array<string, array{code: string, label: string, confidence: string}>
     */
    public static function for(?string $country): array
    {
        $code = strtoupper(trim((string) $country));

        if (isset(self::EXPLICIT[$code])) {
            return self::withConfidence(self::EXPLICIT[$code], 'pilot');
        }

        if (in_array($code, self::OHADA_COUNTRIES, true)) {
            return self::withConfidence(self::OHADA_ACCOUNTS, 'production');
        }

        // Famille PCG (DZ/MA/TN/FR + fallback) — codes référentiels officiels.
        return self::withConfidence(self::PCG_ACCOUNTS, 'production');
    }

    /**
     * @param  array<string, array{code: string, label: string}>  $accounts
     * @return array<string, array{code: string, label: string, confidence: string}>
     */
    private static function withConfidence(array $accounts, string $confidence): array
    {
        return array_map(
            static fn (array $account): array => $account + ['confidence' => $confidence],
            $accounts,
        );
    }

    /**
     * Vérifie que le plan comptable du pays couvre toutes les familles.
     */
    public static function assertComplete(?string $country): void
    {
        $missing = array_diff(self::ACCOUNT_FAMILIES, array_keys(self::for($country)));

        if ($missing !== []) {
            throw new \RuntimeException(sprintf(
                'Plan comptable incomplet pour %s : familles manquantes [%s].',
                (string) $country,
                implode(', ', $missing),
            ));
        }
    }

    /**
     * Pays supportés (registre CountryDefaults).
     *
     * @return list<string>
     */
    public static function supportedCountries(): array
    {
        return array_keys(CountryDefaults::all());
    }
}
