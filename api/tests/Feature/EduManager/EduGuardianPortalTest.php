<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduGuardianAccessLink;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
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

    private Company $company;

    private Company $otherCompany;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
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
