<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAdmissionFollowup;
use App\Modules\EduManager\Infrastructure\Services\EduAdmissionFollowupService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5831 (EDU-015) — marketing admissions : relances consenties.
 *
 * Verrouille : consentement obligatoire, idempotence par
 * (admission, campagne, canal), opt-out RGPD (consent_revoked_at + statuts
 * opted_out), événements outbox versionnés, RBAC direction, isolation.
 */
class EduAdmissionCampaignTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $lambdaA;

    private EduAdmission $admissionConsented;

    private EduAdmission $admissionNoConsent;

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

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);

        $this->admissionConsented = $this->makeAdmission($companyA->id, (int) $yearA->getAttribute('id'), true);
        $this->admissionNoConsent = $this->makeAdmission($companyA->id, (int) $yearA->getAttribute('id'), false);
    }

    private function makeAdmission(string $companyId, int $yearId, bool $consent): EduAdmission
    {
        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create([
            'company_id' => $companyId,
            'academic_year_id' => $yearId,
            'admission_number' => 'ADM-'.uniqid('', false),
            'applicant_first_name' => 'Lina',
            'applicant_last_name' => 'Benali',
            'applicant_email' => 'lina@example.com',
            'applied_at' => '2026-09-01',
            'consent_contact' => $consent,
            'consented_at' => $consent ? now() : null,
            'status' => EduAdmission::STATUS_REVIEW,
        ]);

        return $admission;
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    public function test_followup_requires_consent_and_is_idempotent(): void
    {
        Sanctum::actingAs($this->principalA);
        $url = $this->baseUrl();
        $consentedId = (int) $this->admissionConsented->getAttribute('id');

        // Sans consentement : 422.
        $this->postJson($url.'/admissions/'.(int) $this->admissionNoConsent->getAttribute('id').'/follow-ups', [
            'campaign_code' => 'ADM-2026',
            'channel' => 'email',
        ])->assertStatus(422)->assertJsonPath('error', 'EDU_CONSENT_REQUIRED');

        // Avec consentement : 201, puis rejeu idempotent.
        $payload = ['campaign_code' => 'ADM-2026', 'channel' => 'email'];
        $first = $this->postJson($url."/admissions/{$consentedId}/follow-ups", $payload)->assertStatus(201);
        $second = $this->postJson($url."/admissions/{$consentedId}/follow-ups", $payload)->assertStatus(201);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, EduAdmissionFollowup::query()->where('company_id', $this->companyA->id)->count());

        // Événement outbox versionné publié (edu.admission.followup.v1).
        $this->assertSame(1, \App\Modules\EduManager\Domain\Models\EduOutboxEvent::query()
            ->where('company_id', $this->companyA->id)
            ->where('event_type', EduAdmissionFollowupService::EVENT_FOLLOWUP)
            ->count());
    }

    public function test_opt_out_revokes_consent_and_flips_followups(): void
    {
        Sanctum::actingAs($this->principalA);
        $url = $this->baseUrl();
        $consentedId = (int) $this->admissionConsented->getAttribute('id');

        $this->postJson($url."/admissions/{$consentedId}/follow-ups", [
            'campaign_code' => 'ADM-2026',
            'channel' => 'sms',
        ])->assertStatus(201);

        $this->postJson($url."/admissions/{$consentedId}/opt-out")
            ->assertOk()
            ->assertJsonPath('data.consent_contact', false)
            ->assertJsonPath('data.consent_revoked_at', fn ($value) => $value !== null);

        // Plus aucune relance possible après opt-out.
        $this->postJson($url."/admissions/{$consentedId}/follow-ups", [
            'campaign_code' => 'ADM-2027',
            'channel' => 'email',
        ])->assertStatus(422)->assertJsonPath('error', 'EDU_CONSENT_REVOKED');

        // La relance précédente est passée à opted_out.
        $this->assertSame(
            EduAdmissionFollowup::STATUS_OPTED_OUT,
            EduAdmissionFollowup::query()->where('company_id', $this->companyA->id)->value('status')
        );
    }

    public function test_followups_are_admin_only_and_tenant_isolated(): void
    {
        // Employé lambda : 403.
        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl().'/admissions/'.(int) $this->admissionConsented->getAttribute('id').'/follow-ups', [
            'campaign_code' => 'ADM-2026',
            'channel' => 'email',
        ])->assertStatus(403);

        // Manager du tenant B : 404 sur un dossier du tenant A.
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);
        $this->postJson($this->baseUrl().'/admissions/'.(int) $this->admissionConsented->getAttribute('id').'/follow-ups', [
            'campaign_code' => 'ADM-2026',
            'channel' => 'email',
        ])->assertStatus(404);
    }
}
