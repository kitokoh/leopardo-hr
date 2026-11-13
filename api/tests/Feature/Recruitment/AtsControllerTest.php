<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\ApplicantStatusEvent;
use App\Modules\Recruitment\Domain\Models\AtsSavedFilter;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * ATS — parcours recruteur.
 *
 * Ces tests verrouillent les trois propriétés qui font qu'un ATS est utilisable
 * et pas seulement décoratif :
 *
 * 1. le pipeline se filtre réellement (statut, note, recherche, ancienneté) ;
 * 2. un changement de statut laisse une trace attribuable (qui, quand, pourquoi) ;
 * 3. l'isolation tenant tient : un recruteur ne voit ni ne déplace le dossier
 *    d'une autre entreprise.
 */
class AtsControllerTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $recruiter;

    private JobPosting $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();
        $this->recruiter = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'rh',
            'first_name' => 'Amina',
            'last_name' => 'Recruteuse',
        ]);

        $this->job = JobPosting::create([
            'company_id' => $this->company->id,
            'created_by' => $this->recruiter->id,
            'title' => 'Developpeur Backend',
            'status' => 'published',
            'skills_required' => ['PHP', 'Laravel', 'PostgreSQL'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function applicant(array $overrides = []): Applicant
    {
        return Applicant::create([
            'company_id' => $this->company->id,
            'job_posting_id' => $this->job->id,
            'first_name' => 'Nadia',
            'last_name' => 'Candidate',
            'email' => 'nadia'.uniqid().'@example.com',
            'status' => 'new',
            'source' => 'website',
            'applied_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_pipeline_lists_applicants_of_the_company_only(): void
    {
        $this->applicant();
        $otherCompany = Company::factory()->create();
        $foreignJob = JobPosting::create([
            'company_id' => $otherCompany->id,
            'title' => 'Foreign',
            'status' => 'published',
        ]);
        Applicant::create([
            'company_id' => $otherCompany->id,
            'job_posting_id' => $foreignJob->id,
            'first_name' => 'Foreign',
            'last_name' => 'Candidate',
            'email' => 'foreign@example.com',
            'status' => 'new',
            'applied_at' => now(),
        ]);

        Sanctum::actingAs($this->recruiter);

        $response = $this->getJson('/api/v1/recruitment/ats/applicants');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.last_name', 'Candidate');
    }

    public function test_pipeline_filters_by_status(): void
    {
        $this->applicant(['status' => 'new']);
        $this->applicant(['status' => 'screening']);
        $this->applicant(['status' => 'rejected']);

        Sanctum::actingAs($this->recruiter);

        $response = $this->getJson('/api/v1/recruitment/ats/applicants?statuses[]=screening&statuses[]=rejected');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.counts.new', 1);
        $response->assertJsonPath('meta.counts.screening', 1);
        $response->assertJsonPath('meta.counts.total', 3);
    }

    public function test_pipeline_filters_by_minimum_rating(): void
    {
        $this->applicant(['rating' => 5]);
        $this->applicant(['rating' => 2]);
        $this->applicant(['rating' => null]);

        Sanctum::actingAs($this->recruiter);

        $response = $this->getJson('/api/v1/recruitment/ats/applicants?min_rating=4');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.rating', 5);
    }

    public function test_pipeline_searches_by_name_and_email(): void
    {
        $this->applicant(['first_name' => 'Yacine', 'last_name' => 'Bensalem']);
        $this->applicant(['first_name' => 'Fatima', 'last_name' => 'Zohra']);

        Sanctum::actingAs($this->recruiter);

        $this->getJson('/api/v1/recruitment/ats/applicants?q=bensalem')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Yacine');
    }

    public function test_search_term_wildcards_are_escaped(): void
    {
        $this->applicant(['first_name' => 'Karim', 'last_name' => 'Test']);
        $this->applicant(['first_name' => 'Sara', 'last_name' => 'Autre']);

        Sanctum::actingAs($this->recruiter);

        // `%` est un joker LIKE : non échappé, il ferait correspondre TOUS les
        // dossiers. Le filtre doit le traiter comme un caractère littéral.
        $this->getJson('/api/v1/recruitment/ats/applicants?q=%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pipeline_filters_by_age(): void
    {
        $this->applicant(['applied_at' => now()->subDays(40)]);
        $this->applicant(['applied_at' => now()->subDays(2)]);

        Sanctum::actingAs($this->recruiter);

        $this->getJson('/api/v1/recruitment/ats/applicants?older_than_days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/recruitment/ats/applicants?newer_than_days=7')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_job_pipeline_is_scoped_to_the_job(): void
    {
        $this->applicant();
        $otherJob = JobPosting::create([
            'company_id' => $this->company->id,
            'title' => 'Designer',
            'status' => 'published',
        ]);
        Applicant::create([
            'company_id' => $this->company->id,
            'job_posting_id' => $otherJob->id,
            'first_name' => 'Autre',
            'last_name' => 'Poste',
            'email' => 'autre@example.com',
            'status' => 'new',
            'applied_at' => now(),
        ]);

        Sanctum::actingAs($this->recruiter);

        $response = $this->getJson("/api/v1/recruitment/ats/jobs/{$this->job->id}/applicants");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.job.title', 'Developpeur Backend');
    }

    public function test_status_change_is_journalised_with_actor_and_reason(): void
    {
        $applicant = $this->applicant(['status' => 'new']);

        Sanctum::actingAs($this->recruiter);

        $this->patchJson("/api/v1/recruitment/ats/applicants/{$applicant->id}/status", [
            'status' => 'screening',
            'reason' => 'Profil interessant, a rappeler',
        ])->assertOk()->assertJsonPath('data.status', 'screening');

        $event = ApplicantStatusEvent::query()
            ->where('applicant_id', $applicant->id)
            ->where('to_status', 'screening')
            ->first();

        $this->assertNotNull($event, 'Le changement de statut doit laisser une trace.');
        $this->assertSame('new', $event->from_status);
        $this->assertSame($this->recruiter->id, $event->actor_id);
        $this->assertSame('Amina Recruteuse', $event->actor_name);
        $this->assertSame('Profil interessant, a rappeler', $event->reason);
    }

    public function test_setting_the_same_status_does_not_create_a_noise_event(): void
    {
        $applicant = $this->applicant(['status' => 'screening']);

        Sanctum::actingAs($this->recruiter);

        $this->patchJson("/api/v1/recruitment/ats/applicants/{$applicant->id}/status", [
            'status' => 'screening',
        ])->assertOk();

        $this->assertSame(
            0,
            ApplicantStatusEvent::query()->where('applicant_id', $applicant->id)->count(),
            'Un statut inchangé ne doit pas polluer l historique.',
        );
    }

    public function test_applicant_detail_exposes_history_and_match_score(): void
    {
        $applicant = $this->applicant([
            'status' => 'screening',
            'cover_letter' => 'Je travaille en PHP et Laravel depuis six ans.',
        ]);

        ApplicantStatusEvent::create([
            'company_id' => $this->company->id,
            'applicant_id' => $applicant->id,
            'from_status' => null,
            'to_status' => 'new',
            'occurred_at' => now()->subDays(3),
        ]);
        ApplicantStatusEvent::create([
            'company_id' => $this->company->id,
            'applicant_id' => $applicant->id,
            'from_status' => 'new',
            'to_status' => 'screening',
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($this->recruiter);

        $response = $this->getJson("/api/v1/recruitment/ats/applicants/{$applicant->id}");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'screening');
        $response->assertJsonCount(2, 'data.status_history');
        // 2 compétences sur 3 présentes dans la lettre (PHP, Laravel) → 67 %.
        $response->assertJsonPath('meta.match.score', 67);
        $response->assertJsonPath('meta.match.missing', ['PostgreSQL']);
    }

    public function test_match_score_is_null_when_the_job_declares_no_skill(): void
    {
        $job = JobPosting::create([
            'company_id' => $this->company->id,
            'title' => 'Sans competences',
            'status' => 'published',
        ]);
        $applicant = $this->applicant(['job_posting_id' => $job->id]);

        Sanctum::actingAs($this->recruiter);

        $this->getJson("/api/v1/recruitment/ats/applicants/{$applicant->id}")
            ->assertOk()
            ->assertJsonPath('meta.match.score', null);
    }

    public function test_recruiter_cannot_touch_another_tenant_applicant(): void
    {
        $otherCompany = Company::factory()->create();
        $foreignJob = JobPosting::create([
            'company_id' => $otherCompany->id,
            'title' => 'Foreign',
            'status' => 'published',
        ]);
        $foreign = Applicant::create([
            'company_id' => $otherCompany->id,
            'job_posting_id' => $foreignJob->id,
            'first_name' => 'Foreign',
            'last_name' => 'Candidate',
            'email' => 'foreign@example.com',
            'status' => 'new',
            'applied_at' => now(),
        ]);

        Sanctum::actingAs($this->recruiter);

        $this->getJson("/api/v1/recruitment/ats/applicants/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/v1/recruitment/ats/applicants/{$foreign->id}/status", [
            'status' => 'hired',
        ])->assertNotFound();
    }

    public function test_plain_employee_cannot_read_the_pipeline(): void
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/recruitment/ats/applicants')->assertStatus(403);
    }

    public function test_saved_filters_round_trip_and_normalise_criteria(): void
    {
        Sanctum::actingAs($this->recruiter);

        $created = $this->postJson('/api/v1/recruitment/ats/filters', [
            'name' => 'A rappeler cette semaine',
            'criteria' => [
                'statuses' => ['screening', 'statut-inexistant'],
                'min_rating' => 4,
                'q' => 'laravel',
            ],
        ]);

        $created->assertCreated();
        // Le statut inconnu est écarté à l'enregistrement : un filtre rejoué ne
        // doit pas contenir de valeur que le moteur ignorerait silencieusement.
        $created->assertJsonPath('data.criteria.statuses', ['screening']);
        $created->assertJsonPath('data.criteria.min_rating', 4);

        $this->getJson('/api/v1/recruitment/ats/filters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A rappeler cette semaine');

        $filterId = $created->json('data.id');
        $this->deleteJson("/api/v1/recruitment/ats/filters/{$filterId}")->assertOk();

        $this->assertSame(0, AtsSavedFilter::query()->count());
    }

    public function test_saved_filter_cannot_target_a_foreign_job(): void
    {
        $otherCompany = Company::factory()->create();
        $foreignJob = JobPosting::create([
            'company_id' => $otherCompany->id,
            'title' => 'Foreign',
            'status' => 'published',
        ]);

        Sanctum::actingAs($this->recruiter);

        $this->postJson('/api/v1/recruitment/ats/filters', [
            'name' => 'Fuite',
            'job_posting_id' => $foreignJob->id,
            'criteria' => ['statuses' => ['new']],
        ])->assertStatus(422);
    }
}
