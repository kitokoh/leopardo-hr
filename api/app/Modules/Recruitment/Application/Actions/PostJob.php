<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\JobPostingNotFoundException;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use App\Modules\Recruitment\Infrastructure\Services\RecruitmentService;

class PostJob
{
    public function __construct(
        private readonly RecruitmentService $recruitmentService,
    ) {}

    /**
     * @throws JobPostingNotFoundException
     */
    public function handle(string $jobPostingId): JobPosting
    {
        $jobPosting = JobPosting::query()->find($jobPostingId);

        if (! $jobPosting instanceof JobPosting) {
            throw new JobPostingNotFoundException($jobPostingId);
        }

        return $this->recruitmentService->publishJob($jobPosting);
    }
}
