<?php

namespace App\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Attendance\Domain\Models\ApprovalRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Billing\Domain\Models\FeaturePlanMatrix;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Domain\CameraAccessToken;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduCourseSlot;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use App\Modules\EduManager\Policies\EduAcademicYearPolicy;
use App\Modules\EduManager\Policies\EduAdmissionPolicy;
use App\Modules\EduManager\Policies\EduAssessmentPolicy;
use App\Modules\EduManager\Policies\EduAttendancePolicy;
use App\Modules\EduManager\Policies\EduCampusPolicy;
use App\Modules\EduManager\Policies\EduClassEnrollmentPolicy;
use App\Modules\EduManager\Policies\EduClassPolicy;
use App\Modules\EduManager\Policies\EduCourseSlotPolicy;
use App\Modules\EduManager\Policies\EduFeePolicy;
use App\Modules\EduManager\Policies\EduGradePolicy;
use App\Modules\EduManager\Policies\EduGuardianPolicy;
use App\Modules\EduManager\Policies\EduReportCardPolicy;
use App\Modules\EduManager\Policies\EduStudentGuardianPolicy;
use App\Modules\EduManager\Policies\EduStudentPolicy;
use App\Modules\EduManager\Policies\EduSubjectPolicy;
use App\Modules\EduManager\Policies\EduTeacherSubjectPolicy;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use App\Modules\FuelStation\Domain\Policies\FuelCashSessionPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelSalePolicy;
use App\Modules\FuelStation\Domain\Policies\FuelShiftPolicy;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\Evaluation;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\HR\Domain\Models\Position;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
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
use App\Policies\FuelMeterReadingPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LoanPolicy;
use App\Policies\OnboardingPolicy;
use App\Policies\PayrollAuditPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PositionPolicy;
use App\Policies\PublicHolidayPolicy;
use App\Policies\RateValidationPolicy;
use App\Policies\RecruitmentPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SitePolicy;
use App\Policies\SocialAccountPolicy;
use App\Policies\SocialContributionPolicy;
use App\Policies\SocialPostPolicy;
use App\Policies\TaxSlabPolicy;
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
        Gate::policy(FuelMeterReading::class, FuelMeterReadingPolicy::class);
        Gate::policy(FuelMeterInterval::class, FuelMeterReadingPolicy::class);
        // — FuelStation (FUEL-005 #5799, FUEL-007 #5801, FUEL-008 #5802)
        Gate::policy(FuelShift::class, FuelShiftPolicy::class);
        Gate::policy(FuelShiftAssignment::class, FuelShiftPolicy::class);
        Gate::policy(FuelCashSession::class, FuelCashSessionPolicy::class);
        Gate::policy(FuelSale::class, FuelSalePolicy::class);
        // — EduManager (EDU-002, issue #5818)
        Gate::policy(EduCampus::class, EduCampusPolicy::class);
        Gate::policy(EduStudent::class, EduStudentPolicy::class);
        Gate::policy(EduGuardian::class, EduGuardianPolicy::class);
        Gate::policy(EduStudentGuardian::class, EduStudentGuardianPolicy::class);
        // — EduManager RBAC scolaire (EDU-009, #5825)
        Gate::policy(EduAcademicYear::class, EduAcademicYearPolicy::class);
        Gate::policy(EduSubject::class, EduSubjectPolicy::class);
        Gate::policy(EduClass::class, EduClassPolicy::class);
        Gate::policy(EduTeacherSubject::class, EduTeacherSubjectPolicy::class);
        Gate::policy(EduAdmission::class, EduAdmissionPolicy::class);
        Gate::policy(EduAttendance::class, EduAttendancePolicy::class);
        Gate::policy(EduCourseSlot::class, EduCourseSlotPolicy::class);
        Gate::policy(EduAssessment::class, EduAssessmentPolicy::class);
        Gate::policy(EduGrade::class, EduGradePolicy::class);
        Gate::policy(EduReportCard::class, EduReportCardPolicy::class);
        // — EduManager batch 3 (#5827/#5832) : inscriptions, frais & Accounting
        Gate::policy(EduClassEnrollment::class, EduClassEnrollmentPolicy::class);
        Gate::policy(EduFeeType::class, EduFeePolicy::class);
        Gate::policy(EduFeeCharge::class, EduFeePolicy::class);
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

        // Taux légaux & jours fériés (issue #1917 — Policies Laravel)
        Gate::policy(TaxSlab::class, TaxSlabPolicy::class);
        Gate::policy(SocialContribution::class, SocialContributionPolicy::class);
        Gate::policy(PublicHoliday::class, PublicHolidayPolicy::class);
        Gate::policy(TaxRateChangeLog::class, RateValidationPolicy::class);
        // Issue #1874 — audit des calculs de paie (manager principal/RH +
        // platform admin, isolation tenant stricte).
        Gate::policy(PayrollCalculationAudit::class, PayrollAuditPolicy::class);
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
