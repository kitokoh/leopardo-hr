<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Recruitment\Domain\Contracts\ResumeTextExtraction;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recette (#9461) — jeu de données pour la vérification visuelle des écrans ATS.
 *
 * Ce seeder n'existe QUE pour la recette : il crée des offres publiées et des
 * candidatures avec de vrais fichiers CV, afin de voir les trois écrans avec
 * des données réalistes plutôt qu'avec des listes vides.
 *
 * Il ne fait pas partie du seed de production et n'est jamais appelé par
 * `DatabaseSeeder`.
 *
 * Usage :
 *   php artisan db:seed --class=RecruitmentRecetteSeeder
 */
class RecruitmentRecetteSeeder extends Seeder
{
    public function run(): void
    {
        $company = DB::table('public.companies')
            ->where('slug', 'techcorp-algerie')
            ->first();

        if ($company === null) {
            $this->command->error('Tenant techcorp-algerie introuvable — lancer DemoCompanySeeder d abord.');

            return;
        }

        $companyId = (string) $company->id;

        DB::statement('SET search_path TO shared_tenants, public');

        $jobs = $this->seedJobs($companyId);
        $this->seedApplicants($companyId, $jobs);

        $this->command->info('Recette ATS : '.count($jobs).' offres, '.Applicant::query()->where('company_id', $companyId)->count().' candidatures.');
    }

    /**
     * @return array<string, int>
     */
    private function seedJobs(string $companyId): array
    {
        $definitions = [
            'backend' => [
                'title' => 'Développeur Backend PHP / Laravel',
                'description' => "Nous recherchons un développeur backend pour renforcer l'équipe plateforme.\n\nVous concevrez et maintiendrez les API du produit RH, en collaboration avec l'équipe frontend. Le poste couvre la conception de schémas PostgreSQL, l'écriture de tests automatisés et la revue de code.",
                'location' => 'Alger',
                'remote_policy' => 'hybrid',
                'contract_type' => 'cdi',
                'salary_range_min' => 180000,
                'salary_range_max' => 260000,
                'skills_required' => ['PHP', 'Laravel', 'PostgreSQL', 'Docker', 'REST'],
                'status' => 'published',
            ],
            'frontend' => [
                'title' => 'Développeur Frontend React / TypeScript',
                'description' => "Vous construirez les interfaces du produit : tableaux de bord RH, portail carrières, écrans de paie.\n\nStack : React, TypeScript, Next.js, Tailwind. Vous travaillerez main dans la main avec le design et le backend.",
                'location' => 'Alger',
                'remote_policy' => 'remote',
                'contract_type' => 'cdi',
                'salary_range_min' => 160000,
                'salary_range_max' => 240000,
                'skills_required' => ['React', 'TypeScript', 'Next.js', 'CSS'],
                'status' => 'published',
            ],
            'data' => [
                'title' => 'Data Analyst RH',
                'description' => "Poste orienté analyse : construction des tableaux de bord de masse salariale, suivi des indicateurs de turnover et d'absentéisme.\n\nVous maîtrisez SQL et un outil de visualisation.",
                'location' => 'Oran',
                'remote_policy' => 'onsite',
                'contract_type' => 'cdd',
                'salary_range_min' => 140000,
                'salary_range_max' => 190000,
                'skills_required' => ['SQL', 'Python', 'Power BI'],
                'status' => 'published',
            ],
            'draft' => [
                'title' => 'Responsable Paie (brouillon)',
                'description' => "Offre en cours de rédaction — sert à vérifier que le brouillon n'apparaît PAS sur la vitrine publique.",
                'location' => 'Alger',
                'remote_policy' => 'onsite',
                'contract_type' => 'cdi',
                'salary_range_min' => 220000,
                'salary_range_max' => 300000,
                'skills_required' => ['Paie', 'Droit social'],
                'status' => 'draft',
            ],
        ];

        $ids = [];

        foreach ($definitions as $key => $definition) {
            $existing = JobPosting::query()
                ->where('company_id', $companyId)
                ->where('title', $definition['title'])
                ->first();

            if ($existing !== null) {
                $ids[$key] = (int) $existing->id;

                continue;
            }

            $job = JobPosting::query()->create([
                'company_id' => $companyId,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'location' => $definition['location'],
                'remote_policy' => $definition['remote_policy'],
                'contract_type' => $definition['contract_type'],
                'salary_range_min' => $definition['salary_range_min'],
                'salary_range_max' => $definition['salary_range_max'],
                'currency' => 'DZD',
                'skills_required' => $definition['skills_required'],
                'status' => $definition['status'],
                'published_at' => $definition['status'] === 'published' ? now()->subDays(5) : null,
                'closes_at' => now()->addDays(30),
            ]);

            $ids[$key] = (int) $job->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $jobs
     */
    private function seedApplicants(string $companyId, array $jobs): void
    {
        $extractor = app(ResumeTextExtraction::class);

        $candidates = [
            [
                'job' => 'backend',
                'first_name' => 'Yacine',
                'last_name' => 'Belkacem',
                'email' => 'yacine.belkacem@example.dz',
                'phone' => '+213 555 10 20 30',
                'status' => 'interview',
                'rating' => 4,
                'source' => 'website',
                'cover_letter' => 'Je travaille depuis six ans sur des API Laravel en production et je souhaite rejoindre une équipe produit exigeante.',
                'cv' => ['PHP', 'Laravel', 'PostgreSQL', 'Docker', 'REST', 'Redis', 'PHPUnit'],
            ],
            [
                'job' => 'backend',
                'first_name' => 'Nadia',
                'last_name' => 'Hamdi',
                'email' => 'nadia.hamdi@example.dz',
                'phone' => '+213 555 44 55 66',
                'status' => 'screening',
                'rating' => 3,
                'source' => 'linkedin',
                'cover_letter' => null,
                'cv' => ['PHP', 'Symfony', 'MySQL', 'Docker'],
            ],
            [
                'job' => 'backend',
                'first_name' => 'Sofiane',
                'last_name' => 'Merabet',
                'email' => 'sofiane.merabet@example.dz',
                'phone' => null,
                'status' => 'rejected',
                'rating' => 1,
                'source' => 'website',
                'cover_letter' => 'Profil junior, motivé à apprendre.',
                'cv' => ['HTML', 'CSS', 'JavaScript'],
            ],
            [
                'job' => 'frontend',
                'first_name' => 'Ines',
                'last_name' => 'Bouzid',
                'email' => 'ines.bouzid@example.dz',
                'phone' => '+213 661 77 88 99',
                'status' => 'offer',
                'rating' => 5,
                'source' => 'referral',
                'cover_letter' => 'Je conçois des interfaces accessibles depuis huit ans, dont quatre sur Next.js.',
                'cv' => ['React', 'TypeScript', 'Next.js', 'CSS', 'Tailwind', 'Accessibilité'],
            ],
            [
                'job' => 'frontend',
                'first_name' => 'Riad',
                'last_name' => 'Kaci',
                'email' => 'riad.kaci@example.dz',
                'phone' => null,
                'status' => 'new',
                'rating' => null,
                'source' => 'website',
                'cover_letter' => null,
                'cv' => ['React', 'JavaScript', 'CSS'],
            ],
            [
                'job' => 'data',
                'first_name' => 'Leila',
                'last_name' => 'Saidi',
                'email' => 'leila.saidi@example.dz',
                'phone' => '+213 770 12 34 56',
                'status' => 'hired',
                'rating' => 5,
                'source' => 'agency',
                'cover_letter' => "Analyste data avec cinq ans d'expérience sur les indicateurs RH.",
                'cv' => ['SQL', 'Python', 'Power BI', 'PostgreSQL', 'Excel'],
            ],
        ];

        foreach ($candidates as $candidate) {
            $jobId = $jobs[$candidate['job']] ?? null;

            if ($jobId === null) {
                continue;
            }

            $exists = Applicant::query()
                ->where('company_id', $companyId)
                ->where('job_posting_id', $jobId)
                ->where('email', $candidate['email'])
                ->exists();

            if ($exists) {
                continue;
            }

            $resumePath = $this->writeResume($candidate['first_name'], $candidate['last_name'], $candidate['cv']);
            $absolute = Storage::disk('local')->path($resumePath);
            $text = $extractor->extract($absolute, basename($resumePath));

            Applicant::query()->create([
                'company_id' => $companyId,
                'job_posting_id' => $jobId,
                'first_name' => $candidate['first_name'],
                'last_name' => $candidate['last_name'],
                'email' => $candidate['email'],
                'phone' => $candidate['phone'],
                'resume_path' => $resumePath,
                'cover_letter' => $candidate['cover_letter'],
                'source' => $candidate['source'],
                'status' => $candidate['status'],
                'rating' => $candidate['rating'],
                'applied_at' => now()->subDays(random_int(1, 20)),
                'consent_given_at' => now()->subDays(20),
                'retention_until' => now()->addMonths(24),
                'resume_scan_status' => 'clean',
                'resume_scan_engine' => 'recette',
                'resume_scanned_at' => now(),
                'resume_text' => $text,
                'resume_text_extracted_at' => $text !== null ? now() : null,
            ]);
        }
    }

    /**
     * Écrit un vrai PDF (texte, pas image) contenant les compétences du
     * candidat — c'est ce que `pdftotext` doit savoir relire.
     *
     * @param  list<string>  $skills
     */
    private function writeResume(string $firstName, string $lastName, array $skills): string
    {
        $lines = [
            $firstName.' '.$lastName,
            'Developpeur — Alger, Algerie',
            '',
            'EXPERIENCE',
            '2021 - 2026 : Developpeur senior, societe de services numeriques.',
            '2019 - 2021 : Developpeur, startup fintech.',
            '',
            'COMPETENCES',
            implode(', ', $skills),
            '',
            'FORMATION',
            'Master informatique, USTHB Alger.',
        ];

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

        $path = 'recruitment/recette/'.Str::slug($firstName.'-'.$lastName).'.pdf';
        Storage::disk('local')->put($path, $pdf);

        return $path;
    }
}
