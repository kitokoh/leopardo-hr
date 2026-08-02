<?php

namespace App\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Attendance\Domain\Models\ApprovalRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Domain\CameraAccessToken;
use App\Modules\Billing\Domain\Models\FeaturePlanMatrix;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\Evaluation;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\HR\Domain\Models\Position;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
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
use App\Policies\SocialAccountPolicy;
use App\Policies\SocialPostPolicy;
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
        // PA2-ARCH-008 : divergence tranchee. AppServiceProvider et AuthServiceProvider
        // enregistraient chacun une policy differente pour Invoice (BillingPolicy vs
        // InvoicePolicy). InvoicePolicy est plus fine (scoping company_id + roles
        // dedies view/create/pay) : c'est la policy retenue, unique point d'enregistrement.
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(EmployeeLoan::class, LoanPolicy::class);
        Gate::policy(ExpenseClaim::class, ExpenseClaimPolicy::class);
        Gate::policy(PayrollRun::class, PayrollPolicy::class);
        Gate::policy(PaySlip::class, PayrollPolicy::class);
        Gate::policy(FeaturePlanMatrix::class, FeatureFlagPolicy::class);
        Gate::policy(OnboardingStep::class, OnboardingPolicy::class);

        // Recruitment & Training
        Gate::policy(JobPosting::class, RecruitmentPolicy::class);
        Gate::policy(Applicant::class, RecruitmentPolicy::class);
        Gate::policy(TrainingCourse::class, TrainingPolicy::class);

        // Fleet
        Gate::policy(Vehicle::class, VehiclePolicy::class);

        // Platform
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        // Marketing (Phase 2)
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);
        Gate::policy(SocialPost::class, SocialPostPolicy::class);

        // Gate definitions
        Gate::define('manage-billing', [BillingPolicy::class, 'manageSubscription']);
        Gate::define('manage-onboarding', [OnboardingPolicy::class, 'manageSteps']);
        Gate::define('manage-features', [FeatureFlagPolicy::class, 'manageMatrix']);
        Gate::define('export-data', [ExportPolicy::class, 'export']);
    }
}
