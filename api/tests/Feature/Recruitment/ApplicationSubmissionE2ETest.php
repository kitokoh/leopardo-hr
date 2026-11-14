<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * ATS (#9461) — dépôt de candidature de bout en bout avec un VRAI PDF.
 *
 * ## Ce que ce test couvre, et qu'aucun autre ne couvre
 *
 * Les tests existants vérifient chaque maillon séparément :
 *  - `ResumeTextExtractorTest` : l'extracteur lit un PDF (mais aucun upload) ;
 *  - `AtsResumeScoreTest` : le score compare un texte (mais aucun fichier) ;
 *  - `PublicPortalEndToEndTest` : la candidature est déposée (mais SANS CV).
 *
 * Le trou entre les trois est précisément là où le produit casse : un
 * candidat téléverse un PDF, et le score doit refléter **le contenu de ce
 * fichier**. Un test qui injecte `resume_text` à la main ne prouve rien sur
 * ce chemin — il saute le stockage, l'extraction et le branchement.
 *
 * Ce test part donc d'un fichier PDF réel, traverse le contrôleur public
 * (validation, stockage, extraction), puis interroge l'API recruteur pour
 * vérifier le score calculé.
 *
 * ## Pourquoi le PDF est généré et non versionné
 *
 * Un PDF de test commité devient illisible au premier changement d'outil et
 * personne ne sait plus ce qu'il contient. On l'écrit ici, avec un contenu
 * connu : c'est la seule façon d'affirmer « le score de 100 % vient bien de
 * ces compétences-là ».
 */
class ApplicationSubmissionE2ETest extends TestCase
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

    /**
     * Le parcours complet : offre publiée → candidat téléverse un PDF →
     * texte extrait → score ATS calculé sur le CV.
     */
    public function test_a_real_pdf_upload_is_extracted_and_scored(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        // L'offre exige exactement les compétences que le CV contient.
        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Développeur Backend PHP / Laravel',
            'description' => 'Poste backend.',
            'location' => 'Alger',
            'remote_policy' => 'hybrid',
            'contract_type' => 'cdi',
            'skills_required' => ['PHP', 'Laravel', 'PostgreSQL', 'Docker'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $pdf = $this->makePdf([
            'Yacine Belkacem',
            'Developpeur Backend',
            '',
            'COMPETENCES',
            'PHP, Laravel, PostgreSQL, Docker, Redis',
        ]);

        // 1. Dépôt public, sans authentification, avec le fichier réel.
        $response = $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            [
                'first_name' => 'Yacine',
                'last_name' => 'Belkacem',
                'email' => 'yacine.belkacem@example.dz',
                'phone' => '0555112233',
                // Lettre volontairement SANS aucune compétence : si le score
                // monte, c'est le CV qui l'a fait monter, pas la lettre.
                'cover_letter' => 'Je suis très motivé par ce poste.',
                'consent' => true,
                'resume' => $pdf,
            ],
        );

        $response->assertCreated();

        // 2. La candidature existe, le fichier est stocké, le texte extrait.
        $applicant = Applicant::query()
            ->where('company_id', $company->id)
            ->where('email', 'yacine.belkacem@example.dz')
            ->first();

        $this->assertNotNull($applicant, 'La candidature doit être créée.');
        $this->assertNotNull($applicant->resume_path, 'Le CV doit être stocké.');
        Storage::disk('local')->assertExists((string) $applicant->resume_path);

        $this->assertNotNull(
            $applicant->resume_text,
            'Le texte du CV doit être extrait à l upload (pdftotext requis).',
        );
        $this->assertStringContainsString('Laravel', (string) $applicant->resume_text);
        $this->assertStringContainsString('PostgreSQL', (string) $applicant->resume_text);
        $this->assertNotNull($applicant->resume_text_extracted_at);

        // 3. Le recruteur voit le score calculé sur le CV.
        Sanctum::actingAs($manager);

        $detail = $this->getJson("/api/v1/recruitment/ats/applicants/{$applicant->id}");
        $detail->assertOk();

        $this->assertSame(
            100,
            $detail->json('meta.match.score'),
            'Les 4 compétences exigées sont dans le CV : le score doit être de 100 %.',
        );

        $this->assertSame(
            ['PHP', 'Laravel', 'PostgreSQL', 'Docker'],
            $detail->json('meta.match.matched'),
        );
        $this->assertSame([], $detail->json('meta.match.missing'));

        // La provenance du score est exposée : sans `resume`, un score de
        // 100 % ne prouverait pas que le CV a été lu.
        $this->assertContains('resume', $detail->json('meta.match.sources'));

        $this->assertTrue($detail->json('data.has_resume_text'));
    }

    /**
     * Le score doit s'appuyer sur le CV, pas seulement sur la lettre.
     *
     * Le piège est asymétrique : un score qui ne lirait QUE la lettre
     * passerait le test nominal sans qu'on le remarque (la lettre y est
     * volontairement vide de compétences). On construit donc ici le cas
     * inverse — la lettre ne cite qu'UNE compétence, le CV en apporte une
     * SECONDE que la lettre ne contient pas. Si le score monte à 100 %, c'est
     * bien le CV qui a été lu.
     *
     * (La lettre reste une source légitime du score : c'est un choix produit
     * assumé, exposé via `meta.match.sources`. Ce test ne le contredit pas,
     * il vérifie que le CV n'est pas ignoré.)
     */
    public function test_the_score_uses_the_resume_and_not_only_the_cover_letter(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Développeur Backend',
            'description' => 'Poste backend.',
            'location' => 'Alger',
            'remote_policy' => 'onsite',
            'contract_type' => 'cdi',
            'skills_required' => ['PHP', 'Laravel'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        // La lettre ne cite QUE PHP. Laravel n'apparaît que dans le CV.
        $pdf = $this->makePdf([
            'Amel Cherif',
            'Developpeuse Backend',
            '',
            'COMPETENCES',
            'Laravel, MySQL, Redis',
        ]);

        $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            [
                'first_name' => 'Amel',
                'last_name' => 'Cherif',
                'email' => 'amel.cherif@example.dz',
                'cover_letter' => 'Je travaille avec PHP au quotidien.',
                'consent' => true,
                'resume' => $pdf,
            ],
        )->assertCreated();

        $applicant = Applicant::query()
            ->where('company_id', $company->id)
            ->where('email', 'amel.cherif@example.dz')
            ->firstOrFail();

        Sanctum::actingAs($manager);

        $detail = $this->getJson("/api/v1/recruitment/ats/applicants/{$applicant->id}");
        $detail->assertOk();

        // Laravel n'est présent QUE dans le CV : sans extraction, il
        // manquerait et le score plafonnerait à 50 %.
        $this->assertSame(
            100,
            $detail->json('meta.match.score'),
            'Laravel vient du CV : sans extraction le score plafonnerait à 50 %.',
        );
        $this->assertSame([], $detail->json('meta.match.missing'));
        $this->assertContains('resume', $detail->json('meta.match.sources'));
    }

    /**
     * Un PDF scanné (aucun texte extractible) ne doit PAS faire échouer la
     * candidature : le candidat est légitime, c'est notre lecture qui échoue.
     * Le dossier est créé, `resume_text` reste nul, et le score retombe sur
     * la lettre — comportement dégradé mais explicite.
     */
    public function test_an_unreadable_pdf_still_creates_the_application(): void
    {
        $company = Company::factory()->create();

        $job = JobPosting::create([
            'company_id' => $company->id,
            'title' => 'Développeur Backend',
            'description' => 'Poste backend.',
            'location' => 'Alger',
            'remote_policy' => 'onsite',
            'contract_type' => 'cdi',
            'skills_required' => ['PHP'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        // PDF valide en structure, mais dont le flux ne contient aucun texte.
        $pdf = $this->makePdf([]);

        $this->postJson(
            "/api/v1/public/careers/{$company->slug}/jobs/{$job->id}/apply",
            [
                'first_name' => 'Sofiane',
                'last_name' => 'Merabet',
                'email' => 'sofiane.merabet@example.dz',
                'consent' => true,
                'resume' => $pdf,
            ],
        )->assertCreated();

        $applicant = Applicant::query()
            ->where('company_id', $company->id)
            ->where('email', 'sofiane.merabet@example.dz')
            ->first();

        $this->assertNotNull($applicant, 'Un CV illisible ne doit pas bloquer la candidature.');
        $this->assertNull($applicant->resume_text, 'Aucun texte ne doit être inventé.');
        $this->assertNull($applicant->resume_text_extracted_at);
    }

    /**
     * Construit un PDF minimal mais VALIDE (texte, pas image).
     *
     * La structure est écrite à la main : c'est la seule façon de garantir
     * que le contenu est bien du texte — donc exactement ce que `pdftotext`
     * doit savoir relire.
     *
     * @param  list<string>  $lines
     */
    private function makePdf(array $lines): UploadedFile
    {
        $content = "BT /F1 11 Tf 50 780 Td 14 TL\n";

        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= '('.$escaped.") Tj T*\n";
        }

        $content .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefPosition."\n%%EOF";

        $path = tempnam(sys_get_temp_dir(), 'cv_e2e_').'.pdf';
        file_put_contents($path, $pdf);

        return new UploadedFile($path, 'cv-yacine.pdf', 'application/pdf', null, true);
    }
}
