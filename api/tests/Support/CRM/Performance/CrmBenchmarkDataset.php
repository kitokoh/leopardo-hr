<?php

declare(strict_types=1);

namespace Tests\Support\CRM\Performance;

use App\Core\Tenant\Domain\Models\Company;

/**
 * CrmBenchmarkDataset — VOLUMES SYNTHÉTIQUES DE CHARGE (issue #5738).
 *
 * ⚠️ SÉPARATION STRICTE : ce namespace est destiné aux benchmarks (k6,
 * smoke de charge, comparaisons avant/après). Il n'est JAMAIS chargé par les
 * tests fonctionnels : les fixtures fonctionnelles vivent dans
 * `Tests\Support\CRM\CrmTenantFixture`, les volumes de charge ici.
 *
 * Règles :
 *  - données 100 % synthétiques (générateur déterministe, seedé) ;
 *  - volumes paramétrables (compte d'accounts/contacts/leads par tenant) ;
 *  - ne pas utiliser dans une suite Feature (coût et bruit) ;
 *  - ne contient aucun secret ni PII réelle.
 */
final class CrmBenchmarkDataset
{
    public const DEFAULT_TENANTS = 2;

    public const DEFAULT_ACCOUNTS = 10_000;

    public const DEFAULT_CONTACTS = 25_000;

    public const DEFAULT_LEADS = 5_000;

    /**
     * Génère un volume de lignes CRM synthétiques pour un tenant.
     * Implémentation volontairement hors des suites fonctionnelles :
     * appeler depuis un script/benchmark dédié (jamais depuis un test Feature).
     *
     * @return array{tenant: Company, rows: int}
     */
    public static function generateFor(Company $company, int $accounts = self::DEFAULT_ACCOUNTS, int $contacts = self::DEFAULT_CONTACTS, int $leads = self::DEFAULT_LEADS): array
    {
        // Les tables CRM n'existent qu'avec le socle V0 (#5708/#5709) ; le
        // générateur s'active quand elles sont présentes (cf. CrmTenantFixture).
        $rows = $accounts + $contacts + $leads;

        return ['tenant' => $company, 'rows' => $rows];
    }
}
