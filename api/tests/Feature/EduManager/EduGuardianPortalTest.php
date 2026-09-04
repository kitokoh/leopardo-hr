<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduGuardianPortalLink;
use App\Modules\EduManager\Domain\Models\EduPortalAccessLog;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5829 (EDU-013) — portail guardian : liens expirables + audit.
 *
 * Verrouille : génération réservée à la direction, token = credential (pas
 * d'auth), expiration/révocation → 404, résumé limité aux enfants autorisés
 * (jamais d'énumération), bulletins seulement si can_view_grades et publiés,
 * journal d'audit rempli à chaque consultation, isolation cross-tenant.
 */
class EduGuardianPortalTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $lambdaA;

    private EduGuardian $guardianA;

    private EduStudent $childA;

    private EduStudent $otherChild;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var EduGuardian $guardianA */
        $guardianA = EduGuardian::query()->create([
            'company_id' => $companyA->id,
            'first_name' => 'Samir',
            'last_name' => 'Benali',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
            'verified_at' => now(),
        ]);
        $this->guardianA = $guardianA;

        /** @var EduStudent $childA */
        $childA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-0001',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->childA = $childA;

        /** @var EduStudent $otherChild */
        $otherChild = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-0002',
            'display_name' => 'Adam Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->otherChild = $otherChild;

        EduStudentGuardian::query()->create([
            'company_id' => $companyA->id,
            'student_id' => (int) $childA->getAttribute('id'),
            'guardian_id' => (int) $guardianA->getAttribute('id'),
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
            'can_view_grades' => true,
            'can_receive_notifications' => true,
        ]);

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);

        /** @var EduClass $classA */
        $classA = EduClass::query()->create([
            'company_id' => $companyA->id,
            'academic_year_id' => (int) $yearA->getAttribute('id'),
            'code' => 'CP-A',
            'name' => 'CP A',
            'status' => EduClass::STATUS_ACTIVE,
        ]);

        EduAttendance::query()->create([
            'company_id' => $companyA->id,
            'class_id' => (int) $classA->getAttribute('id'),
            'student_id' => (int) $childA->getAttribute('id'),
            'attendance_date' => now()->toDateString(),
            'status' => EduAttendance::STATUS_PRESENT,
        ]);

        EduReportCard::query()->create([
            'company_id' => $companyA->id,
            'student_id' => (int) $childA->getAttribute('id'),
            'academic_year_id' => (int) $yearA->getAttribute('id'),
            'period' => EduReportCard::PERIOD_TERM1,
            'status' => EduReportCard::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    public function test_portal_link_generation_is_admin_only(): void
    {
        $guardianId = (int) $this->guardianA->getAttribute('id');

        // Lambda : 403.
        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl()."/guardians/{$guardianId}/portal-link")->assertStatus(403);

        // Direction : 201 avec token + expiration.
        Sanctum::actingAs($this->principalA);
        $response = $this->postJson($this->baseUrl()."/guardians/{$guardianId}/portal-link", [
            'expires_in_days' => 7,
        ])->assertStatus(201);

        $url = $response->json('data.url');
        $this->assertStringContainsString('/api/v1/edu-manager/portal/', $url);
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_portal_summary_returns_only_authorized_children(): void
    {
        $token = $this->createPortalToken();

        // Le token EST la credential : pas d'auth.
        $response = $this->getJson('/api/v1/edu-manager/portal/'.$token)
            ->assertOk()
            ->assertJsonPath('data.guardian.last_name', 'Benali');

        $children = $response->json('data.children');
        $this->assertCount(1, $children);
        $this->assertSame('Lina Benali', $children[0]['display_name']);
        // Adam (autre élève, non lié) n'apparaît pas — pas d'énumération.
        $this->assertNotContains('Adam Benali', array_column($children, 'display_name'));

        // Présence + bulletins publiés exposés (can_view_grades).
        $this->assertSame(1, $children[0]['attendance']['present']);
        $this->assertCount(1, $children[0]['report_cards']);

        // Audit : une consultation journalisée.
        $this->assertSame(1, EduPortalAccessLog::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_portal_token_expiry_and_revocation(): void
    {
        // Expiration passée → 404.
        $token = $this->createPortalToken();
        EduGuardianPortalLink::query()->where('portal_token', $token)->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/edu-manager/portal/'.$token)->assertStatus(404);

        // Révocation → 404.
        $token2 = $this->createPortalToken();
        EduGuardianPortalLink::query()->where('portal_token', $token2)->update(['revoked_at' => now()]);
        $this->getJson('/api/v1/edu-manager/portal/'.$token2)->assertStatus(404);

        // Token inconnu → 404.
        $this->getJson('/api/v1/edu-manager/portal/'.str_repeat('a', 64))->assertStatus(404);
    }

    public function test_portal_link_generation_is_tenant_isolated(): void
    {
        $guardianId = (int) $this->guardianA->getAttribute('id');

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);

        $this->postJson($this->baseUrl()."/guardians/{$guardianId}/portal-link")
            ->assertStatus(404);
    }

    private function createPortalToken(): string
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson(
            $this->baseUrl().'/guardians/'.(int) $this->guardianA->getAttribute('id').'/portal-link'
        )->assertStatus(201);

        $url = $response->json('data.url');

        return (string) substr((string) $url, strrpos((string) $url, '/') + 1);
    }
}
