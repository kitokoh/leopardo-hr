<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Enums\BiometricEnrollmentStatus;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Exceptions\DuplicatePendingBiometricEnrollmentException;
use App\Modules\Attendance\Domain\Exceptions\NonBiometricEnrollmentMethodException;
use App\Modules\Attendance\Domain\Models\BiometricEnrollment;
use App\Modules\Attendance\Infrastructure\Services\BiometricEnrollmentLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BIO-002 (#6763) + BIO-003 (#6764) — cycle de vie des enrôlements
 * biométriques versionnés, stockage chiffré tenant-scoped.
 *
 * Scénarios : démarrage pending (gabarit chiffré au repos), idempotence par
 * corrélation, activation avec remplacement (révocation de l'ancien actif,
 * version incrémentée), révocation, unicité de l'actif (index partiel),
 * isolation cross-tenant et audit sans gabarit.
 */
final class BiometricEnrollmentLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    private const TEMPLATE_PLAINTEXT = '{"provider":"fake","template":"facetemplate-binary-v3"}';

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    private TenantManager $tenantManager;

    private BiometricEnrollmentLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = app(TenantManager::class);
        $this->service = app(BiometricEnrollmentLifecycleService::class);

        $this->company = Company::query()->create([
            'name' => 'Company Bio A',
            'slug' => 'company-bio-a',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@bio.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->manager = $this->makeEmployee('manager@bio.test', 'manager');
        $this->employee = $this->makeEmployee('karim@bio.test', 'employee');
    }

    public function test_start_stores_template_encrypted_at_rest(): void
    {
        $this->withinTenant(function (): void {
            $enrollment = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
                correlationId: 'corr-start-1',
            );

            $this->assertSame(BiometricEnrollmentStatus::Pending, $enrollment->status);
            $this->assertSame(1, $enrollment->version);

            // Chiffré au repos : la valeur brute en base ne contient pas le
            // gabarit en clair (BIO-003).
            $raw = DB::table('biometric_enrollments')->where('id', $enrollment->id)->value('template');
            $this->assertIsString($raw);
            $this->assertStringNotContainsString('facetemplate-binary-v3', $raw);
            $this->assertNotSame(self::TEMPLATE_PLAINTEXT, $raw);

            // Décryptage applicatif fonctionnel pour le service autorisé.
            $this->assertSame(self::TEMPLATE_PLAINTEXT, $enrollment->fresh()?->template);

            // Le gabarit n'est pas exposé par une sérialisation métier.
            $this->assertArrayNotHasKey('template', $enrollment->toArray());
        });
    }

    public function test_start_rejects_non_biometric_method(): void
    {
        $this->withinTenant(function (): void {
            $this->expectException(NonBiometricEnrollmentMethodException::class);

            $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Badge,
                templatePayload: 'x',
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
        });
    }

    public function test_duplicate_pending_is_rejected_but_same_correlation_is_idempotent(): void
    {
        $this->withinTenant(function (): void {
            $first = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
                correlationId: 'corr-dup-1',
            );

            // Rejeu (même corrélation) → même enrôlement, pas de doublon.
            $replayed = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
                correlationId: 'corr-dup-1',
            );
            $this->assertSame($first->id, $replayed->id);

            // Nouvelle demande sans corrélation → refus.
            $this->expectException(DuplicatePendingBiometricEnrollmentException::class);
            $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
        });
    }

    public function test_activate_revokes_previous_active_and_increments_version(): void
    {
        $this->withinTenant(function (): void {
            $first = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
            $this->service->activate($first, (int) $this->manager->id);
            $this->assertSame(BiometricEnrollmentStatus::Active, $first->fresh()?->status);

            // Remplacement : le nouveau gabarit devient actif, l'ancien est
            // révoqué, la version est incrémentée.
            $second = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: '{"version":2,"template":"newer"}',
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
            $this->assertSame(2, $second->version);

            $this->service->activate($second, (int) $this->manager->id);

            $this->assertSame(BiometricEnrollmentStatus::Revoked, $first->fresh()?->status);
            $this->assertNotNull($first->fresh()?->revoked_at);

            $active = BiometricEnrollment::query()
                ->where('company_id', $this->company->id)
                ->where('employee_id', $this->employee->id)
                ->where('method', VerificationMethod::Face->value)
                ->where('status', BiometricEnrollmentStatus::Active)
                ->get();

            // Un seul actif par employé + méthode (BIO-002).
            $this->assertCount(1, $active);
            $this->assertSame($second->id, $active->first()?->id);
        });
    }

    public function test_database_enforces_single_active_enrollment(): void
    {
        $this->withinTenant(function (): void {
            $first = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
            $this->service->activate($first, (int) $this->manager->id);

            // Contournement direct de la couche service : l'index unique
            // partiel doit refuser un second actif. La violation attendue est
            // isolée dans sa propre transaction (rollback) pour ne pas
            // empoisonner la transaction du test.
            DB::beginTransaction();
            try {
                BiometricEnrollment::query()->create([
                    'company_id' => $this->company->id,
                    'employee_id' => $this->employee->id,
                    'method' => VerificationMethod::Face->value,
                    'status' => BiometricEnrollmentStatus::Active,
                    'version' => 99,
                    'template' => 'hack',
                    'provider' => 'fake',
                ]);
                DB::commit();
                $this->fail('L\'index unique partiel aurait dû refuser un second enrôlement actif.');
            } catch (QueryException) {
                DB::rollBack();
                $this->addToAssertionCount(1);
            }
        });
    }

    public function test_revoked_enrollment_cannot_be_used_for_punch(): void
    {
        $this->withinTenant(function (): void {
            $enrollment = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
            $this->service->activate($enrollment, (int) $this->manager->id);

            $usableBefore = BiometricEnrollment::query()
                ->usableFor((int) $this->employee->id, VerificationMethod::Face->value)
                ->count();
            $this->assertSame(1, $usableBefore);

            $this->service->revoke($enrollment->fresh() ?? $enrollment, (int) $this->manager->id);

            $usableAfter = BiometricEnrollment::query()
                ->usableFor((int) $this->employee->id, VerificationMethod::Face->value)
                ->count();
            $this->assertSame(0, $usableAfter);
            $this->assertNotNull($enrollment->fresh()?->revoked_at);
        });
    }

    public function test_cross_tenant_isolation(): void
    {
        $this->withinTenant(function (): void {
            $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
        });

        // Tenant B : aucune lecture possible de l'enrôlement du tenant A.
        $companyB = Company::query()->create([
            'name' => 'Company Bio B',
            'slug' => 'company-bio-b',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@bio.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        $this->tenantManager->withinTenant($companyB, function (): void {
            $visible = BiometricEnrollment::query()
                ->where('company_id', $this->company->id)
                ->get();

            // Le scope global BelongsToCompany restreint aux enrôlements du
            // tenant courant (B) : le where explicite sur A ne remonte rien.
            $this->assertCount(0, $visible);

            $this->assertSame(0, BiometricEnrollment::query()->count());
        });

        // Et le tenant A voit toujours son enrôlement.
        $this->withinTenant(function (): void {
            $this->assertSame(1, BiometricEnrollment::query()->count());
        });
    }

    public function test_transitions_are_audited_without_template_leak(): void
    {
        $this->withinTenant(function (): void {
            $enrollment = $this->service->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: self::TEMPLATE_PLAINTEXT,
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
            );
            $this->service->activate($enrollment, (int) $this->manager->id);
            $this->service->revoke($enrollment, (int) $this->manager->id);
        });

        $audits = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('module', 'attendance')
            ->where('action', 'like', 'biometric.enrollment.%')
            ->orderBy('id')
            ->get();

        $actions = $audits->pluck('action')->all();
        $this->assertSame([
            'biometric.enrollment.started',
            'biometric.enrollment.activated',
            'biometric.enrollment.revoked',
        ], $actions);

        foreach ($audits as $audit) {
            $this->assertArrayNotHasKey('template', (array) $audit->metadata);
            $this->assertArrayNotHasKey('capture', (array) $audit->metadata);
        }
    }

    private function makeEmployee(string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'Bio',
            'email' => $email,
            'company_id' => $this->company->id,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $this->company->id,
            'role' => $role,
            'manager_role' => $role === 'manager' ? 'principal' : null,
            'status' => 'active',
        ])->save();

        return $employee;
    }

    private function withinTenant(\Closure $callback): void
    {
        $this->tenantManager->withinTenant($this->company, $callback);
    }
}
