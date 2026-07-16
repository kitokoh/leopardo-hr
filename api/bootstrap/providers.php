<?php

use App\Modules\Absence\Providers\AbsenceServiceProvider;
use App\Modules\Attendance\Providers\AttendanceServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Cabinet\Providers\CabinetServiceProvider;
use App\Modules\Cameras\Providers\CamerasServiceProvider;
use App\Modules\EdgeSync\Providers\EdgeSyncServiceProvider;
use App\Modules\Expense\Providers\ExpenseServiceProvider;
use App\Modules\Fleet\Providers\FleetServiceProvider;
use App\Modules\Growth\Providers\GrowthServiceProvider;
use App\Modules\HR\Providers\HRServiceProvider;
use App\Modules\Marketing\Providers\MarketingServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Onboarding\Providers\OnboardingServiceProvider;
use App\Modules\Payroll\Providers\PayrollServiceProvider;
use App\Modules\Planning\Providers\PlanningServiceProvider;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use App\Modules\Recruitment\Providers\RecruitmentServiceProvider;
use App\Modules\SmartAttendance\Providers\SmartAttendanceServiceProvider;
use App\Modules\Training\Providers\TrainingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FeatureDetectionServiceProvider;
use App\Providers\FeatureRegistryServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    FeatureDetectionServiceProvider::class,
    FeatureRegistryServiceProvider::class,
    HRServiceProvider::class,
    PayrollServiceProvider::class,
    AttendanceServiceProvider::class,
    PlanningServiceProvider::class,
    RecruitmentServiceProvider::class,
    CabinetServiceProvider::class,
    FleetServiceProvider::class,
    BillingServiceProvider::class,
    CamerasServiceProvider::class,
    // — New DDD modules (Phase 2)
    GrowthServiceProvider::class,
    AbsenceServiceProvider::class,
    // — SmartAttendance module
    SmartAttendanceServiceProvider::class,
    ExpenseServiceProvider::class,
    NotificationServiceProvider::class,
    // — New DDD modules (Phase 3–4)
    PlatformServiceProvider::class,
    OnboardingServiceProvider::class,
    TrainingServiceProvider::class,
    // — EdgeSync module
    EdgeSyncServiceProvider::class,
    // — Marketing module (Phase 1)
    MarketingServiceProvider::class,
];
