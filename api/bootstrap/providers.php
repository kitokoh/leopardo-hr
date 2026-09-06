<?php

use App\Modules\Absence\Providers\AbsenceServiceProvider;
use App\Modules\Accounting\Providers\AccountingServiceProvider;
use App\Modules\Attendance\Providers\AttendanceServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Cabinet\Providers\CabinetServiceProvider;
use App\Modules\Cameras\Providers\CamerasServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\CRM\Providers\CrmServiceProvider;
use App\Modules\Delivery\Providers\DeliveryServiceProvider;
use App\Modules\EdgeSync\Providers\EdgeSyncServiceProvider;
use App\Modules\EduManager\Providers\EduManagerServiceProvider;
use App\Modules\Expense\Providers\ExpenseServiceProvider;
use App\Modules\Fleet\Providers\FleetServiceProvider;
use App\Modules\FuelStation\Providers\FuelStationServiceProvider;
use App\Modules\Growth\Providers\GrowthServiceProvider;
use App\Modules\HR\Providers\HRServiceProvider;
use App\Modules\Marketing\Providers\MarketingServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Onboarding\Providers\OnboardingServiceProvider;
use App\Modules\Payroll\Providers\PayrollServiceProvider;
use App\Modules\Planning\Providers\PlanningServiceProvider;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use App\Modules\Recruitment\Providers\RecruitmentServiceProvider;
use App\Modules\Restaurant\Providers\RestaurantServiceProvider;
use App\Modules\RestaurantManager\Providers\RestaurantManagerServiceProvider;
use App\Modules\Showcase\Providers\ShowcaseServiceProvider;
use App\Modules\TravelAgency\Providers\TravelAgencyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FeatureDetectionServiceProvider;
use App\Providers\FeatureRegistryServiceProvider;
use App\Providers\QueueCorrelationServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    FeatureDetectionServiceProvider::class,
    FeatureRegistryServiceProvider::class,
    QueueCorrelationServiceProvider::class,
    // — Module providers (ordre stable)
    HRServiceProvider::class,
    PayrollServiceProvider::class,
    AttendanceServiceProvider::class,
    PlanningServiceProvider::class,
    RecruitmentServiceProvider::class,
    RestaurantServiceProvider::class,
    CabinetServiceProvider::class,
    FleetServiceProvider::class,
    BillingServiceProvider::class,
    CamerasServiceProvider::class,
    CrmServiceProvider::class,
    GrowthServiceProvider::class,
    AbsenceServiceProvider::class,
    ExpenseServiceProvider::class,
    NotificationServiceProvider::class,
    PlatformServiceProvider::class,
    OnboardingServiceProvider::class,
    EdgeSyncServiceProvider::class,
    MarketingServiceProvider::class,
    AccountingServiceProvider::class,
    DeliveryServiceProvider::class,
    FuelStationServiceProvider::class,
    TravelAgencyServiceProvider::class,
    EduManagerServiceProvider::class,
    RestaurantManagerServiceProvider::class,
    CatalogServiceProvider::class,
    ShowcaseServiceProvider::class,
];
