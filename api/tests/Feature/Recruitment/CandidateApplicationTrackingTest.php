<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * ATS (#9461) — suivi de candidature par le candidat.
 *
 * Cette route rend l'état d'un dossier à partir d'une simple adresse email :
 * c'est, par construction, une surface d'énumération. Les tests verrouillent
 * donc autant ce qu'elle rend que ce qu'elle NE rend PAS.
 */
class CandidateApplicationTrackingTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private JobPosting $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->job = JobPosting::create([
            'company_id' => $this->company->id,
            'title' => 'Developpeur Backend',
            'location' => 'Alger',
            'contract_type' => 'cdi',
            'status' => 'published',
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
    private function applicant(string $email, array $overrides = []): Applicant
    {
        return Applicant::create([
            'company_id' => $this->company->id,
            'job_posting_id' => $this->job->id,
            'first_name' => 'Nadia',
            'last_name' => 'Candidate',
            'email' => $email,
            'status' => 'new',
            'source' => 'website',
            'applied_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_a_candidate_finds_their_own_applications(): void
    {
        $this->applicant('nadia@example.com', ['status' => 'screening']);

        $response = $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'nadia@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.job_title', 'Developpeur Backend');
        $response->assertJsonPath('data.0.status', 'screening');
        $response->assertJsonPath('meta.count', 1);
    }

    public function test_the_email_match_is_case_insensitive(): void
    {
        $this->applicant('nadia@example.com');

        $response = $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'Nadia@Example.COM',
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_an_unknown_email_returns_an_empty_list_not_a_404(): void
    {
        // Un 404 confirmerait « cette adresse n'a jamais postule », ce qui est
        // deja une information. La reponse doit etre identique a celle d'un
        // candidat sans candidature.
        $response = $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'inconnu@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.count', 0);
    }

    public function test_it_never_leaks_internal_recruiter_data(): void
    {
        $this->applicant('nadia@example.com', [
            'rating' => 5,
            'notes' => 'Note interne : candidat trop cher.',
            'resume_text' => 'CV complet avec PHP et Laravel.',
            'resume_scan_status' => 'clean',
        ]);

        $response = $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'nadia@example.com',
        ]);

        $response->assertOk();

        $payload = $response->json('data.0');

        // La note interne, les notes du recruteur, le texte du CV et le
        // verdict antivirus sont des donnees RH : jamais dans une reponse
        // publique.
        $this->assertArrayNotHasKey('rating', $payload);
        $this->assertArrayNotHasKey('notes', $payload);
        $this->assertArrayNotHasKey('resume_text', $payload);
        $this->assertArrayNotHasKey('resume_scan_status', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('id', $payload);

        $this->assertStringNotContainsString('trop cher', (string) $response->getContent());
    }

    public function test_it_does_not_return_applications_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $otherJob = JobPosting::create([
            'company_id' => $otherCompany->id,
            'title' => 'Poste ailleurs',
            'status' => 'published',
        ]);

        Applicant::create([
            'company_id' => $otherCompany->id,
            'job_posting_id' => $otherJob->id,
            'first_name' => 'Nadia',
            'last_name' => 'Candidate',
            'email' => 'nadia@example.com',
            'status' => 'new',
            'applied_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'nadia@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_an_unknown_company_slug_is_a_404(): void
    {
        $this->postJson('/api/v1/public/careers/entreprise-inexistante/applications/track', [
            'email' => 'nadia@example.com',
        ])->assertNotFound();
    }

    public function test_the_email_is_required_and_validated(): void
    {
        $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'pas-un-email',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_retention_period_is_disclosed(): void
    {
        $this->applicant('nadia@example.com');

        $response = $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'nadia@example.com',
        ]);

        $response->assertOk();
        // Obligation d'information RGPD : le candidat doit savoir combien de
        // temps son dossier est conserve.
        $this->assertIsInt($response->json('meta.retention_days'));
        $this->assertGreaterThan(0, $response->json('meta.retention_days'));
    }

    public function test_the_reference_is_readable_and_does_not_expose_the_raw_id(): void
    {
        $applicant = $this->applicant('nadia@example.com');

        $response = $this->postJson("/api/v1/public/careers/{$this->company->slug}/applications/track", [
            'email' => 'nadia@example.com',
        ]);

        $response->assertOk();
        $this->assertSame(
            sprintf('CAND-%06d', $applicant->id),
            $response->json('data.0.reference'),
        );
    }
}
