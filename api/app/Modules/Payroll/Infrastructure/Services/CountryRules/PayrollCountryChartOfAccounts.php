<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

use App\Support\CountryDefaults;

/**
 * #5256 — Plan comptable par pays pour les écritures salariales.
 *
 * Registre immuable des comptes utilisés pour les écritures comptables d'un
 * run de paie validé, par pays. Les libellés s'appuient sur le référentiel
 * comptable du pays (PCG, PCN, SYSCOHADA, Tekdüzen Hesap Planı, pratiques
 * UK/US) — voir docs/payroll/MULTI_PAYS_PLAN_COMPTABLE.md.
 *
 * Les cinq comptes suivent le modèle du flux « Paie → Comptabilité »
 * (docs/architecture/COMPTABILITE_CONCEPTION.md §6.3) :
 *   - salary_expense        → D (équivalent 641, salaires bruts)
 *   - employer_charges      → D (équivalent 645, charges patronales)
 *   - net_payable           → C (équivalent 421, net à payer)
 *   - social_contributions  → C (équivalent 431, cotisations salariale + patronale)
 *   - income_tax_withheld   → C (équivalent 4421, impôt retenu à la source)
 *   - other_deductions      → C (équivalent 425, avances et autres retenues)
 *
 * Chaque entrée porte un niveau de confiance (constitution §III) :
 *   - 'production' : code ancré dans un référentiel officiel documenté ;
 *   - 'pilot'      : code de pratique courante, à valider par un
 *                    expert-comptable local avant généralisation.
 */
final class PayrollCountryChartOfAccounts
{
    /**
     * Plan comptable « famille PCG » — utilisé tel quel par les pays
     * francophones (DZ/MA/TN/FR) et, sous sa déclinaison SYSCOHADA, par la
     * zone OHADA (SN/CI/CM et membres CEMAC/CEDEAO).
     *
     * @var array{
     *     salary_expense: array{code: string, label: string},
     *     employer_charges: array{code: string, label: string},
     *     net_payable: array{code: string, label: string},
     *     social_contributions: array{code: string, label: string},
     *     income_tax_withheld: array{code: string, label: string},
     *     other_deductions: array{code: string, label: string},
     * }
     */
    private const PCG_ACCOUNTS = [
        'salary_expense' => ['code' => '641', 'label' => 'Salaires et appointements'],
        'employer_charges' => ['code' => '645', 'label' => 'Charges de sécurité sociale et de prévoyance'],
        'net_payable' => ['code' => '421', 'label' => 'Personnel — rémunérations dues'],
        'social_contributions' => ['code' => '431', 'label' => 'Organismes sociaux (sécurité sociale)'],
        'income_tax_withheld' => ['code' => '4421', 'label' => 'État — impôt retenu à la source'],
        'other_deductions' => ['code' => '425', 'label' => 'Personnel — avances et acomptes'],
    ];

