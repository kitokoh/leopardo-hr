# Namespace Map — Migration Clean Architecture

Ce document liste la correspondance entre l'ancienne structure flat et la nouvelle structure modulaire.

## Auth (Core)

| Ancien namespace | Nouveau namespace |
|---|---|
| `App\Http\Controllers\Api\V1\AuthController` | `App\Core\Auth\Interfaces\Api\V1\AuthController` |
| `App\Http\Controllers\Api\V1\PlatformAuthController` | `App\Core\Auth\Interfaces\Api\V1\PlatformAuthController` |
| `App\Http\Controllers\Api\V1\UserAuthController` | `App\Core\Auth\Interfaces\Api\V1\UserAuthController` |
| `App\Services\AuthService` | `App\Core\Auth\Infrastructure\Services\AuthService` |
| `App\Services\UserAuthService` | `App\Core\Auth\Infrastructure\Services\UserAuthService` |
| `App\Models\Employee` (auth side) | `App\Core\Auth\Domain\Models\Employee` |
| `App\Models\User` | `App\Core\Auth\Domain\Models\User` |

## HR Module

| Ancien namespace | Nouveau namespace |
|---|---|
| `App\Http\Controllers\Api\V1\EmployeeController` | `App\Modules\HR\Interfaces\Api\V1\EmployeeController` |
| `App\Http\Controllers\Api\V1\DepartmentController` | `App\Modules\HR\Interfaces\Api\V1\DepartmentController` |
| `App\Http\Controllers\Api\V1\OrgChartController` | `App\Modules\HR\Interfaces\Api\V1\OrgChartController` |
| `App\Services\EmployeeService` | `App\Modules\HR\Infrastructure\Services\EmployeeService` |
| `App\Models\Employee` | `App\Modules\HR\Domain\Models\Employee` |
| `App\Models\Department` | `App\Modules\HR\Domain\Models\Department` |
| `App\Models\Position` | `App\Modules\HR\Domain\Models\Position` |
| `App\DTOs\CreateEmployeeDTO` | `App\Modules\HR\Application\DTOs\CreateEmployeeDTO` |

## Payroll Module

| Ancien namespace | Nouveau namespace |
|---|---|
| `App\Http\Controllers\Api\V1\PayrollController` | `App\Modules\Payroll\Interfaces\Api\V1\PayrollController` |
| `App\Http\Controllers\Api\V1\PaySlipController` | `App\Modules\Payroll\Interfaces\Api\V1\PaySlipController` |
| `App\Services\PayrollService` | `App\Modules\Payroll\Infrastructure\Services\PayrollService` |
| `App\Models\Payroll` | `App\Modules\Payroll\Domain\Models\Payroll` |
| `App\Models\PaySlip` | `App\Modules\Payroll\Domain\Models\PaySlip` |
| `App\Models\SalaryStructure` | `App\Modules\Payroll\Domain\Models\SalaryStructure` |
| `App\Exceptions\PayrollAlreadyValidatedException` | `App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException` |

## Attendance Module

| Ancien namespace | Nouveau namespace |
|---|---|
| `App\Http\Controllers\Api\V1\AttendanceController` | `App\Modules\Attendance\Interfaces\Api\V1\AttendanceController` |
| `App\Http\Controllers\Api\V1\KioskController` | `App\Modules\Attendance\Interfaces\Api\V1\KioskController` |
| `App\Http\Controllers\Api\V1\ZktecoController` | `App\Modules\Attendance\Interfaces\Api\V1\ZktecoController` |
| `App\Services\AttendanceService` | `App\Modules\Attendance\Infrastructure\Services\AttendanceService` |
| `App\Services\ZktecoIntegrationService` | `App\Modules\Attendance\Infrastructure\Services\ZktecoIntegrationService` |
| `App\Models\AttendanceLog` | `App\Modules\Attendance\Domain\Models\AttendanceLog` |
| `App\DTOs\CheckInDTO` | `App\Modules\Attendance\Application\DTOs\CheckInDTO` |

## Planning Module

| Ancien namespace | Nouveau namespace |
|---|---|
| `App\Http\Controllers\Api\V1\AbsenceController` | `App\Modules\Planning\Interfaces\Api\V1\AbsenceController` |
| `App\Http\Controllers\Api\V1\LeavePolicyController` | `App\Modules\Planning\Interfaces\Api\V1\LeavePolicyController` |
| `App\Services\AbsenceService` | `App\Modules\Planning\Infrastructure\Services\AbsenceService` |
| `App\Models\Absence` | `App\Modules\Planning\Domain\Models\Absence` |
| `App\Models\LeaveBalance` | `App\Modules\Planning\Domain\Models\LeaveBalance` |
| `App\Exceptions\InsufficientLeaveBalanceException` | `App\Modules\Planning\Domain\Exceptions\InsufficientLeaveBalanceException` |

## Fleet Module

| Ancien namespace | Nouveau namespace |
|---|---|
| `App\Http\Controllers\Api\V1\VehicleController` | `App\Modules\Fleet\Interfaces\Api\V1\VehicleController` |
| `App\Models\Vehicle` | `App\Modules\Fleet\Domain\Models\Vehicle` |
| `App\Models\VehicleTrip` | `App\Modules\Fleet\Domain\Models\VehicleTrip` |

## Billing Module

| Ancien namespace | Nouveau namespace |
|---|---|
| `App\Http\Controllers\Api\V1\BillingController` | `App\Modules\Billing\Interfaces\Api\V1\BillingController` |
| `App\Http\Controllers\Api\V1\StripeWebhookController` | `App\Modules\Billing\Interfaces\Api\V1\StripeWebhookController` |
| `App\Services\StripeService` | `App\Modules\Billing\Infrastructure\Services\StripeService` |
| `App\Models\Subscription` | `App\Modules\Billing\Domain\Models\Subscription` |
