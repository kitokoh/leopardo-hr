<?php

use App\Http\Controllers\Api\V1\PartnerDashboardController;
use App\Http\Controllers\Api\V1\GrowthAdminController;
use Illuminate\Support\Facades\Route;

// Espace Partenaire (Web Client)
// Note: Utilise le guard user_api car le profil partenaire est lié au User global, pas à un Employee d'un tenant.
Route::middleware(['auth:user_api'])->prefix('partner')->group(function () {
    Route::get('/stats', [PartnerDashboardController::class, 'stats']);
    Route::get('/companies', [PartnerDashboardController::class, 'referredCompanies']);
});

// Espace Administration (Super Admin)
Route::middleware(['auth:super_admin_api'])->prefix('platform/growth')->group(function () {
    Route::get('/partners', [GrowthAdminController::class, 'partners']);
    Route::patch('/partners/{partner}/rate', [GrowthAdminController::class, 'updateRate']);
    Route::get('/history', [GrowthAdminController::class, 'history']);
});
