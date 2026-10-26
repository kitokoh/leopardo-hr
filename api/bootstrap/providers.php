<?php

use App\Modules\Absence\Providers\AbsenceServiceProvider;
use App\Modules\Accounting\Providers\AccountingServiceProvider;
use App\Modules\Attendance\Providers\AttendanceServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Cabinet\Providers\CabinetServiceProvider;
use App\Modules\Cameras\Providers\CamerasServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Communication\Providers\CommunicationServiceProvider;
use App\Modules\CRM\Providers\CrmServiceProvider;
use App\Modules\Delivery\Providers\DeliveryServiceProvider;
use App\Modules\EdgeSync\Providers\EdgeSyncServiceProvider;
use App\Modules\EduManager\Providers\EduManagerServiceProvider;
use App\Modules\Expense\Providers\ExpenseServiceProvider;
use App\Modules\FarmOs\Providers\FarmOsServiceProvider;
use App\Modules\Fleet\Providers\FleetServiceProvider;
use App\Modules\FuelStation\Providers\FuelStationServiceProvider;
use App\Modules\Fundraising\Providers\FundraisingServiceProvider;
use App\Modules\Geo\Providers\GeoServiceProvider;
use App\Modules\HealthManager\Providers\HealthManagerServiceProvider;
use App\Modules\HospitalityManager\Providers\HospitalityManagerServiceProvider;
use App\Modules\HR\Providers\HRServiceProvider;
use App\Modules\Marketing\Providers\MarketingServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Onboarding\Providers\OnboardingServiceProvider;
use App\Modules\Paiements\Providers\PaiementsServiceProvider;
// BC-38 PAIEMENTS (#8680) — socle de paiement interne + registre de fournisseurs.
use App\Modules\Payroll\Providers\PayrollServiceProvider;
use App\Modules\Pharmacy\Providers\PharmacyServiceProvider;
use App\Modules\Planning\Providers\PlanningServiceProvider;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use App\Modules\Recruitment\Providers\RecruitmentServiceProvider;
use App\Modules\Restaurant\Providers\RestaurantServiceProvider;
use App\Modules\RestaurantManager\Providers\RestaurantManagerServiceProvider;
use App\Modules\Retail\Providers\RetailServiceProvider;
use App\Modules\Showcase\Providers\ShowcaseServiceProvider;
// GSI-1 (#8512) — module Growth & Search Intelligence (SEO/SEA/AEO/GEO).
use App\Modules\SearchIntelligence\Providers\SearchIntelligenceServiceProvider;
use App\Modules\Trade\Providers\TradeServiceProvider;
use App\Modules\TravelAgency\Providers\TravelAgencyServiceProvider;
use App\Modules\Vtc\Providers\VtcServiceProvider;
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
    AbsenceServiceProvider::class,
    ExpenseServiceProvider::class,
    NotificationServiceProvider::class,
    PlatformServiceProvider::class,
    // GSI-1 (#8512) — pilotage Growth & Search Intelligence depuis la console
    // plateforme (routes chargées via routes/api.php).
    SearchIntelligenceServiceProvider::class,
    OnboardingServiceProvider::class,
    EdgeSyncServiceProvider::class,
    MarketingServiceProvider::class,
    AccountingServiceProvider::class,
    DeliveryServiceProvider::class,
    FuelStationServiceProvider::class,
    FundraisingServiceProvider::class,
    TravelAgencyServiceProvider::class,
    // PHARMA-001 (#7798) — verticale PharmaManager (officines de pharmacie).
    PharmacyServiceProvider::class,
    EduManagerServiceProvider::class,
    HealthManagerServiceProvider::class,
    RestaurantManagerServiceProvider::class,
    CatalogServiceProvider::class,
    RetailServiceProvider::class,
    ShowcaseServiceProvider::class,
    CommunicationServiceProvider::class,
    // HOSP-001 (#7943) — verticale HospitalityManager (BC-32, hôtels &
    // résidences) : enregistre le manifest `hospitality` au catalogue.
    HospitalityManagerServiceProvider::class,
    // BC-34 GEO (GEO-02/#8351) — core géospatial transverse (PostGIS).
    GeoServiceProvider::class,
    // BC-34 VTC (VTC-01/#8357) — verticale VTC/taxi : enregistre le manifest
    // `vtc` au catalogue des solutions (module requis : geo).
    VtcServiceProvider::class,
    // BC-37 FARM OS — verticale agricole : enregistre le manifest `farm_os` au
    // catalogue des solutions (module requis : rh) — sans cet enregistrement,
    // SolutionActivator refuse l'activation au provisioning (fail-closed).
    FarmOsServiceProvider::class,
    // BC-36 TRADE (TRADE-001/#8476) — verticale International Trade OS :
    // enregistre le manifest `trade` au catalogue des solutions (aucun module
    // requis — activation possible sur tenant frais).
    TradeServiceProvider::class,
    // BC-38 PAIEMENTS (#8680) — socle de paiement interne : registre des types
    // de fournisseur (code) + services ledger / resolver / reconciliation.
    PaiementsServiceProvider::class,
];
