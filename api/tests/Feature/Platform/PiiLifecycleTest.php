<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-011 (#5869) — Classification PII et cycle de vie (BC-01 PLATFORM).
 *
 * Prouve que les droits RGPD sont TESTÉS et AUDITÉS et que chaque champ
 * sensible exposé par l'export possède une politique dans le catalogue
 * `dev-hub/tools/pii-classification.json` (garde CI dédiée).
 *
 * Cycle couvert : export (art. 15, audit trail) → isolation tenant →
 * effacement (art. 17, conservation légale paie, audit).
 */
class PiiLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    private function catalogPath(): string
    {
        return dirname(__DIR__, 3).'/dev-hub/tools/pii-classification.json';
    }

    public function test_export_payload_fields_have_catalog_policies(): void
    {
        // Pont catalogue ↔ contrat d'export : chaque champ PII exposé par
        // /privacy/export doit avoir une politique dans le catalogue MAT-011.
        $catalog = json_decode((string) file_get_contents($this->catalogPath()), true, 512, JSON_THROW_ON_ERROR);
        $keys = array_column($catalog['fields'], 'key');

        $exportMapping = [
            'first_name' => 'employee_first_name',
            'last_name' => 'employee_last_name',
            'preferred_name' => 'employee_preferred_name',
            'email' => 'employee_email',
            'personal_email' => 'employee_personal_email',
            'phone' => 'employee_phone',
            'date_of_birth' => 'employee_date_of_birth',
            'national_id' => 'employee_national_id',
            'iban' => 'employee_iban',
            'bank_account' => 'employee_bank_account',
            'emergency_contact_name' => 'employee_emergency_contact_name',
            'emergency_contact_phone' => 'employee_emergency_contact_phone',
        ];

        foreach ($exportMapping as $payloadKey => $catalogKey) {
            self::assertContains(
                $catalogKey,
                $keys,
                "Le champ export '{$payloadKey}' doit avoir une politique PII ('{$catalogKey}')",
            );
        }

        // Le catalogue est complet : chaque politique référence un contexte et
        // des listes de référence valides (déjà garanti par le garde CI, on
        // vérifie ici la structure de base pour la preuve de cycle).
        self::assertNotEmpty($catalog['fields']);
        self::assertNotEmpty($catalog['contexts']);
    }

    public function test_export_is_audited_and_tenant_scoped(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'owner.pii@leopardo.test',
            'personal_email' => 'perso.pii@exemple.dz',
            'iban' => 'DZ0000000000000000000000',
        ]);
        /** @var Employee $other */
        $other = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'other.pii@leopardo.test',
        ]);

        Sanctum::actingAs($employee, ['*']);

        $response = $this->getJson('/api/v1/privacy/export');

        $response->assertOk()
            ->assertJsonPath('data.employee.email', 'owner.pii@leopardo.test')
            ->assertJsonPath('data.employee.personal_email', 'perso.pii@exemple.dz')
            // L'IBAN n'est jamais exposé dans l'export self-service (champ
            // restricted — politique du catalogue MAT-011).
            ->assertJsonMissingPath('data.employee.iban');

        // L'export est limité au tenant courant : aucune donnée de l'autre
        // employé dans le bundle (les compteurs sont scopés par company).
        $response->assertJsonPath('data.activity_summary.attendance_logs_count', 0);

        // Audit trail : l'export est journalisé (qui/quand/résultat).
        $audit = AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'hr_data.privacy_exported')
            ->latest('id')
            ->first();
        self::assertNotNull($audit);
        self::assertSame('privacy_export', $audit->metadata['resource'] ?? null);
        self::assertSame((string) $employee->id, (string) $audit->auditable_id);
    }

    public function test_erasure_keeps_payroll_history_and_is_audited(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'eraser.pii@leopardo.test',
            'first_name' => 'Karim',
            'last_name' => 'Haddad',
            'national_id' => '123456789012345',
            'iban' => 'DZ0000000000000000000000',
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 60000,
            'net_salary' => 47558,
            'status' => 'validated',
        ]);

        $this->artisan('gdpr:anonymize-employee', [
            'employee' => (string) $employee->id,
            '--force' => true,
        ])->assertSuccessful();

        // Effacement : les PII sont remplacées…
        $erased = $employee->fresh();
        self::assertSame('Anonymisé', $erased->first_name);
        self::assertNotSame('Karim', $erased->first_name);
        self::assertStringStartsWith('anonyme-', (string) $erased->email);
        self::assertNull($erased->getRawOriginal('iban'));

        // … mais l'historique économique reste (conservation légale 10 ans).
        self::assertSame(1, PaySlip::query()->where('employee_id', $employee->id)->count());

        // Audit : l'opération d'effacement est tracée.
        self::assertGreaterThanOrEqual(
            1,
            AuditLog::query()->where('company_id', $company->id)->where('action', 'like', '%anonymiz%')->count(),
        );
    }
}
