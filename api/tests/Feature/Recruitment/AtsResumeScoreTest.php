<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Application\Services\AtsApplicantFilter;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * ATS (#9461) — le score de correspondance lit le CV, pas seulement la lettre.
 *
 * Avant ce lot, `AtsApplicantFilter::score()` ne comparait les compétences
 * demandées qu'à la lettre de motivation et aux notes internes. Un candidat
 * sans lettre obtenait 0 % malgré un CV parfaitement aligné : le score était
 * trompeur au point qu'un recruteur ne pouvait pas s'en servir pour trier.
 *
 * Ces tests verrouillent trois propriétés :
 *
 * 1. le texte du CV entre bien dans le corpus ;
 * 2. une compétence n'est reconnue qu'en tant que MOT (« Go » ne doit pas
 *    correspondre à « Google », « R » à « Recrutement ») ;
 * 3. l'absence de compétences déclarées sur l'offre rend `null`, jamais 0.
 */
class AtsResumeScoreTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
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

    public function test_resume_text_alone_produces_a_full_score(): void
    {
        // Aucune lettre de motivation : c'est exactement le cas qui donnait
        // 0 % avant ce lot.
        $applicant = $this->applicant([
            'cover_letter' => null,
            'resume_text' => 'Developpeur backend senior. PHP, Laravel et PostgreSQL au quotidien.',
        ]);

        $result = app(AtsApplicantFilter::class)->score($applicant, $this->job);

        $this->assertSame(100, $result['score']);
        $this->assertSame(['PHP', 'Laravel', 'PostgreSQL'], $result['matched']);
        $this->assertSame([], $result['missing']);
        $this->assertContains('resume', $result['sources']);
    }

    public function test_resume_and_cover_letter_are_combined(): void
    {
        $applicant = $this->applicant([
            'resume_text' => 'Experiences: PHP et Laravel.',
            'cover_letter' => 'Je connais PostgreSQL depuis cinq ans.',
        ]);

        $result = app(AtsApplicantFilter::class)->score($applicant, $this->job);

        $this->assertSame(100, $result['score']);
        $this->assertSame(['resume', 'cover_letter'], $result['sources']);
    }

    public function test_missing_skills_are_reported(): void
    {
        $applicant = $this->applicant([
            'resume_text' => 'Developpeur PHP.',
        ]);

        $result = app(AtsApplicantFilter::class)->score($applicant, $this->job);

        $this->assertSame(33, $result['score']);
        $this->assertSame(['PHP'], $result['matched']);
        $this->assertSame(['Laravel', 'PostgreSQL'], $result['missing']);
    }

    public function test_a_skill_is_matched_on_word_boundaries_only(): void
    {
        $job = JobPosting::create([
            'company_id' => $this->company->id,
            'title' => 'Ingenieur Go',
            'status' => 'published',
            'skills_required' => ['Go', 'R'],
        ]);

        // « Google » contient « Go », « Recrutement » contient « R » : une
        // recherche par sous-chaîne donnerait 100 % à un candidat qui ne
        // connaît ni Go ni R.
        $applicant = $this->applicant([
            'job_posting_id' => $job->id,
            'resume_text' => 'J ai travaille chez Google dans le Recrutement.',
        ]);

        $result = app(AtsApplicantFilter::class)->score($applicant, $job);

        $this->assertSame(0, $result['score']);
        $this->assertSame(['Go', 'R'], $result['missing']);
    }

    public function test_a_skill_written_as_a_standalone_word_is_matched(): void
    {
        $job = JobPosting::create([
            'company_id' => $this->company->id,
            'title' => 'Ingenieur Go',
            'status' => 'published',
            'skills_required' => ['Go'],
        ]);

        $applicant = $this->applicant([
            'job_posting_id' => $job->id,
            'resume_text' => 'Langages: Go, Python, Rust.',
        ]);

        $result = app(AtsApplicantFilter::class)->score($applicant, $job);

        $this->assertSame(100, $result['score']);
    }

    public function test_no_declared_skills_yields_null_not_zero(): void
    {
        $job = JobPosting::create([
            'company_id' => $this->company->id,
            'title' => 'Offre sans competences',
            'status' => 'published',
            'skills_required' => null,
        ]);

        $applicant = $this->applicant([
            'job_posting_id' => $job->id,
            'resume_text' => 'Un CV complet.',
        ]);

        $result = app(AtsApplicantFilter::class)->score($applicant, $job);

        $this->assertNull($result['score'], 'Sans competence declaree, le score doit etre null.');
        $this->assertSame([], $result['sources']);
    }

    public function test_an_unreadable_resume_leaves_the_score_computable_from_the_letter(): void
    {
        // `resume_text` null = CV image ou chiffre : non extrait, pas vide.
        $applicant = $this->applicant([
            'resume_text' => null,
            'cover_letter' => 'Je fais du PHP et du Laravel.',
        ]);

        $result = app(AtsApplicantFilter::class)->score($applicant, $this->job);

        $this->assertSame(67, $result['score']);
        $this->assertSame(['cover_letter'], $result['sources']);
    }

    public function test_the_pipeline_search_finds_a_skill_written_in_the_resume(): void
    {
        $this->applicant([
            'resume_text' => 'Expert Kubernetes et Terraform.',
            'cover_letter' => null,
        ]);
        $this->applicant([
            'resume_text' => 'Developpeur PHP.',
            'cover_letter' => null,
        ]);

        Sanctum::actingAs($this->recruiter);

        $response = $this->getJson('/api/v1/recruitment/ats/applicants?q=Kubernetes');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_the_detail_endpoint_exposes_the_score_sources(): void
    {
        $applicant = $this->applicant([
            'resume_text' => 'PHP et Laravel.',
        ]);

        Sanctum::actingAs($this->recruiter);

        $response = $this->getJson("/api/v1/recruitment/ats/applicants/{$applicant->id}");

        $response->assertOk();
        $response->assertJsonPath('meta.match.score', 67);
        $response->assertJsonPath('meta.match.sources.0', 'resume');
        $response->assertJsonPath('data.has_resume_text', true);
    }
}
