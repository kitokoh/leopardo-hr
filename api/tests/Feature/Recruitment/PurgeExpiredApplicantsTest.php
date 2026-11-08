<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-10 (#9216) — purge RGPD des candidatures échues.
 *
 * Ce test verrouille les garanties qui ne se voient pas à l'œil nu :
 *  - une candidature échue est supprimée, une candidature en cours est
 *    CONSERVÉE (une purge trop large détruirait des candidatures vivantes) ;
 *  - le CV est supprimé du disque, pas seulement la ligne ;
 *  - un fichier non supprimable CONSERVE la ligne (sinon le CV devient
 *    orphelin et introuvable, donc jamais purgé) ;
 *  - un chemin hors périmètre tenant n'est jamais supprimé ;
 *  - `--dry-run` n'écrit rien.
 */
final class PurgeExpiredApplicantsTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_it_purges_applications_past_their_retention_date(): void
    {
        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $expired = $this->applicant($company, $job, 'expired@example.com', now()->subDay());
        $alive = $this->applicant($company, $job, 'alive@example.com', now()->addMonths(6));

        $this->artisan('recruitment:purge-expired-applications')->assertSuccessful();

        // L'échue part, la vivante reste : c'est tout l'enjeu d'une purge.
        $this->assertDatabaseMissing('applicants', ['id' => $expired->id]);
        $this->assertDatabaseHas('applicants', ['id' => $alive->id]);
    }

    public function test_it_deletes_the_stored_resume_file(): void
    {
        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $path = "recruitment/{$company->id}/resumes/cv.pdf";
        Storage::disk('local')->put($path, 'contenu du cv');

        $applicant = $this->applicant($company, $job, 'with-cv@example.com', now()->subDay(), $path);

        $this->artisan('recruitment:purge-expired-applications')->assertSuccessful();

        // Supprimer la ligne en laissant le CV serait le pire des deux mondes :
        // la personne croit ses données effacées, le document reste lisible.
        $this->assertDatabaseMissing('applicants', ['id' => $applicant->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $path = "recruitment/{$company->id}/resumes/cv.pdf";
        Storage::disk('local')->put($path, 'contenu du cv');

        $applicant = $this->applicant($company, $job, 'dry@example.com', now()->subDay(), $path);

        $this->artisan('recruitment:purge-expired-applications', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('applicants', ['id' => $applicant->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_the_grace_period_keeps_recently_expired_applications(): void
    {
        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        // Échue il y a 5 jours : dans la marge de 30 jours, donc conservée.
        $applicant = $this->applicant($company, $job, 'grace@example.com', now()->subDays(5));

        $this->artisan('recruitment:purge-expired-applications', ['--grace-days' => 30])->assertSuccessful();

        $this->assertDatabaseHas('applicants', ['id' => $applicant->id]);
    }

    public function test_it_never_deletes_a_file_outside_the_tenant_scope(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $job = $this->jobFor($company);

        // Chemin pointant vers le répertoire d'une AUTRE entreprise : un chemin
        // forgé ou corrompu ne doit pas pouvoir faire supprimer le fichier d'un
        // autre client.
        $foreignPath = "recruitment/{$other->id}/resumes/other-cv.pdf";
        Storage::disk('local')->put($foreignPath, 'cv d une autre entreprise');

        $applicant = $this->applicant($company, $job, 'foreign@example.com', now()->subDay(), $foreignPath);

        $this->artisan('recruitment:purge-expired-applications')->assertSuccessful();

        // La ligne part (la candidature est bien échue), mais le fichier de
        // l'autre tenant reste intact.
        $this->assertDatabaseMissing('applicants', ['id' => $applicant->id]);
        Storage::disk('local')->assertExists($foreignPath);
    }

    public function test_it_never_deletes_a_file_through_a_path_traversal(): void
    {
        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $traversal = "recruitment/{$company->id}/../other/resumes/cv.pdf";
        Storage::disk('local')->put('recruitment/other/resumes/cv.pdf', 'hors perimetre');

        $applicant = $this->applicant($company, $job, 'traversal@example.com', now()->subDay(), $traversal);

        $this->artisan('recruitment:purge-expired-applications')->assertSuccessful();

        $this->assertDatabaseMissing('applicants', ['id' => $applicant->id]);
        Storage::disk('local')->assertExists('recruitment/other/resumes/cv.pdf');
    }

    public function test_it_purges_across_all_tenants(): void
    {
        $first = Company::factory()->create();
        $second = Company::factory()->create();

        $a = $this->applicant($first, $this->jobFor($first), 'a@example.com', now()->subDay());
        $b = $this->applicant($second, $this->jobFor($second), 'b@example.com', now()->subDay());

        $this->artisan('recruitment:purge-expired-applications')->assertSuccessful();

        // La commande tourne hors contexte tenant : elle doit couvrir TOUS les
        // tenants, pas seulement celui qui serait « courant ».
        $this->assertDatabaseMissing('applicants', ['id' => $a->id]);
        $this->assertDatabaseMissing('applicants', ['id' => $b->id]);
    }

    public function test_it_ignores_applications_without_a_retention_date(): void
    {
        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        // Candidature déposée avant l'ajout des colonnes RGPD : `retention_until`
        // est NULL. On ne peut pas prouver qu'elle est échue, donc on ne la
        // supprime pas — une purge ne doit jamais deviner.
        $applicant = Applicant::create([
            'company_id' => $company->id,
            'job_posting_id' => $job->id,
            'first_name' => 'Sans',
            'last_name' => 'Echeance',
            'email' => 'no-retention@example.com',
            'status' => 'new',
            'applied_at' => now()->subYears(5),
        ]);

        $this->artisan('recruitment:purge-expired-applications')->assertSuccessful();

        $this->assertDatabaseHas('applicants', ['id' => $applicant->id]);
    }

    public function test_it_reports_nothing_to_purge_when_the_table_is_clean(): void
    {
        $this->artisan('recruitment:purge-expired-applications')
            ->expectsOutputToContain('Aucune candidature expirée à purger.')
            ->assertSuccessful();
    }

    private function jobFor(Company $company): JobPosting
    {
        return JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Ingénieur',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function applicant(
        Company $company,
        JobPosting $job,
        string $email,
        \Illuminate\Support\Carbon $retentionUntil,
        ?string $resumePath = null,
    ): Applicant {
        return Applicant::create([
            'company_id' => $company->id,
            'job_posting_id' => $job->id,
            'first_name' => 'Jean',
            'last_name' => 'Candidat',
            'email' => $email,
            'resume_path' => $resumePath,
            'status' => 'new',
            'applied_at' => now()->subMonths(25),
            'consent_given_at' => now()->subMonths(25),
            'retention_until' => $retentionUntil,
        ]);
    }
}
