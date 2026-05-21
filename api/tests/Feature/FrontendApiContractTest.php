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
            'mobile language' => ['PATCH', 'api/v1/auth/language'],
            'mobile change password' => ['POST', 'api/v1/auth/change-password'],
            'mobile logout' => ['POST', 'api/v1/auth/logout'],
            'mobile attendance check in' => ['POST', 'api/v1/attendance/check-in'],
            'mobile attendance check out' => ['POST', 'api/v1/attendance/check-out'],
            'mobile attendance today' => ['GET', 'api/v1/attendance/today'],
            'mobile attendance history' => ['GET', 'api/v1/attendance'],
            'mobile absences list' => ['GET', 'api/v1/absences'],
            'mobile absence create' => ['POST', 'api/v1/absences'],
            'mobile absence detail' => ['GET', 'api/v1/absences/{absence}'],
            'mobile absence cancel' => ['DELETE', 'api/v1/absences/{absence}'],
            'mobile leave balances' => ['GET', 'api/v1/me/leave-balances'],
            'mobile pay slips' => ['GET', 'api/v1/me/pay-slips'],
            'mobile pay slip detail' => ['GET', 'api/v1/me/pay-slips/{paySlip}'],
            'mobile pay slip pdf' => ['GET', 'api/v1/me/pay-slips/{paySlip}/pdf'],
            'mobile notifications' => ['GET', 'api/v1/notifications'],
            'mobile notification read' => ['PUT', 'api/v1/notifications/{notification}/read'],
            'mobile notifications read all' => ['PUT', 'api/v1/notifications/read-all'],
            'mobile device token register' => ['POST', 'api/v1/device-tokens'],
            'mobile device token unregister' => ['DELETE', 'api/v1/device-tokens'],
            'admin dashboard summary' => ['GET', 'api/v1/dashboard/summary'],
            'admin dashboard recent activity' => ['GET', 'api/v1/dashboard/recent-activity'],
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
