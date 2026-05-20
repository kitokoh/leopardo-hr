<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrontendApiContractTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function criticalFrontendRoutes(): array
    {
        return [
            'mobile login' => ['POST', 'api/v1/auth/login'],
            'mobile me' => ['GET', 'api/v1/auth/me'],
            'mobile logout' => ['POST', 'api/v1/auth/logout'],
            'admin dashboard summary' => ['GET', 'api/v1/dashboard/summary'],
            'admin audit export' => ['GET', 'api/v1/audit-logs/export-csv'],
            'admin employees export' => ['GET', 'api/v1/export/employees'],
            'admin contracts export' => ['GET', 'api/v1/export/contracts'],
            'admin vehicles export' => ['GET', 'api/v1/export/vehicles'],
            'admin pay slips export' => ['GET', 'api/v1/export/pay-slips'],
            'admin absences export' => ['GET', 'api/v1/export/absences'],
            'admin training export' => ['GET', 'api/v1/export/training'],
            'kiosk register' => ['POST', 'api/v1/kiosks'],
            'kiosk punch' => ['POST', 'api/v1/kiosks/{deviceCode}/punch'],
            'kiosk qr punch' => ['POST', 'api/v1/kiosks/{deviceCode}/qr-punch'],
            'kiosk sync' => ['POST', 'api/v1/kiosks/{deviceCode}/sync'],
            'kiosk roster' => ['GET', 'api/v1/kiosks/{deviceCode}/roster'],
            'kiosk employee info' => ['POST', 'api/v1/kiosks/{deviceCode}/employee-info'],
            'kiosk leave balance' => ['POST', 'api/v1/kiosks/{deviceCode}/leave-balance'],
            'kiosk announcements' => ['GET', 'api/v1/kiosks/{deviceCode}/announcements'],
        ];
    }

    #[DataProvider('criticalFrontendRoutes')]
    public function test_critical_frontend_route_contract_exists(string $method, string $uri): void
    {
        $routesByMethod = Route::getRoutes()->getRoutesByMethod();
        $exists = array_key_exists($uri, $routesByMethod[$method] ?? []);

        $this->assertTrue($exists, "Missing {$method} {$uri}");
    }
}
