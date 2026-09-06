<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\ApplicantAlreadyAppliedException;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Database\QueryException;

/**
 * Cas d'usage : dépôt d'une candidature (manager ou portail public).
 *
 * Issue #3860 — pas de doublon (job_posting_id, email) : garde applicative
 * + catch 23505 pour la course entre le check et le create (index unique).
 */
class CreateApplicantAction
{
    /**
     * @param  array<string, mixed>  $data  champs validés (sans company_id/job_posting_id)
     *
     * @throws ApplicantAlreadyAppliedException
     */
    public function execute(int $companyId, JobPosting $jobPosting, array $data): Applicant
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
            return Applicant::create([
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
    }
}
