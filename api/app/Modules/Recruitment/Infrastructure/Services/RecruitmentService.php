<?php

namespace App\Modules\Recruitment\Infrastructure\Services;

use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\Interview;
use App\Modules\Recruitment\Domain\Models\JobPosting;

class RecruitmentService
{
    /**
     * Publish a job posting.
     */
    public function publishJob(JobPosting $jobPosting): JobPosting
    {
        $jobPosting->status = 'published';
        $jobPosting->published_at = now();
        $jobPosting->save();

        return $jobPosting;
    }

    /**
     * Schedule an interview for an applicant.
     */
    public function scheduleInterview(Applicant $applicant, array $interviewData): Interview
    {
        return Interview::query()->create(array_merge(
            $interviewData,
            ['applicant_id' => $applicant->id]
        ));
    }

    /**
     * Get applicants for a job posting.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Applicant>
     */
    public function getApplicants(JobPosting $jobPosting): \Illuminate\Database\Eloquent\Collection
    {
        return Applicant::query()
            ->where('job_posting_id', $jobPosting->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
