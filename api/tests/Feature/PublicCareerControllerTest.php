<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PublicCareerControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_it_lists_only_published_jobs_for_the_company(): void
    {
        $company = Company::factory()->create();

        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Published Job',
            'status' => 'published',
            'published_at' => now(),
        ]);
        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Draft Job',
            'status' => 'draft',
        ]);
        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Closed Job',
            'status' => 'closed',
        ]);

        $response = $this->getJson("/api/v1/public/careers/{$company->slug}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Published Job');
    }

    public function test_it_exposes_company_branding_meta_on_the_listing(): void
    {
        $company = Company::factory()->create(['name' => 'Acme Corp']);
        DB::table($this->companiesTable())
            ->where('id', $company->id)
            ->update(['metadata' => json_encode([
                'branding' => [
                    'display_name' => 'Acme Careers',
                    'logo_url' => 'https://cdn.example.com/acme-logo.png',
                    'primary_color' => '#ff0000',
                    'accent_color' => '#00ff00',
                ],
            ], JSON_THROW_ON_ERROR)]);

        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Published Job',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/public/careers/{$company->slug}");

        $response->assertOk();
        $response->assertJsonPath('meta.company.slug', $company->slug);
        $response->assertJsonPath('meta.company.display_name', 'Acme Careers');
        $response->assertJsonPath('meta.company.logo_url', 'https://cdn.example.com/acme-logo.png');
        $response->assertJsonPath('meta.company.primary_color', '#FF0000');
        $response->assertJsonPath('meta.company.accent_color', '#00FF00');
    }

    public function test_it_falls_back_to_default_branding_when_unconfigured(): void
    {
        $company = Company::factory()->create(['name' => 'Bare Corp']);
        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}");

        $response->assertOk();
        $response->assertJsonPath('meta.company.display_name', 'Bare Corp');
        $response->assertJsonPath('meta.company.logo_url', null);
        $response->assertJsonPath('meta.company.primary_color', '#10B981');
        $response->assertJsonPath('meta.company.accent_color', '#2563EB');
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }

    public function test_it_does_not_leak_jobs_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        JobPosting::create([
            'company_id' => $otherCompany->id,
            'title' => 'Other Company Job',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/public/careers/{$company->slug}");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_unknown_company_slug_returns_404(): void
    {
        $this->getJson('/api/v1/public/careers/does-not-exist')->assertNotFound();
    }

    public function test_it_shows_a_single_published_job(): void
    {
        $company = Company::factory()->create();
        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'description' => 'Build things.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}");

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Backend Engineer');
    }

    public function test_draft_job_is_not_publicly_visible(): void
    {
        $company = Company::factory()->create();
        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Secret Draft',
            'status' => 'draft',
        ]);

        $this->getJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}")->assertNotFound();
    }

    public function test_it_serves_an_xml_feed_of_published_jobs(): void
    {
        $company = Company::factory()->create();
        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Feed Job',
            'location' => 'Algiers',
            'contract_type' => 'cdi',
            'status' => 'published',
            'published_at' => now(),
        ]);
        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Hidden Draft',
            'status' => 'draft',
        ]);

        $response = $this->get("/api/v1/public/careers/{$company->slug}/feed.xml");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('Feed Job', false);
        $response->assertDontSee('Hidden Draft', false);

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(1, $xml->job);
    }

    public function test_it_can_submit_a_candidate_application(): void
    {
        $company = Company::factory()->create();
        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Support Engineer',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply", [
            'first_name' => 'Nadia',
            'last_name' => 'Candidate',
            'email' => 'nadia@example.com',
            'phone' => '0555000000',
            'cover_letter' => 'I would love to join your team.',
            'source' => 'linkedin',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'nadia@example.com');
        $response->assertJsonPath('data.job_posting_id', $job->id);

        $this->assertDatabaseHas('applicants', [
            'company_id' => $company->id,
            'job_posting_id' => $job->id,
            'email' => 'nadia@example.com',
            'source' => 'linkedin',
            'status' => 'new',
        ]);
    }

    public function test_it_cannot_apply_to_a_draft_job(): void
    {
        $company = Company::factory()->create();
        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Draft Job',
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply", [
            'first_name' => 'Nadia',
            'last_name' => 'Candidate',
            'email' => 'nadia@example.com',
        ]);

        $response->assertNotFound();
    }

    public function test_application_requires_first_name_last_name_and_email(): void
    {
        $company = Company::factory()->create();
        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    }
}
