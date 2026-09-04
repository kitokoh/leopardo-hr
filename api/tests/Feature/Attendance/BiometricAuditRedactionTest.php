<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Models\BiometricAuditLog;
use App\Modules\Attendance\Domain\Models\BiometricEnrollment;
use App\Modules\Attendance\Infrastructure\Services\BiometricAuditLogger;
use App\Modules\Attendance\Infrastructure\Services\BiometricEnrollmentLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BIO-008 (#6773) — audit & observabilité biométrique sans fuite de données.
 *
 * Les logs ne contiennent ni capture, ni template, ni secret ; chaque
 * événement est rattachable à un tenant/salarié/appareil/corrélation ; le
 * contexte est filtré par allowlist (défense en profondeur).
 */
final class BiometricAuditRedactionTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_enrollment_transitions_are_audited_without_payload_leak(): void
    {
        $company = $this->makeCompany();
        $tenantManager = app(TenantManager::class);
        $service = app(BiometricEnrollmentLifecycleService::class);

        [$manager, $employee] = $this->makePeople($company);

        $template = '{"provider":"fake","template":"FACE-BINARY-SECRET-42"}';

        $tenantManager->withinTenant($company, function () use ($service, $employee, $manager, $template): void {
            $enrollment = $service->start(
                employee: $employee,
                method: VerificationMethod::Face,
                templatePayload: $template,
                provider: 'fake',
                actorEmployeeId: (int) $manager->id,
                correlationId: 'corr-audit-1',
            );
            $service->activate($enrollment, (int) $manager->id);
            $service->revoke($enrollment, (int) $manager->id);
        });

        DB::statement('SET search_path TO shared_tenants,public');
        $rows = BiometricAuditLog::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame([
            'biometric.enrollment.started',
            'biometric.enrollment.activated',
            'biometric.enrollment.revoked',
        ], $rows->pluck('event')->all());

        foreach ($rows as $row) {
            // Aucun contenu biométrique dans la ligne d'audit ni son contexte.
            $serialized = json_encode($row->toArray(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('FACE-BINARY-SECRET-42', $serialized);
            $this->assertStringNotContainsString('template', (string) json_encode($row->context));
        }

        // Corrélation rattachable.
        $this->assertSame('corr-audit-1', $rows->first()?->correlation_id);
    }

    public function test_logger_context_is_filtered_by_allowlist(): void
    {
        $company = $this->makeCompany();
        $logger = app(BiometricAuditLogger::class);

        $logger->log(
            companyId: (string) $company->id,
            event: 'verification.rejected',
            employeeId: 42,
            kioskId: 7,
            resultCode: 'VERIFICATION_REJECTED',
            correlationId: 'corr-x',
            context: [
                'version' => 3,
                'reason' => 'no_match',
                // Tentative de fuite : doit être filtrée par l'allowlist.
                'template' => 'FACE-BINARY-SECRET-42',
                'capture' => '/tmp/capture.jpg',
                'raw_provider_payload' => ['x' => 1],
            ],
        );

        DB::statement('SET search_path TO shared_tenants,public');
        $row = BiometricAuditLog::query()
            ->where('company_id', $company->id)
            ->firstOrFail();

        $this->assertSame(['version' => 3, 'reason' => 'no_match'], $row->context);
        $this->assertArrayNotHasKey('template', (array) $row->context);
        $this->assertArrayNotHasKey('capture', (array) $row->context);
        $this->assertSame('corr-x', $row->correlation_id);
        $this->assertSame(7, $row->kiosk_id);
        $this->assertSame('VERIFICATION_REJECTED', $row->result_code);

        // Les templates stockés ne fuient jamais dans la table d'audit.
        $this->assertSame(0, BiometricEnrollment::query()->count());
    }

    public function test_audit_rows_are_tenant_scoped(): void
    {
        $companyA = $this->makeCompany('a');
        $companyB = $this->makeCompany('b');
        $logger = app(BiometricAuditLogger::class);

        $logger->log(companyId: (string) $companyA->id, event: 'device.revoked', kioskId: 1);
        $logger->log(companyId: (string) $companyB->id, event: 'device.revoked', kioskId: 2);

        // Tenant A ne voit que ses lignes.
        app(TenantManager::class)->withinTenant($companyA, function (): void {
            $this->assertSame(1, BiometricAuditLog::query()->count());
        });

        app(TenantManager::class)->withinTenant($companyB, function (): void {
            $this->assertSame(1, BiometricAuditLog::query()->count());
        });
    }

    private function makeCompany(string $suffix = 'audit'): Company
    {
        $company = Company::query()->create([
            'name' => 'Company '.$suffix,
            'slug' => 'company-'.$suffix.'-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $suffix.'@audit.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        return $company;
    }

    /**
     * @return array{0: Employee, 1: Employee} [manager, employee]
     */
    private function makePeople(Company $company): array
    {
        $employee = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Audit',
            'email' => 'karim@audit.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Audit',
            'email' => 'manager@audit.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        return [$manager, $employee];
    }
}
