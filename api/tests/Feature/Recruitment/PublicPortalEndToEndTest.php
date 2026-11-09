<?php

namespace Tests\Feature\Recruitment;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-10 — parcours complet du portail public, en une seule chaîne.
 *
 * Les tests existants couvrent chaque maillon séparément
 * (`RecruitmentControllerTest` pour la publication côté tenant,
 * `PublicCareerControllerTest` pour la lecture publique et la candidature,
 * `PlatformRecruitmentPortalTest` pour la publication plateforme). Ce fichier
 * couvre ce qu'aucun ne couvre : **la chaîne entière**, du clic du recruteur
 * jusqu'à la candidature visible dans son espace.
 *
 * C'est le test qui attrape les régressions d'**intégration** — un maillon qui
 * marche isolément mais dont la sortie ne rentre plus dans l'entrée du suivant
 * (statut mal propagé, `published_at` non posé, offre filtrée par erreur).
 */
class PublicPortalEndToEndTest extends TestCase
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

    /**
     * Le parcours nominal, de bout en bout :
     *
     *   1. le RH crée une offre (brouillon) ;
     *   2. elle n'est PAS visible publiquement ;
     *   3. il la publie ;
     *   4. elle apparaît sur le portail public ;
     *   5. un candidat postule ;
     *   6. la candidature est visible dans l'espace du recruteur ;
     *   7. elle apparaît aussi dans le flux XML (Google Jobs / Indeed).
     */
    public function test_the_full_journey_from_draft_to_visible_application(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        Sanctum::actingAs($manager);

        // 1. Création — l'offre naît en brouillon.
        $created = $this->postJson('/api/v1/recruitment/jobs', [
            'title' => 'Ingénieur DevOps',
            'description' => 'Rejoignez notre équipe plateforme.',
            'location' => 'Alger',
            'remote_policy' => 'hybrid',
            'contract_type' => 'cdi',
        ]);

        $created->assertCreated();
        $jobId = $created->json('data.id');
        $this->assertNotNull($jobId, 'La création doit renvoyer un identifiant.');

        // 2. Un brouillon ne doit JAMAIS fuiter sur le portail public.
        $this->getJson("/api/v1/public/careers/{$company->slug}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // 3. Publication.
        $this->postJson("/api/v1/recruitment/jobs/{$jobId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // 4. L'offre est désormais publique, avec son titre.
        $listing = $this->getJson("/api/v1/public/careers/{$company->slug}");
        $listing->assertOk();
        $listing->assertJsonCount(1, 'data');
        $listing->assertJsonPath('data.0.title', 'Ingénieur DevOps');

        // Le détail est accessible sans authentification.
        $this->getJson("/api/v1/public/careers/{$company->slug}/jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Ingénieur DevOps');

        // 5. Candidature publique, sans authentification.
        $this->postJson("/api/v1/public/careers/{$company->slug}/jobs/{$jobId}/apply", [
            'first_name' => 'Yacine',
            'last_name' => 'Benali',
            'email' => 'yacine.benali@example.com',
            'phone' => '0555112233',
            'cover_letter' => 'Je souhaite rejoindre votre équipe.',
            'consent' => true,
        ])->assertCreated();

        // 6. La candidature remonte dans l'espace du recruteur.
        $applicants = $this->getJson("/api/v1/recruitment/jobs/{$jobId}/applicants");
        $applicants->assertOk();
        $applicants->assertJsonCount(1, 'data');
        $applicants->assertJsonPath('data.0.email', 'yacine.benali@example.com');

        // 7. Et dans le flux XML lu par les robots.
        $feed = $this->get("/api/v1/public/careers/{$company->slug}/feed.xml");
        $feed->assertOk();

        $xml = simplexml_load_string($feed->getContent());
        $this->assertNotFalse($xml, 'Le flux doit être un XML valide.');
        $this->assertCount(1, $xml->job);
    }

    /**
     * Le maillon le plus fragile de la chaîne : une offre publiée par la
     * **plateforme** (et non par le tenant) doit elle aussi atterrir sur le
     * portail public. C'est le cas que la publication côté tenant ne couvre
     * pas, et celui qu'une régression d'isolation casse en premier.
     */
    public function test_a_platform_published_job_reaches_the_public_portal(): void
    {
        $company = Company::factory()->create();

        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Data Analyst',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->getJson("/api/v1/public/careers/{$company->slug}")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Data Analyst');

        $this->getJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}")
            ->assertOk();
    }

    /**
     * Fermer une offre la retire du portail **sans** effacer les candidatures
     * déjà reçues : un recruteur qui clôture un poste ne doit pas perdre les
     * dossiers en cours d'instruction.
     */
    public function test_closing_a_job_hides_it_but_keeps_its_applications(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'created_by' => $manager->id,
            'title' => 'Chef de projet',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->postJson("/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply", [
            'first_name' => 'Amel',
            'last_name' => 'Cherif',
            'email' => 'amel.cherif@example.com',
            'consent' => true,
        ])->assertCreated();

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/recruitment/jobs/{$job->id}/close")->assertOk();

        // L'offre disparaît du portail public…
        $this->getJson("/api/v1/public/careers/{$company->slug}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // …mais la candidature reste consultable par le recruteur.
        $this->getJson("/api/v1/recruitment/jobs/{$job->id}/applicants")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
