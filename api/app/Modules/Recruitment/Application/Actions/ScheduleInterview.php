<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\ApplicantNotFoundException;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\Interview;
use App\Modules\Recruitment\Infrastructure\Services\RecruitmentService;

class ScheduleInterview
{
    public function __construct(
        private readonly RecruitmentService $recruitmentService,
    ) {}

    /**
     * @throws ApplicantNotFoundException
     */
    public function handle(string $applicantId, array $interviewData): Interview
    {
        $applicant = Applicant::query()->find($applicantId);

        if (! $applicant instanceof Applicant) {
            throw new ApplicantNotFoundException($applicantId);
        }

        return $this->recruitmentService->scheduleInterview($applicant, $interviewData);
    }
}
