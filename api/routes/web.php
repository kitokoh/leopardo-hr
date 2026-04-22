<?php

use App\Http\Controllers\Web\BiometricAdminController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\InvitationManagementController;
use App\Http\Controllers\Web\KioskController;
use App\Http\Controllers\Web\MyDashboardController;
use App\Http\Controllers\Web\PlatformAuthController;
use App\Http\Controllers\Web\PlatformCompanyController;
use App\Http\Controllers\Web\WebAuthController;
use App\Http\Controllers\Web\WebEmployeeController;
use App\Http\Controllers\Web\WebEmployeeManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest:super_admin_web')->group(function (): void {
    Route::get('/platform/login', [PlatformAuthController::class, 'showLogin'])->name('platform.login');
    Route::post('/platform/login', [PlatformAuthController::class, 'login'])->name('platform.login.store');
});

Route::post('/platform/logout', [PlatformAuthController::class, 'logout'])
    ->middleware('auth:super_admin_web')
    ->name('platform.logout');

Route::middleware('auth:super_admin_web')->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/companies', [PlatformCompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [PlatformCompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [PlatformCompanyController::class, 'store'])->name('companies.store');
});

Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.store');
});

Route::get('/activate/{token}', [InvitationController::class, 'showActivationForm'])->name('invitation.activate.show');
Route::post('/activate/{token}', [InvitationController::class, 'activate'])->name('invitation.activate.store');
Route::get('/kiosk/{deviceCode}', [KioskController::class, 'show'])->name('kiosk.show');
Route::post('/kiosk/{deviceCode}/punch', [KioskController::class, 'punch'])->name('kiosk.punch');

Route::post('/logout', [WebAuthController::class, 'logout'])
    ->middleware('auth:web')
    ->name('logout');

// Espace personnel accessible a tout employe authentifie (manager ou simple employe).
Route::middleware(['auth:web', 'tenant', 'employee'])->prefix('me')->name('me.')->group(function (): void {
    Route::get('/', [MyDashboardController::class, 'index'])->name('dashboard');
});

// Dashboard manager (principal + sous-roles RH / dept / comptable / superviseur).
Route::middleware(['auth:web', 'tenant', 'manager'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/employees/{employee}', [WebEmployeeController::class, 'show'])
        ->where('employee', '[0-9]+')
        ->name('employees.show');
    Route::get('/employees/{employee}/quick-estimate', [WebEmployeeController::class, 'quickEstimate'])
        ->where('employee', '[0-9]+')
        ->name('employees.quickEstimate');
    Route::get('/employees/{employee}/receipt', [WebEmployeeController::class, 'receipt'])
        ->where('employee', '[0-9]+')
        ->name('employees.receipt');
});

// Creation / gestion des employes : reservee aux managers Principal et RH.
Route::middleware(['auth:web', 'tenant', 'manager_role:principal,rh'])->group(function (): void {
    Route::get('/employees/create', [WebEmployeeManagementController::class, 'create'])->name('employees.create');
    Route::post('/employees', [WebEmployeeManagementController::class, 'store'])->name('employees.store');

    Route::prefix('hr')->name('hr.')->group(function (): void {
        Route::get('/invitations', [InvitationManagementController::class, 'index'])->name('invitations.index');
        Route::post('/invitations/{invitation}/resend', [InvitationManagementController::class, 'resend'])->name('invitations.resend');
    });
});

// Biometrie / bornes : Principal et Superviseur.
Route::middleware(['auth:web', 'tenant', 'manager_role:principal,superviseur'])->group(function (): void {
    Route::get('/biometrics', [BiometricAdminController::class, 'index'])->name('biometrics.index');
    Route::post('/biometrics/requests/{id}/approve', [BiometricAdminController::class, 'approve'])->name('biometrics.requests.approve');
    Route::post('/biometrics/requests/{id}/reject', [BiometricAdminController::class, 'reject'])->name('biometrics.requests.reject');
    Route::post('/biometrics/kiosks', [BiometricAdminController::class, 'createKiosk'])->name('biometrics.kiosks.store');
});
