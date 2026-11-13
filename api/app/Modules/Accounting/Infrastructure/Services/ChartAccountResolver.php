<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;

/**
 * Résolution des comptes d'écriture du module Comptabilité (issues #9368,
 * successeur du codage en dur de `JournalPostingService`).
 *
 * Le module disposait déjà de deux sources de vérité correctes mais
 * *déconnectées* du posting :
 *   - `AccountingChartOfAccounts::for()`  — registre de comptes moteurs par pays
 *     (PCG, SYSCOHADA, Tekdüzen, UK, US, CA) ;
 *   - `accounting_chart_accounts`         — plan comptable provisionné puis
 *     personnalisé par le tenant.
 *
 * `JournalPostingService` les ignorait et postait sur des constantes PCG
 * (`411`/`70`/`4457`/`512`/`53`) : un tenant turc recevait un plan Tekdüzen
 * (120/600/391/102) mais ses écritures atterrissaient sur des comptes absents
 * de son plan — grand livre, balance, bilan et FEC incohérents.
 *
 * Ce résolveur applique un ordre de priorité explicite et traçable :
 *   1. plan comptable **du tenant** (`accounting_chart_accounts`, comptes
 *      actifs) — c'est lui que l'expert-comptable a validé et que la balance
 *      affichera ;
 *   2. registre **pays** (`AccountingChartOfAccounts::for()`) ;
 *   3. défaut de repli (`FALLBACK`) quand ni l'un ni l'autre ne fournit la
 *      famille demandée — jamais de compte inventé hors plan.
 *
 * Le résolveur est volontairement *sans dépendance obligatoire* : le pays peut
 * être fourni directement (résolution pure, testable) ou déduit de l'entreprise.
 */
final class ChartAccountResolver
{
    /**
     * Repli historique PCG — utilisé uniquement quand ni le plan du tenant ni
     * le registre pays ne couvrent la famille demandée (ex. base migrée d'une
     * version antérieure au seed par pays).
     *
     * @var array<string, array{code: string, label: string}>
     */
    public const FALLBACK = [
        'clients' => ['code' => '411', 'label' => 'Clients'],
        'suppliers' => ['code' => '401', 'label' => 'Fournisseurs'],
        'bank' => ['code' => '512', 'label' => 'Banques'],
        'cash' => ['code' => '53', 'label' => 'Caisse'],
        'vat_collected' => ['code' => '4457', 'label' => 'État — TVA collectée'],
        'vat_deductible' => ['code' => '4456', 'label' => 'État — TVA déductible'],
        'sales_revenue' => ['code' => '706', 'label' => 'Prestations de services'],
        'sales_discount' => ['code' => '709', 'label' => 'Rabais, remises et ristournes'],
        'purchases' => ['code' => '607', 'label' => 'Achats de marchandises'],
        'purchase_discount' => ['code' => '609', 'label' => 'Rabais, remises et ristournes obtenus'],
        'external_charges' => ['code' => '628', 'label' => 'Charges externes diverses'],
        'payroll_expense' => ['code' => '641', 'label' => 'Salaires et appointements'],
        'expense_report' => ['code' => '6251', 'label' => 'Voyages et déplacements'],
        'paid_in_capital' => ['code' => '101', 'label' => 'Capital social'],
        'retained_earnings' => ['code' => '110', 'label' => 'Report à nouveau'],
    ];

    /** @var array<string, array{code: string, label: string}> */
    private array $resolved;

    private ?string $country;

    /**
     * @param  array<array-key, string>|null  $overrides
     *                                                    Surcharges explicites `code => libellé` (plan comptable du tenant
     *                                                    déjà chargé) — évite une requête quand l'appelant les possède.
     *                                                    Les clés numériques sont normalisées en chaîne par `merge()`.
     */
    public function __construct(?string $country = null, ?array $overrides = null)
    {
        $this->country = $country;
        $this->resolved = $overrides !== null
            ? $this->merge($this->countryAccounts($country), $overrides)
            : $this->countryAccounts($country);
    }