    /**
     * Registre des plans comptables explicites par pays.
     *
     * @var array<string, array{
     *     accounts: array{
     *         salary_expense: array{code: string, label: string},
     *         employer_charges: array{code: string, label: string},
     *         net_payable: array{code: string, label: string},
     *         social_contributions: array{code: string, label: string},
     *         income_tax_withheld: array{code: string, label: string},
     *         other_deductions: array{code: string, label: string},
     *     },
     *     confidence_level: string,
     *     reference: string,
     * }>
     */
    private const EXPLICIT = [
        'DZ' => [
            'accounts' => self::PCG_ACCOUNTS,
            'confidence_level' => 'production',
            'reference' => 'Plan comptable algérien (PCN 2009), classe 4/6 — COMPTABILITE_CONCEPTION.md §6.3',
        ],
        'MA' => [
            'accounts' => self::PCG_ACCOUNTS,
            'confidence_level' => 'pilot',
            'reference' => 'Plan comptable marocain (PCG 1993), classe 4/6 — à valider par expert-comptable',
        ],
        'TN' => [
            'accounts' => self::PCG_ACCOUNTS,
            'confidence_level' => 'pilot',
            'reference' => 'Plan comptable tunisien, classe 4/6 — à valider par expert-comptable',
        ],
        'FR' => [
            'accounts' => self::PCG_ACCOUNTS,
            'confidence_level' => 'production',
            'reference' => 'PCG français (règlement ANC 2014-03), comptes 641/645/421/431/4421/425',
        ],
        // SYSCOHADA (OHADA) — les 3 pays « packs » portent des codes explicites
        // (même référentiel, libellés 2017) ; les autres membres dérivent (all()).
        'SN' => [
            'accounts' => self::PCG_ACCOUNTS,
            'confidence_level' => 'pilot',
            'reference' => 'SYSCOHADA 2017, classe 4/6 — à valider par expert-comptable',
        ],
        'CI' => [
            'accounts' => self::PCG_ACCOUNTS,
            'confidence_level' => 'pilot',
            'reference' => 'SYSCOHADA 2017, classe 4/6 — à valider par expert-comptable',
        ],
        'CM' => [
            'accounts' => self::PCG_ACCOUNTS,
            'confidence_level' => 'pilot',
            'reference' => 'SYSCOHADA 2017, classe 4/6 — à valider par expert-comptable',
        ],
        // Turquie — Tekdüzen Hesap Planı (THP) : la paie est une charge
        // générale (770) ; net à payer en 335 ; cotisations SGK en 361 ;
        // gelir vergisi + damga vergisi retenus en 360 ; avances en 135.
        'TR' => [
            'accounts' => [
                'salary_expense' => ['code' => '770', 'label' => 'Genel Yönetim Giderleri (ücretler)'],
                'employer_charges' => ['code' => '770', 'label' => 'Genel Yönetim Giderleri (işveren SGK)'],
                'net_payable' => ['code' => '335', 'label' => 'Personele Borçlar'],
                'social_contributions' => ['code' => '361', 'label' => 'Ödenecek Sosyal Güvenlik Kesintileri'],
                'income_tax_withheld' => ['code' => '360', 'label' => 'Ödenecek Vergi ve Fonlar (gelir/damga vergisi)'],
                'other_deductions' => ['code' => '135', 'label' => 'Personel Avansları'],
            ],
            'confidence_level' => 'pilot',
            'reference' => 'Tekdüzen Hesap Planı (THP) — pratique courante, à valider par expert-comptable local',
        ],
        // Royaume-Uni — pas de plan comptable légal : codes de pratique
        // courante (FRS 102 / Xero-type). PAYE + NI sur le compte créancier 2210.
        'GB' => [
            'accounts' => [
                'salary_expense' => ['code' => '622', 'label' => 'Salaries and wages'],
                'employer_charges' => ['code' => '622', 'label' => 'Employer national insurance'],
                'net_payable' => ['code' => '2300', 'label' => 'Salaries control (net pay)'],
                'social_contributions' => ['code' => '2210', 'label' => 'PAYE / NI creditor'],
                'income_tax_withheld' => ['code' => '2210', 'label' => 'PAYE income tax creditor'],
                'other_deductions' => ['code' => '2310', 'label' => 'Employee advances'],
            ],
            'confidence_level' => 'pilot',
            'reference' => 'Pratique UK (HMRC payroll) — pas de chart légal, à valider par expert-comptable',
        ],
        // États-Unis — pratique courante (QuickBooks-type). Cotisations (FICA)
        // et impôt fédéral/état retenus en passifs de paie 2030/2040.
        'US' => [
            'accounts' => [
                'salary_expense' => ['code' => '6010', 'label' => 'Salaries and wages expense'],
                'employer_charges' => ['code' => '6040', 'label' => 'Payroll tax expense (employer)'],
                'net_payable' => ['code' => '2020', 'label' => 'Accrued payroll (net pay)'],
                'social_contributions' => ['code' => '2030', 'label' => 'Payroll tax liabilities (FICA)'],
                'income_tax_withheld' => ['code' => '2040', 'label' => 'Income tax withheld'],
                'other_deductions' => ['code' => '1010', 'label' => 'Employee advances'],
            ],
            'confidence_level' => 'pilot',
            'reference' => 'Pratique US (GAAP / QuickBooks-type) — à valider par expert-comptable',
        ],
        // Canada — pratique courante (CPP/EI + retenues ARC).
        'CA' => [
            'accounts' => [
                'salary_expense' => ['code' => '6010', 'label' => 'Salaries and wages expense'],
                'employer_charges' => ['code' => '6040', 'label' => 'Employer payroll taxes (CPP/EI)'],
                'net_payable' => ['code' => '2020', 'label' => 'Accrued payroll (net pay)'],
                'social_contributions' => ['code' => '2030', 'label' => 'Payroll liabilities (CPP/EI)'],
                'income_tax_withheld' => ['code' => '2040', 'label' => 'Income tax withheld (CRA)'],
                'other_deductions' => ['code' => '1010', 'label' => 'Employee advances'],
            ],
            'confidence_level' => 'pilot',
            'reference' => 'Pratique CA (CRA payroll) — à valider par expert-comptable',
        ],
    ];

