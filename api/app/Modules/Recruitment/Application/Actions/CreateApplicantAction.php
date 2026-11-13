<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Application\Services\ApplicantStatusJournal;
use App\Modules\Recruitment\Domain\Exceptions\ApplicantAlreadyAppliedException;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Database\QueryException;

/**
 * Cas d'usage : dépôt d'une candidature (manager ou portail public).
 *
 * Issue #3860 — pas de doublon (job_posting_id, email) : garde applicative
 * + catch 23505 pour la course entre le check et le create (index unique).
 *
 * ATS — chaque dossier créé ouvre son journal de statuts : sans cette entrée
 * initiale, l'historique d'un candidat commencerait au premier déplacement, et
 * « quand a-t-il postulé ? » n'aurait plus de réponse fiable.
 */
class CreateApplicantAction
{
    public function __construct(
        private readonly ApplicantStatusJournal $journal,
    ) {}

    /**
     * @param  array<string, mixed>  $data  champs validés (sans company_id/job_posting_id)
     * @param  array{id?: int|null, name?: string|null}  $actor  auteur (vide = dépôt public)
     *
     * @throws ApplicantAlreadyAppliedException
     */
    public function execute(string $companyId, JobPosting $jobPosting, array $data, array $actor = []): Applicant
    {
        $alreadyApplied = Applicant::query()
            ->where('company_id', $companyId)
            ->where('job_posting_id', $jobPosting->id)
            ->where('email', $data['email'])
            ->exists();

        if ($alreadyApplied) {
            throw new ApplicantAlreadyAppliedException;
        }

        try {
            $applicant = Applicant::create([
                ...$data,
                'company_id' => $companyId,
                'job_posting_id' => $jobPosting->id,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new ApplicantAlreadyAppliedException;
            }

            throw $e;
        }

        $this->journal->recordCreation($applicant, $actor);

        return $applicant;
    }
}
