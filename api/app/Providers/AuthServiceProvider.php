<?php

namespace App\Providers;

use App\Models\Applicant;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\JobPosting;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\Subscription;
use App\Models\TrainingCourse;
use App\Models\Vehicle;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Domain\CameraAccessToken;
use App\Policies\AttendancePolicy;
use App\Policies\BillingPolicy;
use App\Policies\Cameras\CameraPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\ExportPolicy;
use App\Policies\FeatureFlagPolicy;
use App\Policies\OnboardingPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\RecruitmentPolicy;
use App\Policies\TrainingPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(AttendanceLog::class, AttendancePolicy::class);
        Gate::policy(Evaluation::class, EvaluationPolicy::class);
        Gate::policy(Camera::class, CameraPolicy::class);
        Gate::policy(CameraAccessToken::class, CameraPolicy::class);
        Gate::policy(Subscription::class, BillingPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(PayrollRun::class, PayrollPolicy::class);
        Gate::policy(PaySlip::class, PayrollPolicy::class);
        Gate::policy(JobPosting::class, RecruitmentPolicy::class);
        Gate::policy(Applicant::class, RecruitmentPolicy::class);
        Gate::policy(TrainingCourse::class, TrainingPolicy::class);

        Gate::define('manage-billing', [BillingPolicy::class, 'manageSubscription']);
        Gate::define('manage-onboarding', [OnboardingPolicy::class, 'manageSteps']);
        Gate::define('manage-features', [FeatureFlagPolicy::class, 'manageMatrix']);
        Gate::define('export-data', [ExportPolicy::class, 'export']);
    }
}
