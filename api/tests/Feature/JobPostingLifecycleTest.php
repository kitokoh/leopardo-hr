<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-10 (#9100) — cycle de vie d'une offre et flux XML du portail public.
 *
 * Verrouille les transitions qui ne se voient pas à l'œil nu :
 *  - une offre archivée disparaît du portail public ;
 *  - une offre republiée y réapparaît, avec une date de clôture remise à zéro
 *    (sinon elle disparaîtrait aussitôt, ce qui se lirait comme un bug) ;
 *  - une transition invalide est refusée en 422 ;
 *  - le flux XML porte les champs exigés par Google Jobs.
 */
final class JobPostingLifecycleTest extends TestCase
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

    public function test_a_manager_can_archive_a_closed_job_and_it_leaves_the_portal(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp']);
        $manager = $this->manager($company);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => 'closed',
            'published_at' => now()->subDays(10),
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/recruitment/jobs/{$job->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertNotNull($job->refresh()->archived_at);

        // Le portail public ne doit plus la servir.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}")->assertNotFound();
    }

    public function test_a_published_job_cannot_be_archived_directly(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp']);
        $manager = $this->manager($company);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        // Archiver une offre encore publiée la retirerait du portail sans
        // clôture explicite : la disparition serait inexplicable côté candidat.
        $this->postJson("/api/v1/recruitment/jobs/{$job->id}/archive")->assertStatus(422);
    }

    public function test_reopening_an_archived_job_clears_the_closing_date(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp']);
        $manager = $this->manager($company);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => 'archived',
            'published_at' => now()->subDays(30),
            'closes_at' => now()->subDays(5),
            'archived_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/recruitment/jobs/{$job->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $job->refresh();

        // Conserver l'ancienne `closes_at` ferait réapparaître l'offre pour la
        // faire disparaître aussitôt (le portail filtre sur closes_at >= now()).
        $this->assertNull($job->closes_at);
        $this->assertNull($job->archived_at);

        // Et elle est bien de nouveau visible.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}")->assertOk();
    }

    public function test_a_draft_job_cannot_be_reopened(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp']);
        $manager = $this->manager($company);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/recruitment/jobs/{$job->id}/reopen")->assertStatus(422);
    }

    public function test_the_xml_feed_carries_the_fields_google_jobs_requires(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp', 'country' => 'DZ']);

        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Ingénieur Backend',
            'description' => 'Rejoignez-nous.',
            'location' => 'Alger',
            'contract_type' => 'cdi',
            'remote_policy' => 'remote',
            'salary_range_min' => 120000,
            'salary_range_max' => 180000,
            'currency' => 'DZD',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get("/api/v1/public/careers/{$company->slug}/feed.xml");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);

        $job = $xml->job[0];

        // Champs exigés par la spécification Google Jobs : sans
        // `expirationDate`, l'annonce peut rester indexée après sa clôture.
        foreach (['title', 'date', 'referencenumber', 'url', 'company', 'city', 'country', 'description', 'jobtype', 'expirationDate', 'remote'] as $field) {
            $this->assertTrue(isset($job->{$field}), "Le flux doit porter le champ « {$field} ».");
        }

        $this->assertSame('true', (string) $job->remote);
        $this->assertSame('FullTime', (string) $job->jobtype);
        $this->assertSame('DZ', (string) $job->country);
    }

    public function test_the_feed_escapes_special_characters(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp', 'country' => 'DZ']);

        JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Dev & Ops <Senior>',
            'description' => 'Stack : PHP & Postgres, "scalable".',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get("/api/v1/public/careers/{$company->slug}/feed.xml");

        // Un `&` non échappé rendrait le flux invalide et ferait rejeter
        // TOUTES les offres par Google Jobs, pas seulement celle-ci.
        $body = $response->getContent();
        $this->assertStringContainsString('&amp;', $body);

        $xml = simplexml_load_string($body);
        $this->assertNotFalse($xml);
        $this->assertSame('Dev & Ops <Senior>', (string) $xml->job[0]->title);
    }

    private function manager(Company $company): Employee
    {
        // `manager_role` (et non `role`) : c'est le champ que lit
        // `hasManagerRole('principal', 'rh')` dans le contrôleur.
        return Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);
    }
}
