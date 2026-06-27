<?php

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
    App\Modules\HR\Providers\HRServiceProvider::class,
    App\Modules\Payroll\Providers\PayrollServiceProvider::class,
    App\Modules\Attendance\Providers\AttendanceServiceProvider::class,
    App\Modules\Planning\Providers\PlanningServiceProvider::class,
    App\Modules\Recruitment\Providers\RecruitmentServiceProvider::class,
    App\Modules\Cabinet\Providers\CabinetServiceProvider::class,
    App\Modules\Fleet\Providers\FleetServiceProvider::class,
    App\Modules\Billing\Providers\BillingServiceProvider::class,
    App\Modules\Cameras\Providers\CamerasServiceProvider::class,
];
