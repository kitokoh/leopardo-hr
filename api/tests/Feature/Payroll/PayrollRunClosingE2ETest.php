<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5150 — clôture DZ de bout en bout via l'API, MOTEUR RÉEL :
 *   draft → calculé → validée (étape RH) → verrouillée (étape comptable)
 *   → revert (déverrouillage motivé) → re-validée, sans perte.
 *
 * Différence avec les tests existants :
 *   - PayrollRunClosingApiTest construit des runs/bulletins à la main
 *     (statuts posés directement) ;
 *   - PayrollClosingTest exerce le service sans passer par le stack HTTP ;
 *   - ici, TOUT le flux HTTP est traversé avec le vrai PayrollCalculator
 *     (création → calcul → validation → verrouillage → déverrouillage →
 *     re-verrouillage) + archivage Cabinet des bulletins au verrouillage.
 */
class PayrollRunClosingE2ETest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $comptable;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->comptable = $comptable;

        // Grille salariale DZ active + employé rémunéré → le moteur réel
        // génère de vrais bulletins calculés (pattern PayrollClosingTest).
        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille par défaut (test E2E clôture)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
        ]);
    }

    private function createDraftRun(): int
    {
        $response = $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'notes' => 'Clôture mensuelle juillet 2026 (E2E #5150)',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', PayrollRun::STATUS_DRAFT);

        return (int) $response->json('data.id');
    }

    public function test_full_closing_round_trip_via_api_with_real_engine(): void
    {
        Storage::fake('private');

        Sanctum::actingAs($this->comptable);

        $runId = $this->createDraftRun();

        // 1. Calcul (moteur réel) : draft → calculated + bulletins réels.
        $calculated = $this->postJson("/api/v1/payroll-runs/{$runId}/calculate")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_CALCULATED);
        $slipsCount = (int) $calculated->json('data.pay_slips_count');
        $this->assertGreaterThanOrEqual(1, $slipsCount);
        $totalNet = $calculated->json('data.total_net');

        // 2. Étape 1 — validation RH : calculated → validated, bulletins basculés.
        $this->postJson("/api/v1/payroll-runs/{$runId}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_VALIDATED);
        $this->assertSame(
            $slipsCount,
            PaySlip::query()->where('payroll_run_id', $runId)->where('status', 'validated')->count()
        );

        // 3. Étape 2 — clôture comptable : validated → locked.
        $this->postJson("/api/v1/payroll-runs/{$runId}/lock")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_LOCKED)
            ->assertJsonPath('data.locked_by', $this->comptable->id);

        // 4. Revert : déverrouillage motivé → validated (raison tracée).
        $this->postJson("/api/v1/payroll-runs/{$runId}/unlock", [
            'reason' => 'Erreur de paramétrage détectée après clôture (E2E)',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_VALIDATED)
            ->assertJsonPath('data.locked_by', null);

        // 5. Re-validation : re-verrouillage possible, SANS perte.
        $reLocked = $this->postJson("/api/v1/payroll-runs/{$runId}/lock")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_LOCKED);
        $this->assertSame($totalNet, $reLocked->json('data.total_net'));
        $this->assertSame($slipsCount, (int) $reLocked->json('data.pay_slips_count'));

        // 6. Audit trail complet, ordonné, sans perte d'événements.
        $actions = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('auditable_type', (new PayrollRun)->getMorphClass())
            ->where('auditable_id', $runId)
            ->orderBy('id')
            ->pluck('action')
            ->all();
        $this->assertSame([
            'payroll_run_validated',
            'payroll_run_locked',
            'payroll_run_unlocked',
            'payroll_run_locked',
        ], $actions);
    }

    /**
     * Régression #5150 : via le flux API, les bulletins sont `validated`
     * (validateRun bascule les statuts AVANT lock) — le job d'archivage
     * Cabinet déclenché au verrouillage doit les archiver malgré tout
     * (filtre élargi à calculated/validated/sent, plus `calculated` seul).
     */
    public function test_lock_archives_validated_slips_to_cabinet_via_api_flow(): void
    {
        Storage::fake('private');

        Sanctum::actingAs($this->comptable);

        $runId = $this->createDraftRun();

        $this->postJson("/api/v1/payroll-runs/{$runId}/calculate")->assertOk();
        $this->postJson("/api/v1/payroll-runs/{$runId}/validate")->assertOk();
        $this->postJson("/api/v1/payroll-runs/{$runId}/lock")->assertOk();

        // Le job a tourné en queue sync au verrouillage : un document
        // Cabinet read-only par bulletin du run.
        $slipCount = PaySlip::query()->where('payroll_run_id', $runId)->count();
        $this->assertGreaterThanOrEqual(1, $slipCount);

        $docs = CabinetDocument::query()
            ->where('document_type', 'payslip')
            ->whereNotNull('source_id')
            ->get();
        $this->assertCount($slipCount, $docs);
        foreach ($docs as $doc) {
            $this->assertTrue($doc->read_only);
            Storage::disk('private')->assertExists($doc->path);
        }
    }
}
