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
            'mobile attendance correction request' => ['POST', 'api/v1/attendance/corrections'],
            'mobile manager attendance correction queue' => ['GET', 'api/v1/attendance/corrections'],
            'mobile manager attendance correction approve' => ['PUT', 'api/v1/attendance/corrections/{correction}/approve'],
            'mobile manager attendance correction reject' => ['PUT', 'api/v1/attendance/corrections/{correction}/reject'],
            'mobile attendance correction update' => ['PUT', 'api/v1/attendance/{attendanceLog}'],
            'mobile attendance today' => ['GET', 'api/v1/attendance/today'],
            'mobile manager attendance anomalies' => ['GET', 'api/v1/attendance/anomalies'],
            'mobile attendance history' => ['GET', 'api/v1/attendance'],
            'mobile monthly summary' => ['GET', 'api/v1/me/monthly-summary'],
            'mobile employees list' => ['GET', 'api/v1/employees'],
            'mobile employee create' => ['POST', 'api/v1/employees'],
            'mobile employee detail' => ['GET', 'api/v1/employees/{employee}'],
            'mobile employee update' => ['PUT', 'api/v1/employees/{employee}'],
            'mobile employee archive' => ['POST', 'api/v1/employees/{employee}/archive'],
            'mobile manager schedules list' => ['GET', 'api/v1/schedules'],
            'mobile manager schedule create' => ['POST', 'api/v1/schedules'],
            'mobile manager schedule update' => ['PUT', 'api/v1/schedules/{schedule}'],
            'mobile manager schedule delete' => ['DELETE', 'api/v1/schedules/{schedule}'],
            'mobile tasks today' => ['GET', 'api/v1/tasks/today'],
            'mobile task create' => ['POST', 'api/v1/tasks'],
            'mobile task update' => ['PATCH', 'api/v1/tasks/{task}'],
            'mobile employee qr profile' => ['GET', 'api/v1/me/qr-profile'],
            'mobile company qr onboarding' => ['GET', 'api/v1/company/qr-onboarding'],
            'mobile company scan employee qr' => ['POST', 'api/v1/company/qr-onboarding/scan-employee'],
            'mobile company create employee from qr' => ['POST', 'api/v1/company/qr-onboarding/create-employee'],
            'mobile employee scan company qr' => ['POST', 'api/v1/me/company-qr/scan'],
            'mobile invitations list' => ['GET', 'api/v1/invitations'],
            'mobile invitation resend' => ['POST', 'api/v1/invitations/{invitation}/resend'],
            'mobile absences list' => ['GET', 'api/v1/absences'],
            'mobile absence create' => ['POST', 'api/v1/absences'],
            'mobile absence detail' => ['GET', 'api/v1/absences/{absence}'],
            'mobile absence cancel' => ['DELETE', 'api/v1/absences/{absence}'],
            'mobile leave balances' => ['GET', 'api/v1/me/leave-balances'],
            'mobile salary advances list' => ['GET', 'api/v1/salary-advances'],
            'mobile salary advance create' => ['POST', 'api/v1/salary-advances'],
            'mobile salary advance detail' => ['GET', 'api/v1/salary-advances/{salaryAdvance}'],
            'mobile salary advance approve' => ['PUT', 'api/v1/salary-advances/{salaryAdvance}/approve'],
            'mobile salary advance manager approve' => ['PUT', 'api/v1/salary-advances/{salaryAdvance}/manager-approve'],
            'mobile salary advance mark paid' => ['PUT', 'api/v1/salary-advances/{salaryAdvance}/mark-paid'],
            'mobile salary advance confirm received' => ['PUT', 'api/v1/salary-advances/{salaryAdvance}/confirm-received'],
            'mobile salary advance reject' => ['PUT', 'api/v1/salary-advances/{salaryAdvance}/reject'],
            'mobile salary advance cancel' => ['DELETE', 'api/v1/salary-advances/{salaryAdvance}'],
            'mobile approvals pending' => ['GET', 'api/v1/approvals/pending'],
            'mobile approval approve' => ['POST', 'api/v1/approvals/{approvalRequest}/approve'],
            'mobile approval reject' => ['POST', 'api/v1/approvals/{approvalRequest}/reject'],
            'mobile approvals history' => ['GET', 'api/v1/approvals/history'],
            'mobile pay slips' => ['GET', 'api/v1/me/pay-slips'],
            'mobile pay slip detail' => ['GET', 'api/v1/me/pay-slips/{paySlip}'],
            'mobile pay slip pdf' => ['GET', 'api/v1/me/pay-slips/{paySlip}/pdf'],
            'mobile employee balance' => ['GET', 'api/v1/me/balance'],
            'mobile manager payroll summary' => ['GET', 'api/v1/payroll/mobile-summary'],
            'mobile notifications' => ['GET', 'api/v1/notifications'],
            'mobile notification read' => ['PUT', 'api/v1/notifications/{notification}/read'],
            'mobile notifications read all' => ['PUT', 'api/v1/notifications/read-all'],
            'mobile notification delete' => ['DELETE', 'api/v1/notifications/{notification}'],
            'mobile notification preferences read' => ['GET', 'api/v1/notification-preferences'],
            'mobile notification preferences update' => ['PATCH', 'api/v1/notification-preferences'],
            'mobile device token register' => ['POST', 'api/v1/device-tokens'],
            'mobile device token unregister' => ['DELETE', 'api/v1/device-tokens'],
            'admin dashboard summary' => ['GET', 'api/v1/dashboard/summary'],
            'admin dashboard recent activity' => ['GET', 'api/v1/dashboard/recent-activity'],
            'manager mobile dashboard digest' => ['GET', 'api/v1/dashboard/manager-digest'],
            'admin launch readiness' => ['GET', 'api/v1/launch-readiness'],
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
            'platform mobile login' => ['POST', 'api/v1/platform/auth/login'],
            'platform mobile me' => ['GET', 'api/v1/platform/auth/me'],
            'platform mobile logout' => ['POST', 'api/v1/platform/auth/logout'],
            'platform mobile plans' => ['GET', 'api/v1/platform/plans'],
            'platform mobile companies list' => ['GET', 'api/v1/platform/companies'],
            'platform mobile company create' => ['POST', 'api/v1/platform/companies'],
            'platform mobile companies health' => ['GET', 'api/v1/platform/companies/health'],
            'platform mobile company health' => ['GET', 'api/v1/platform/companies/{company}/health'],
            'platform mobile subscription show' => ['GET', 'api/v1/platform/companies/{company}/subscription'],
            'platform mobile subscription update' => ['PATCH', 'api/v1/platform/companies/{company}/subscription'],
            'platform mobile features show' => ['GET', 'api/v1/platform/companies/{company}/features'],
            'platform mobile features update' => ['PATCH', 'api/v1/platform/companies/{company}/features'],
            'platform mobile metrics overview' => ['GET', 'api/v1/platform/metrics/overview'],
            'platform mobile company requests list' => ['GET', 'api/v1/platform/company-requests'],
            'platform mobile company request detail' => ['GET', 'api/v1/platform/company-requests/{id}'],
            'platform mobile company request review' => ['PATCH', 'api/v1/platform/company-requests/{id}'],
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
