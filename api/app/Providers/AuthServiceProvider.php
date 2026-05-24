<?php

namespace App\Providers;

use App\Models\Absence;
use App\Models\Applicant;
use App\Models\ApprovalRequest;
use App\Models\AttendanceLog;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Evaluation;
use App\Models\ExpenseClaim;
use App\Models\Invoice;
use App\Models\JobPosting;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\TrainingCourse;
use App\Models\Vehicle;
use App\Models\WebhookEndpoint;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Domain\CameraAccessToken;
use App\Policies\AbsencePolicy;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\BillingPolicy;
use App\Policies\Cameras\CameraPolicy;
use App\Policies\ContractPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\ExpenseClaimPolicy;
use App\Policies\ExportPolicy;
use App\Policies\FeatureFlagPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LoanPolicy;
use App\Policies\OnboardingPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PositionPolicy;
use App\Policies\RecruitmentPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SitePolicy;
use App\Policies\TrainingPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WebhookEndpointPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Core models
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(AttendanceLog::class, AttendancePolicy::class);
        Gate::policy(Evaluation::class, EvaluationPolicy::class);
        Gate::policy(Camera::class, CameraPolicy::class);
        Gate::policy(CameraAccessToken::class, CameraPolicy::class);

        // Org structure
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(Site::class, SitePolicy::class);

        // HR workflows
        Gate::policy(Absence::class, AbsencePolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);

        // Finance
        Gate::policy(Subscription::class, BillingPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(EmployeeLoan::class, LoanPolicy::class);
        Gate::policy(ExpenseClaim::class, ExpenseClaimPolicy::class);
        Gate::policy(PayrollRun::class, PayrollPolicy::class);
        Gate::policy(PaySlip::class, PayrollPolicy::class);

        // Recruitment & Training
        Gate::policy(JobPosting::class, RecruitmentPolicy::class);
        Gate::policy(Applicant::class, RecruitmentPolicy::class);
        Gate::policy(TrainingCourse::class, TrainingPolicy::class);

        // Fleet
        Gate::policy(Vehicle::class, VehiclePolicy::class);

        // Platform
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        // Gate definitions
        Gate::define('manage-billing', [BillingPolicy::class, 'manageSubscription']);
        Gate::define('manage-onboarding', [OnboardingPolicy::class, 'manageSteps']);
        Gate::define('manage-features', [FeatureFlagPolicy::class, 'manageMatrix']);
        Gate::define('export-data', [ExportPolicy::class, 'export']);
    }
}
