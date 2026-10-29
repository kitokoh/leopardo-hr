<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Interfaces\Api\V1\Support\PayrollRunAccess;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7962 — les gardes d'autorisation d'un run de paie sont nommées et testées.
 *
 * Avant l'extraction, `PayrollRunController` répétait le préambule
 * tenant+manager dans onze méthodes et le bloc `catch` métier dans cinq. Rien
 * ne testait ces gardes directement : elles n'étaient couvertes qu'incidemment
 * par des tests d'API, et la différence entre `isManager()` (calculer) et
 * `hasManagerRole('principal','comptable')` (approuver) n'était écrite nulle
 * part. Ces tests la figent.
 */
final class PayrollRunAccessTest extends TestCase
{
    use RefreshTenantDatabase;

    private PayrollRunAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->access = app(PayrollRunAccess::class);
    }

    public function test_a_run_from_another_tenant_is_a_404_not_a_403(): void
    {
        $run = (new PayrollRun)->forceFill(['company_id' => 'company-a']);
        $actor = (new Employee)->forceFill(['company_id' => 'company-b']);

        try {
            $this->access->authorizeManager($run, $actor);
            self::fail('Expected a 404 for a cross-tenant run.');
        } catch (HttpException $exception) {
            // 404 et non 403 : ne pas révéler l'existence d'un run d'un autre
            // tenant est le comportement historique, on le préserve.
            self::assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_an_ordinary_employee_cannot_manage_a_run(): void
    {
        $run = (new PayrollRun)->forceFill(['company_id' => 'company-a']);
        $actor = (new Employee)->forceFill(['company_id' => 'company-a', 'role' => 'ordinary']);

        $this->expectException(HttpException::class);
        $this->access->authorizeManager($run, $actor);
    }

    public function test_an_rh_manager_can_prepare_but_not_approve(): void
    {
        $run = (new PayrollRun)->forceFill(['company_id' => 'company-a']);
        $actor = (new Employee)->forceFill([
            'company_id' => 'company-a',
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        // Préparer (calculer) : autorisé.
        $this->access->authorizeManager($run, $actor);

        // Approuver (valider / clôturer) : refusé — séparation des tâches #5246.
        try {
            $this->access->authorizeApprover($run, $actor);
            self::fail('An rh manager must not be able to approve a payroll run.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_a_principal_manager_can_approve(): void
    {
        $run = (new PayrollRun)->forceFill(['company_id' => 'company-a']);
        $actor = (new Employee)->forceFill([
            'company_id' => 'company-a',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        // #9025 : `authorizeApprover` renvoie `void` — le succès est l'absence
        // d'exception. `assertTrue(true)` était une tautologie que PHPStan
        // niveau 8 rejette ; `expectNotToPerformAssertions()` énonce la même
        // intention sans assertion vide.
        $this->expectNotToPerformAssertions();
        $this->access->authorizeApprover($run, $actor);
    }

    public function test_a_comptable_manager_can_approve(): void
    {
        $run = (new PayrollRun)->forceFill(['company_id' => 'company-a']);
        $actor = (new Employee)->forceFill([
            'company_id' => 'company-a',
            'role' => 'manager',
            'manager_role' => 'comptable',
        ]);

        $this->expectNotToPerformAssertions();
        $this->access->authorizeApprover($run, $actor);
    }
}
