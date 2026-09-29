<?php

declare(strict_types=1);

namespace Tests\Feature\Solutions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;
use ValueError;

/**
 * BOS-013 (#8200) — Manifest v2 : champ `industry` + installation EFFECTIVE
 * des permissions déclarées à l'activation, via le socle de grants existant
 * (#7761), sans nouveau système de rôles.
 *
 * Critères d'acceptation de l'issue :
 *  1. Activer `edumanager` → les grants déclarés sont installés pour le
 *     manager principal (idempotent, transactionnel) ;
 *  2. Un manifest inactif ne confère aucune permission effective
 *     (fail-closed) ;
 *  3. Désactivation manuelle → retrait propre des grants installés (même
 *     logique transactionnelle, audit `solution.deactivated`) ;
 *  4. `industry` présent sur les manifests, enum rejetant toute valeur
 *     inconnue.
 */
class SolutionPermissionGrantsTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, bool>  $features
     */
    private function company(array $features = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => $features,
        ]);

        return $company;
    }

    private function principal(Company $company): Employee
    {
        return Employee::factory()->manager()->create(['company_id' => $company->id]);
    }

    /** @return list<string> */
    private function grantKeys(Company $company): array
    {
        /** @var list<string> $keys */
        $keys = EmployeeModuleGrant::forCompany($company)
            ->orderBy('module_key')
            ->pluck('module_key')
            ->all();

        return $keys;
    }

    public function test_activation_installs_declared_grants_for_the_principal(): void
    {
        $company = $this->company(['documents' => true, 'notifications' => true]);
        $principal = $this->principal($company);

        $result = app(SolutionActivator::class)->activate($company, 'edumanager');

        $this->assertSame('activated', $result['status']);

        $expected = array_keys(app(SolutionCatalogue::class)->resolve('edumanager')->permissions());
        $this->assertNotSame([], $expected);

        foreach ($expected as $code) {
            $this->assertContains($code, $this->grantKeys($company), "grant manquant : {$code}");
            $this->assertDatabaseHas('employee_module_grants', [
                'company_id' => $company->id,
                'employee_id' => $principal->id,
                'module_key' => $code,
            ]);
        }

        // Audit enrichi : permissions installées + industrie de la solution.
        /** @var AuditLog $log */
        $log = AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'solution.activated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('edumanager', $log->new_values['solution'] ?? null);
        $this->assertSame(SolutionIndustry::Education->value, $log->new_values['industry'] ?? null);
        $this->assertEqualsCanonicalizing($expected, $log->new_values['permissions_installed'] ?? []);
    }

    public function test_activation_is_idempotent_and_never_duplicates_grants(): void
    {
        $company = $this->company(['documents' => true, 'notifications' => true]);
        $this->principal($company);

        $activator = app(SolutionActivator::class);
        $activator->activate($company, 'edumanager');
        $countAfterFirst = count($this->grantKeys($company));

        $result = $activator->activate($company, 'edumanager');

        $this->assertSame('already_active', $result['status']);
        $this->assertCount($countAfterFirst, $this->grantKeys($company));
    }

    public function test_inactive_manifest_confers_no_effective_permission(): void
    {
        $company = $this->company(['documents' => true, 'notifications' => true]);
        $employee = Employee::factory()->create(['company_id' => $company->id]); // non-manager

        // Aucune activation : aucun grant `edu.*` chez personne.
        $this->assertSame([], $this->grantKeys($company));
        $this->assertFalse($employee->hasModuleGrant('edu.admin'));

        // L'activation d'une AUTRE solution n'installe pas les grants edu
        // (pharmacy : dépendances minimales, aucun listener d'amorçage).
        app(SolutionActivator::class)->activate($company, 'pharmacy');

        $this->assertFalse($employee->hasModuleGrant('edu.admin'));
        $this->assertNotContains('edu.admin', $this->grantKeys($company));
        $this->assertContains('pharmacy.catalog', $this->grantKeys($company));
    }

    public function test_manual_deactivation_revokes_installed_grants_cleanly(): void
    {
        $company = $this->company(['documents' => true, 'notifications' => true]);
        $this->principal($company);

        $activator = app(SolutionActivator::class);
        $activator->activate($company, 'edumanager');
        $this->assertNotSame([], $this->grantKeys($company));

        $result = $activator->deactivate($company, 'edumanager');

        $this->assertSame('deactivated', $result['status']);
        $this->assertFalse($company->refresh()->hasFeature('edumanager'));
        $this->assertSame([], $this->grantKeys($company));

        /** @var AuditLog $log */
        $log = AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'solution.deactivated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('edumanager', $log->new_values['solution'] ?? null);
        $this->assertContains('edu.admin', $log->new_values['permissions_revoked'] ?? []);

        // Idempotence : une seconde désactivation est un no-op.
        $this->assertSame('already_inactive', $activator->deactivate($company, 'edumanager')['status']);
    }

    public function test_deactivation_of_unknown_solution_is_refused(): void
    {
        $company = $this->company();

        $this->expectException(\App\Core\Solutions\Exceptions\SolutionNotFoundException::class);

        app(SolutionActivator::class)->deactivate($company, 'solution-inconnue');
    }

    public function test_industry_enum_rejects_unknown_values(): void
    {
        $this->expectException(ValueError::class);

        SolutionIndustry::from('industrie-inconnue');
    }

    public function test_every_catalogue_manifest_declares_a_valid_industry(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertNotSame([], $catalogue->codes());

        foreach ($catalogue->codes() as $code) {
            $industry = $catalogue->resolve($code)->industry();

            $this->assertInstanceOf(SolutionIndustry::class, $industry, "industry invalide pour {$code}");
            $this->assertSame($industry, SolutionIndustry::from($industry->value));
        }
    }
}