    /**
     * Résolveur pour une entreprise : tente de lire le plan comptable du tenant
     * (comptes actifs) puis retombe sur le registre pays.
     */
    public static function forCompany(Company $company): self
    {
        return new self($company->country, self::tenantOverrides((string) $company->id));
    }

    /**
     * Construit le résolveur sans entreprise (pays seul) — utile pour les
     * documents détachés et les tests de résolution pure.
     */
    public static function forCountry(?string $country): self
    {
        return new self($country);
    }

    /**
     * Lit les comptes actifs du plan comptable du tenant, indexés par famille.
     *
     * La correspondance famille → compte se fait par **code** : on ne peut pas
     * deviner la sémantique d'un compte personnalisé, donc on ne mappe que les
     * codes connus du registre pays (et du repli). Un compte personnalisé
     * supplémentaire reste disponible via `accountCode()`/`label()` mais ne
     * remplace pas un compte moteur.
     *
     * @return array<string, string> code => label
     */
    public static function tenantOverrides(string $companyId): array
    {
        $accounts = AccountingChartAccount::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->get(['code', 'label']);

        /** @var array<string, string> $labelByCode */
        $labelByCode = [];

        foreach ($accounts as $account) {
            $labelByCode[(string) $account->code] = (string) $account->label;
        }

        return $labelByCode;
    }

    /**
     * Code du compte pour une famille (`clients`, `suppliers`, `bank`,
     * `cash`, `vat_collected`, `vat_deductible`, `sales_revenue`,
     * `sales_discount`, `purchases`, `purchase_discount`, `external_charges`,
     * `payroll_expense`, `expense_report`, `paid_in_capital`,
     * `retained_earnings`).
     */
    public function accountCode(string $family): string
    {
        return $this->entry($family)['code'];
    }

    public function label(string $family): string
    {
        return $this->entry($family)['label'];
    }

    /**
     * Ligne prête pour `JournalPostingService::postLines()`.
     *
     * @return array{code: string, label: string}
     */
    public function entry(string $family): array
    {
        return $this->resolved[$family] ?? self::FALLBACK[$family] ?? ['code' => '471', 'label' => "Comptes d'attente"];
    }

    /**
     * Compte de trésorerie d'un moyen de paiement : la caisse pour les espèces,
     * la banque pour tout le reste (chèque, virement, carte, paiement en ligne).
     */
    public function treasuryFamilyFor(?string $method): string
    {
        $resolved = PaymentMethod::tryFrom((string) $method) ?? PaymentMethod::Other;

        return $resolved === PaymentMethod::Cash ? 'cash' : 'bank';
    }

    /**
     * @return array<string, array{code: string, label: string}>
     */
    public function all(): array
    {
        return $this->resolved;
    }

    public function country(): ?string
    {
        return $this->country;
    }

    /**
     * Registre pays complété par le repli : le registre ne couvre pas les
     * familles ajoutées pour les achats et la paie (ses 11 familles d'origine
     * sont orientées « vente + bilan »).
     *
     * @return array<string, array{code: string, label: string}>
     */
    private function countryAccounts(?string $country): array
    {
        /** @var array<string, array{code: string, label: string, confidence?: string}> $registry */
        $registry = AccountingChartOfAccounts::for($country);

        /** @var array<string, array{code: string, label: string}> $merged */
        $merged = [];

        foreach ($registry as $family => $account) {
            $merged[(string) $family] = ['code' => (string) $account['code'], 'label' => (string) $account['label']];
        }

        // Familles manquantes du registre : complétées par le repli, en gardant
        // la devise et la numérotation du pays quand une variante existe.
        foreach (self::FALLBACK as $family => $account) {
            $merged[$family] ??= $this->countryOverride($country, $family, $account);
        }

        return $merged;
    }

