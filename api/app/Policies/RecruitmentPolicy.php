<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\JobPosting;

class RecruitmentPolicy
{
    public function viewJobs(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function createJob(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function updateJob(Employee $actor, JobPosting $job): bool
    {
        return $actor->company_id === $job->company_id && $actor->hasManagerRole('principal', 'rh');
    }

    public function deleteJob(Employee $actor, JobPosting $job): bool
    {
        return $actor->company_id === $job->company_id && $actor->hasManagerRole('principal');
    }

    public function viewApplicants(Employee $actor, JobPosting $job): bool
    {
        return $actor->company_id === $job->company_id && $actor->isManager();
    }

    public function manageApplicant(Employee $actor, Applicant $applicant): bool
    {
        return $actor->isManager();
    }

    public function scheduleInterview(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
