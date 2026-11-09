<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Application\Services\ResumeScanService;
use App\Modules\Recruitment\Domain\Contracts\ResumeScanner;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use App\Modules\Recruitment\Domain\ValueObjects\ResumeScanVerdict;
use App\Modules\Recruitment\Infrastructure\Antivirus\NullResumeScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-10 (#9217) — analyse antivirus des CV à l'upload.
 *
 * Le point le plus important de ce test n'est pas « un virus est rejeté » :
 * c'est qu'un fichier NON ANALYSÉ ne soit jamais présenté comme sain. C'est
 * l'erreur qui rendrait le contrôle dangereux — un faux sentiment de sécurité
 * est pire qu'une absence de contrôle assumée.
 */
final class ResumeScanTest extends TestCase
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

    public function test_a_clean_resume_is_accepted_and_the_verdict_is_persisted(): void
    {
        $this->bindScanner(ResumeScanVerdict::clean('fake'));

        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $response = $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            $this->payload(['resume' => $this->pdf('cv.pdf')]),
        );

        $response->assertCreated();
        $this->assertDatabaseHas('applicants', [
            'email' => 'candidat@example.com',
            'resume_scan_status' => ResumeScanVerdict::CLEAN,
            'resume_scan_engine' => 'fake',
        ]);
    }

    public function test_an_infected_resume_is_rejected_and_never_stored(): void
    {
        $this->bindScanner(ResumeScanVerdict::infected('fake', 'Eicar-Test-Signature'));

        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $response = $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            $this->payload(['resume' => $this->pdf('cv.pdf')]),
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'RESUME_REJECTED');

        // Ni candidature, ni fichier : le document malveillant ne doit à aucun
        // moment atteindre le répertoire du tenant.
        $this->assertDatabaseMissing('applicants', ['email' => 'candidat@example.com']);
        $this->assertEmpty(Storage::disk('local')->allFiles("recruitment/{$company->id}/resumes"));
    }

    public function test_the_rejection_message_does_not_leak_the_signature(): void
    {
        $this->bindScanner(ResumeScanVerdict::infected('fake', 'Eicar-Test-Signature'));

        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $response = $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            $this->payload(['resume' => $this->pdf('cv.pdf')]),
        );

        // La signature détectée est une information d'exploitation : la
        // révéler à un tiers lui apprendrait ce que notre moteur sait voir.
        $response->assertStatus(422);
        $this->assertStringNotContainsString('Eicar', (string) $response->getContent());
    }

    public function test_an_unavailable_scanner_does_not_block_a_legitimate_application(): void
    {
        $this->bindScanner(ResumeScanVerdict::unavailable('fake', 'démon injoignable'));

        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $response = $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            $this->payload(['resume' => $this->pdf('cv.pdf')]),
        );

        // Une panne d'infrastructure ne doit pas empêcher un candidat de
        // postuler — mais le verdict est persisté, donc auditable.
        $response->assertCreated();
        $this->assertDatabaseHas('applicants', [
            'email' => 'candidat@example.com',
            'resume_scan_status' => ResumeScanVerdict::UNAVAILABLE,
        ]);
    }

    public function test_an_application_without_a_resume_has_no_scan_verdict(): void
    {
        $this->bindScanner(ResumeScanVerdict::clean('fake'));

        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $response = $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            $this->payload(),
        );

        $response->assertCreated();

        // Pas de CV → pas de verdict. NULL dit honnêtement « rien à analyser »,
        // là où `clean` prétendrait avoir vérifié un fichier inexistant.
        $this->assertDatabaseHas('applicants', [
            'email' => 'candidat@example.com',
            'resume_scan_status' => null,
            'resume_scanned_at' => null,
        ]);
    }

    public function test_the_null_scanner_reports_unavailable_and_never_clean(): void
    {
        // Le cas le plus important : aucun moteur configuré. Le scanner par
        // défaut ne doit PAS prétendre que le fichier est sain.
        $scanner = new NullResumeScanner;
        $verdict = $scanner->scan('/tmp/inexistant.pdf', 'cv.pdf');

        $this->assertTrue($verdict->isUnavailable());
        $this->assertFalse($verdict->isClean());
        $this->assertSame('none', $verdict->engine);
    }

    public function test_the_quarantine_policy_accepts_an_infected_resume_but_marks_it(): void
    {
        $this->bindScanner(ResumeScanVerdict::infected('fake', 'Eicar-Test-Signature'));
        config(['recruitment.antivirus.policy' => ResumeScanService::POLICY_QUARANTINE]);
        $this->app->forgetInstance(ResumeScanService::class);

        $company = Company::factory()->create();
        $job = $this->jobFor($company);

        $response = $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            $this->payload(['resume' => $this->pdf('cv.pdf')]),
        );

        // En quarantaine, la candidature passe mais le fichier reste marqué
        // pour analyse manuelle par l'équipe sécurité.
        $response->assertCreated();
        $this->assertDatabaseHas('applicants', [
            'email' => 'candidat@example.com',
            'resume_scan_status' => ResumeScanVerdict::INFECTED,
            'resume_scan_signature' => 'Eicar-Test-Signature',
        ]);
    }

    public function test_an_unknown_driver_falls_back_to_unavailable_not_clean(): void
    {
        // Faute de frappe dans une variable d'environnement : la sécurité ne
        // doit pas être désactivée silencieusement en prétendant que les
        // fichiers sont sains.
        config(['recruitment.antivirus.driver' => 'clamv']);
        $this->app->forgetInstance(ResumeScanner::class);

        $scanner = $this->app->make(ResumeScanner::class);
        $verdict = $scanner->scan('/tmp/inexistant.pdf', 'cv.pdf');

        $this->assertInstanceOf(NullResumeScanner::class, $scanner);
        $this->assertTrue($verdict->isUnavailable());
    }

    private function bindScanner(ResumeScanVerdict $verdict): void
    {
        $this->app->bind(ResumeScanner::class, fn (): ResumeScanner => new class($verdict) implements ResumeScanner
        {
            public function __construct(private readonly ResumeScanVerdict $verdict) {}

            public function scan(string $absolutePath, string $originalName): ResumeScanVerdict
            {
                return $this->verdict;
            }

            public function name(): string
            {
                return 'fake';
            }
        });

        $this->app->forgetInstance(ResumeScanService::class);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jean',
            'last_name' => 'Candidat',
            'email' => 'candidat@example.com',
            'consent' => true,
        ], $overrides);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }
}
