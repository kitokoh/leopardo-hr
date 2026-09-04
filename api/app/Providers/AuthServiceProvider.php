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
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmImport;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Policies\CrmImportPolicy;
use App\Modules\CRM\Policies\CrmLeadPolicy;
use App\Modules\CRM\Policies\CrmMergePolicy;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduCourseSlot;
use App\Modules\EduManager\Domain\Models\EduFee;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduImport;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use App\Modules\EduManager\Domain\Policies\EduAcademicYearPolicy;
use App\Modules\EduManager\Domain\Policies\EduAssessmentPolicy;
use App\Modules\EduManager\Domain\Policies\EduAttendancePolicy;
use App\Modules\EduManager\Domain\Policies\EduClassPolicy;
use App\Modules\EduManager\Domain\Policies\EduCourseSlotPolicy;
use App\Modules\EduManager\Domain\Policies\EduFeePolicy;
use App\Modules\EduManager\Domain\Policies\EduGradePolicy;
use App\Modules\EduManager\Domain\Policies\EduImportPolicy;
use App\Modules\EduManager\Domain\Policies\EduReportCardPolicy;
use App\Modules\EduManager\Domain\Policies\EduSubjectPolicy;
use App\Modules\EduManager\Domain\Policies\EduTeacherSubjectPolicy;
use App\Modules\EduManager\Policies\EduAdmissionPolicy;
use App\Modules\EduManager\Policies\EduCampusPolicy;
use App\Modules\EduManager\Policies\EduGuardianPolicy;
use App\Modules\EduManager\Policies\EduStudentGuardianPolicy;
use App\Modules\EduManager\Policies\EduStudentPolicy;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\FuelStation\Domain\Models\FuelAccountVisit;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;
use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelProfessionalAccount;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelReportExport;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockEntry;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
use App\Modules\FuelStation\Domain\Policies\FuelAlertPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelCashSessionPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelCrmPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelCustomerPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelEquipmentPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelImportPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelIncidentPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelMaintenancePolicy;
use App\Modules\FuelStation\Domain\Policies\FuelMaintenanceTaskPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelMetricsPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelOutboxPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelProductPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelReferencePolicy;
use App\Modules\FuelStation\Domain\Policies\FuelReportPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelSalePolicy;
use App\Modules\FuelStation\Domain\Policies\FuelShiftPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelSitePolicy;
use App\Modules\FuelStation\Domain\Policies\FuelStationPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelStockEntryPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelStockPolicy;
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
        // — EduManager fondations (EDU-002, #5818)
        Gate::policy(EduCampus::class, EduCampusPolicy::class);
        Gate::policy(EduStudent::class, EduStudentPolicy::class);
        Gate::policy(EduGuardian::class, EduGuardianPolicy::class);
        Gate::policy(EduStudentGuardian::class, EduStudentGuardianPolicy::class);
        Gate::policy(EduFee::class, EduFeePolicy::class);
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
        Gate::policy(EduImport::class, EduImportPolicy::class);
        Gate::policy(EduReportCard::class, EduReportCardPolicy::class);
        // — FuelStation batch A (FUEL-009 #5803, FUEL-010 #5804, FUEL-011 #5805,
        //   FUEL-016 #5810) : policies deny-by-default.
        Gate::policy(FuelStation::class, FuelStationPolicy::class);
        Gate::policy(FuelSite::class, FuelSitePolicy::class);
        Gate::policy(FuelPump::class, FuelEquipmentPolicy::class);
        Gate::policy(FuelProduct::class, FuelProductPolicy::class);
        Gate::policy(FuelStockEntry::class, FuelStockEntryPolicy::class);
        Gate::policy(FuelIncident::class, FuelIncidentPolicy::class);
        Gate::policy(FuelMaintenanceTask::class, FuelMaintenanceTaskPolicy::class);
        Gate::policy(FuelCustomer::class, FuelCustomerPolicy::class);
        // — FuelStation stocks & rapprochement (FUEL-009 #5803)
        Gate::policy(FuelTankDelivery::class, FuelStockPolicy::class);
        Gate::policy(FuelReconciliationRun::class, FuelStockPolicy::class);
        Gate::policy(FuelTank::class, FuelStockPolicy::class);
        // — FuelStation incidents & maintenance (FUEL-010 #5804)
        Gate::policy(FuelMaintenanceTask::class, FuelIncidentPolicy::class);
        // — FuelStation référentiel (FUEL-011 #5805)
        Gate::policy(FuelStation::class, FuelReferencePolicy::class);
        Gate::policy(FuelSite::class, FuelReferencePolicy::class);
        Gate::policy(FuelPump::class, FuelReferencePolicy::class);
        Gate::policy(FuelTank::class, FuelReferencePolicy::class);
        Gate::policy(FuelMeterRegister::class, FuelReferencePolicy::class);
        Gate::policy(FuelProduct::class, FuelReferencePolicy::class);
        // — FuelStation intégration CRM (FUEL-016 #5810)
        Gate::policy(FuelProfessionalAccount::class, FuelCrmPolicy::class);
        Gate::policy(FuelAccountVisit::class, FuelCrmPolicy::class);
        // — FuelStation reporting (FUEL-017 #5811)
        Gate::policy(FuelReportSnapshot::class, FuelReferencePolicy::class);
        // — FuelStation import/export (FUEL-018 #5812)
        Gate::policy(FuelImport::class, FuelReferencePolicy::class);
        // — FuelStation (FUEL-009 #5803 : stocks, livraisons, rapprochements)
        Gate::policy(FuelStockMovement::class, FuelStockPolicy::class);
        Gate::policy(FuelDelivery::class, FuelStockPolicy::class);
        Gate::policy(FuelStockReconciliation::class, FuelStockPolicy::class);
        // — FuelStation (FUEL-010 #5804 : incidents, maintenance, tâches)
        Gate::policy(FuelIncident::class, FuelMaintenancePolicy::class);
        Gate::policy(FuelMaintenanceTask::class, FuelMaintenancePolicy::class);
        // — FuelStation (FUEL-018 #5812 : imports CSV)
        Gate::policy(FuelImport::class, FuelImportPolicy::class);
        // — FuelStation (FUEL-011 #5805 : référentiel manager)
        Gate::policy(FuelSite::class, FuelStationPolicy::class);
        Gate::policy(FuelTank::class, FuelEquipmentPolicy::class);
        Gate::policy(FuelMeterRegister::class, FuelEquipmentPolicy::class);
        // — FuelStation (FUEL-015 #5809 : outbox contrat Accounting)
        Gate::policy(FuelOutboxEvent::class, FuelOutboxPolicy::class);
        // — FuelStation (FUEL-019 #5813 : alertes & préférences)
        Gate::policy(FuelAlert::class, FuelAlertPolicy::class);
        Gate::policy(FuelNotificationPreference::class, FuelAlertPolicy::class);
        // — FuelStation (FUEL-020 #5814 : métriques d'observabilité, sans modèle)
        Gate::define('fuel.metrics', [FuelMetricsPolicy::class, 'viewAny']);
        // — FuelStation (FUEL-017 #5811 : reporting opérationnel)
        Gate::policy(FuelReportSnapshot::class, FuelReportPolicy::class);
        Gate::policy(FuelReportExport::class, FuelReportPolicy::class);
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

        // CRM client (PA2-ARCH-008 — point d'enregistrement unique, #6575)
        Gate::policy(CrmImport::class, CrmImportPolicy::class);
        Gate::policy(CrmLead::class, CrmLeadPolicy::class);
        Gate::policy(CrmAccount::class, CrmMergePolicy::class);

        // Gate definitions
        Gate::define('manage-billing', [BillingPolicy::class, 'manageSubscription']);
        Gate::define('manage-onboarding', [OnboardingPolicy::class, 'manageSteps']);
        Gate::define('manage-features', [FeatureFlagPolicy::class, 'manageMatrix']);
        Gate::define('export-data', [ExportPolicy::class, 'export']);
    }
}