    /**
     * Pays dérivés : un membre de zone réutilise le plan comptable du pays
     * de référence de la zone (même référentiel SYSCOHADA).
     *
     * @var array<string, string>
     */
    private const DERIVED = [
        // CEDEAO/UEMOA (XOF) → Sénégal
        'ML' => 'SN', 'BF' => 'SN', 'BJ' => 'SN', 'TG' => 'SN', 'NE' => 'SN',
        // CEMAC (XAF) → Cameroun
        'GA' => 'CM', 'CG' => 'CM', 'TD' => 'CM', 'CF' => 'CM', 'GQ' => 'CM',
    ];

    /**
     * Plan comptable d'un pays, explicite ou dérivé.
     *
     * @return array{
     *     country: string,
     *     base_country: string,
     *     accounts: array{
     *         salary_expense: array{code: string, label: string},
     *         employer_charges: array{code: string, label: string},
     *         net_payable: array{code: string, label: string},
     *         social_contributions: array{code: string, label: string},
     *         income_tax_withheld: array{code: string, label: string},
     *         other_deductions: array{code: string, label: string},
     *     },
     *     confidence_level: string,
     *     reference: string,
     * }|null
     */
    public static function forCountry(?string $country): ?array
    {
        $code = strtoupper(trim((string) $country));
        if ($code === '' || ! CountryDefaults::isSupported($code)) {
            return null;
        }

        $base = $code;
        if (isset(self::EXPLICIT[$code])) {
            $entry = self::EXPLICIT[$code];
        } elseif (isset(self::DERIVED[$code])) {
            $base = self::DERIVED[$code];
            $entry = self::EXPLICIT[$base];
        } else {
            return null;
        }

        return [
            'country' => $code,
            'base_country' => $base,
            'accounts' => $entry['accounts'],
            'confidence_level' => $entry['confidence_level'],
            'reference' => $entry['reference'],
        ];
    }

    /**
     * Tous les pays du registre officiel (CountryDefaults), chacun avec son
     * plan comptable (explicite ou dérivé) — garantit que TOUT pays déclaré
     * produit un export comptable cohérent (DoD #5256).
     *
     * @return list<array{
     *     country: string,
     *     base_country: string,
     *     accounts: array{
     *         salary_expense: array{code: string, label: string},
     *         employer_charges: array{code: string, label: string},
     *         net_payable: array{code: string, label: string},
     *         social_contributions: array{code: string, label: string},
     *         income_tax_withheld: array{code: string, label: string},
     *         other_deductions: array{code: string, label: string},
     *     },
     *     confidence_level: string,
     *     reference: string,
     * }>
     */
    public static function all(): array
    {
        $entries = [];
        foreach (CountryDefaults::all() as $defaults) {
            $entry = self::forCountry($defaults['country']);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public static function isSupported(?string $country): bool
    {
        return self::forCountry($country) !== null;
    }
}
