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
use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\EduManager\Domain\Models\EduGuardianAccessLink;
use Illuminate\Support\Facades\DB;
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
 * Portail guardian — Issue #5829 (EDU-013).
 *
 * Acceptation : « liens d'accès expirables ; consentement et audit ;
 * aucune énumération d'élèves ». Couvre l'émission (direction seule,
 * TTL borné), la consommation atomique (usage unique, replay 410,
 * expiration 410, lien inconnu 404), le consentement RGPD (verified_at),
 * l'audit (émission + accès) et la confidentialité (bulletins publiés
 * uniquement et conditionnés à can_view_grades ; enfants liés uniquement).
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
    private Company $company;

    private Company $otherCompany;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create([
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
        $this->otherCompany = $other;

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principal = $principal;
    }

    public function test_direction_can_emit_access_link(): void
    {
        Sanctum::actingAs($this->principal);

        $guardian = $this->guardian($this->company);

        $response = $this->postJson('/api/v1/edu-manager/guardians/'.$guardian->id.'/access-links', [
            'expires_in_days' => 3,
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertIsString($data['token']);
        $this->assertSame(64, strlen((string) $data['token']));
        $this->assertStringContainsString((string) $data['token'], (string) $data['portal_url']);
        $this->assertSame(3, $data['expires_in_days']);

        // Le token brut n'est JAMAIS persisté : seule l'empreinte SHA-256 l'est.
        $row = DB::table('edu_guardian_access_links')
            ->where('guardian_id', $guardian->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(hash('sha256', (string) $data['token']), $row->token_hash);
        $this->assertNotSame($data['token'], $row->token_hash);

        // Audit d'émission.
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'edu.access_link_created',
            'module' => 'edu_manager',
        ]);
    }

    public function test_ttl_is_bounded(): void
    {
        Sanctum::actingAs($this->principal);

        $guardian = $this->guardian($this->company);

        $this->postJson('/api/v1/edu-manager/guardians/'.$guardian->id.'/access-links', [
            'expires_in_days' => 0,
        ])->assertStatus(422);

        $this->postJson('/api/v1/edu-manager/guardians/'.$guardian->id.'/access-links', [
            'expires_in_days' => 31,
        ])->assertStatus(422);

        // Défaut : 7 jours.
        $response = $this->postJson('/api/v1/edu-manager/guardians/'.$guardian->id.'/access-links');
        $response->assertStatus(201);
        $this->assertSame(7, $response->json('data.expires_in_days'));
    }

    public function test_plain_employee_cannot_emit_access_link(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $guardian = $this->guardian($this->company);

        $this->postJson('/api/v1/edu-manager/guardians/'.$guardian->id.'/access-links')
            ->assertStatus(403);
    }

    public function test_cross_tenant_manager_cannot_emit_access_link(): void
    {
        /** @var Employee $otherPrincipal */
        $otherPrincipal = Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($otherPrincipal);

        $guardian = $this->guardian($this->company);

        $this->postJson('/api/v1/edu-manager/guardians/'.$guardian->id.'/access-links')
            ->assertStatus(404);
    }

    public function test_consume_returns_only_linked_children(): void
    {
        Sanctum::actingAs($this->principal);

        $linked = $this->student('S-001');
        $unlinked = $this->student('S-002');
        $guardian = $this->guardian($this->company);
        EduStudentGuardian::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $linked->id,
            'guardian_id' => $guardian->id,
            'can_view_grades' => true,
        ]);

        $token = $this->emit($guardian);

        $response = $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token.'/consume');

        $response->assertStatus(200);
        $children = $response->json('data.children');
        $this->assertCount(1, $children);
        $this->assertSame($linked->id, $children[0]['id']);
        $this->assertSame('S-001', $children[0]['student_number']);
        // Aucune énumération : l'élève non lié n'apparaît pas.
        $this->assertNotContains($unlinked->id, array_column($children, 'id'));
        $this->assertSame($guardian->id, $response->json('data.guardian.id'));
    }

    public function test_consume_sets_consent_and_audits(): void
    {
        Sanctum::actingAs($this->principal);

        $guardian = $this->guardian($this->company);
        $this->assertNull($guardian->verified_at);

        $token = $this->emit($guardian);

        $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token.'/consume')
            ->assertStatus(200);

        // Consentement RGPD posé une seule fois.
        $this->assertNotNull($guardian->refresh()->verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'edu.portal_access',
            'module' => 'edu_manager',
        ]);
    }

    public function test_replay_is_rejected(): void
    {
        Sanctum::actingAs($this->principal);

        $guardian = $this->guardian($this->company);
        $token = $this->emit($guardian);

        $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token.'/consume')
            ->assertStatus(200);

        // Replay du même lien : 410.
        $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token.'/consume')
            ->assertStatus(410);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'edu.portal_access',
            'module' => 'edu_manager',
        ]);
    }

    public function test_expired_link_is_rejected(): void
    {
        Sanctum::actingAs($this->principal);

        $guardian = $this->guardian($this->company);
        $token = $this->emit($guardian);

        EduGuardianAccessLink::query()
            ->where('company_id', $this->company->id)
            ->where('guardian_id', $guardian->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token.'/consume')
            ->assertStatus(410);
    }

    public function test_unknown_link_is_rejected(): void
    {
        $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.str_repeat('a', 64).'/consume')
            ->assertStatus(404);
    }

    public function test_report_cards_are_published_only_and_grade_gated(): void
    {
        Sanctum::actingAs($this->principal);

        $student = $this->student('S-001');
        $guardian = $this->guardian($this->company);
        EduStudentGuardian::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'can_view_grades' => false,
        ]);

        EduReportCard::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
            'average' => 14.5,
            'status' => EduReportCard::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        EduReportCard::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM2,
            'average' => 12.0,
            'status' => EduReportCard::STATUS_DRAFT,
        ]);

        $token = $this->emit($guardian);

        $response = $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token.'/consume');

        $response->assertStatus(200);
        $child = $response->json('data.children.0');
        // can_view_grades=false → aucun bulletin renvoyé.
        $this->assertSame([], $child['report_cards']);

        // Avec can_view_grades=true → UNIQUEMENT le bulletin publié.
        EduStudentGuardian::query()
            ->where('company_id', $this->company->id)
            ->where('student_id', $student->id)
            ->update(['can_view_grades' => true]);

        $token2 = $this->emit($guardian);
        $response2 = $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token2.'/consume');
        $cards = $response2->json('data.children.0.report_cards');
        $this->assertCount(1, $cards);
        $this->assertSame('term1', $cards[0]['period']);
    }

    public function test_guardian_without_employee_can_consume(): void
    {
        // Un responsable légal n'est PAS un Employee (EDU-013) : le portail
        // fonctionne sans compte, via le lien.
        Sanctum::actingAs($this->principal);

        $student = $this->student('S-001');
        $guardian = $this->guardian($this->company);
        EduStudentGuardian::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
        ]);

        $token = $this->emit($guardian);

        $this->postJson('/api/v1/edu-manager/guardian-portal/access-links/'.$token.'/consume')
            ->assertStatus(200)
            ->assertJsonPath('data.children.0.id', $student->id);
    }

    private function student(string $number): EduStudent
    {
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $this->company->id,
            'student_number' => $number,
            'display_name' => 'Élève '.$number,
        ]);

        return $student;
    }

    private function guardian(Company $company): EduGuardian
    {
        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Parent',
            'last_name' => 'Test',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
        ]);

        return $guardian;
    }

    private function emit(EduGuardian $guardian): string
    {
        $response = $this->postJson('/api/v1/edu-manager/guardians/'.$guardian->id.'/access-links');

        return (string) $response->json('data.token');
    }
}