    /**
     * Variante pays d'une famille absente du registre moteur (achats, avoirs
     * fournisseurs, paie, notes de frais).
     *
     * @param  array{code: string, label: string}  $fallback
     * @return array{code: string, label: string}
     */
    private function countryOverride(?string $country, string $family, array $fallback): array
    {
        $familyKey = AccountingChartOfAccounts::familyFor($country);

        return match ($familyKey) {
            // SYSCOHADA — comptes de charges/produits et de tiers de la
            // nomenclature OHADA révisée (AUDCIF 2017).
            AccountingChartOfAccounts::FAMILY_SYSCOHADA => match ($family) {
                'purchases' => ['code' => '601', 'label' => 'Achats de marchandises'],
                'purchase_discount' => ['code' => '609', 'label' => 'Rabais, remises et ristournes obtenus'],
                'sales_discount' => ['code' => '7019', 'label' => 'Rabais, remises et ristournes accordés'],
                'payroll_expense' => ['code' => '661', 'label' => 'Rémunérations directes versées au personnel national'],
                'expense_report' => ['code' => '618', 'label' => 'Transports du personnel'],
                default => $fallback,
            },
            // Tekdüzen Hesap Planı — 7/A maliyet hesapları.
            AccountingChartOfAccounts::FAMILY_TEKDUZEN => match ($family) {
                'purchases' => ['code' => '153', 'label' => 'Ticari mallar'],
                'purchase_discount' => ['code' => '611', 'label' => 'Alıştan iadeler (-)'],
                'sales_discount' => ['code' => '610', 'label' => 'Satıştan iadeler (-)'],
                'payroll_expense' => ['code' => '770', 'label' => 'Genel yönetim giderleri'],
                'expense_report' => ['code' => '770', 'label' => 'Genel yönetim giderleri'],
                default => $fallback,
            },
            // Plans anglo-saxons (UK / US / CA) — numérotation 4 chiffres.
            AccountingChartOfAccounts::FAMILY_UK, AccountingChartOfAccounts::FAMILY_US, AccountingChartOfAccounts::FAMILY_CA => match ($family) {
                'purchases' => ['code' => '5000', 'label' => 'Purchases'],
                'purchase_discount' => ['code' => '5090', 'label' => 'Purchase discounts'],
                'sales_discount' => ['code' => '4090', 'label' => 'Sales discounts'],
                'payroll_expense' => ['code' => '6000', 'label' => 'Salaries and wages'],
                'expense_report' => ['code' => '6200', 'label' => 'General expenses'],
                default => $fallback,
            },
            default => $fallback,
        };
    }

    /**
     * Fusionne registre pays et surcharges du tenant.
     *
     * Les surcharges sont fournies sous la forme `code => label` (contenu de
     * `accounting_chart_accounts`). On ne peut pas déduire la famille depuis un
     * compte personnalisé : on remplace donc uniquement les familles dont le
     * code du registre pays **existe** dans le plan du tenant (le comptable a
     * conservé ce code → il fait foi), et on garde le libellé du tenant.
     *
     * @param  array<string, array{code: string, label: string}>  $base
     * @param  array<string, mixed>  $overrides  code => label
     * @return array<string, array{code: string, label: string}>
     */
    private function merge(array $base, array $overrides): array
    {
        if ($overrides === []) {
            return $base;
        }

        // PHP convertit les clés numériques en entiers : `['411' => 'Clients']`
        // devient `[411 => 'Clients']`. On normalise donc en chaîne avant de
        // comparer, sinon aucune surcharge de compte numéroté ne serait jamais
        // prise en compte (bug détecté par
        // PurchaseAndChartResolverTest::test_resolver_uses_the_tenant_chart…).
        /** @var array<string, string> $byCode */
        $byCode = [];

        // Le type est déjà `array<array-key, string>` : les gardes
        // `is_string()`/`is_int()` seraient toujours vraies (PHPStan level 8,
        // `function.alreadyNarrowedType`). Seule la normalisation en chaîne
        // reste nécessaire.
        foreach ($overrides as $code => $label) {
            $byCode[(string) $code] = $label;
        }

        foreach ($base as $family => $account) {
            if (isset($byCode[$account['code']])) {
                $base[$family] = ['code' => $account['code'], 'label' => $byCode[$account['code']]];
            }
        }

        return $base;
    }
}
