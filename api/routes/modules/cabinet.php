<?php

/**
 * Routes Cabinet (Placard) — espace personnel de rangement de documents.
 *
 * Tous les utilisateurs authentifies (employee, manager, etc.) disposent
 * d'un placard personnel pour stocker, organiser et partager leurs documents.
 */

use App\Http\Controllers\Api\V1\CabinetDocumentController;
use App\Http\Controllers\Api\V1\CabinetFolderController;
use App\Http\Controllers\Api\V1\CabinetShareController;
use Illuminate\Support\Facades\Route;

// Public: access shared resources via token (no auth required)
Route::get('/cabinet/shared/{token}', [CabinetShareController::class, 'accessByToken'])
    ->middleware('throttle:60,1');

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant'])->prefix('cabinet')->group(function (): void {

    // ── Stats ────────────────────────────────────────────────────────────────
    Route::get('/stats', [CabinetShareController::class, 'stats']);

    // ── Folders ──────────────────────────────────────────────────────────────
    Route::get('/folders', [CabinetFolderController::class, 'index']);
    Route::post('/folders', [CabinetFolderController::class, 'store']);
    Route::get('/folders/{cabinetFolder}', [CabinetFolderController::class, 'show'])->whereNumber('cabinetFolder');
    Route::put('/folders/{cabinetFolder}', [CabinetFolderController::class, 'update'])->whereNumber('cabinetFolder');
    Route::delete('/folders/{cabinetFolder}', [CabinetFolderController::class, 'destroy'])->whereNumber('cabinetFolder');

    // ── Documents ────────────────────────────────────────────────────────────
    Route::get('/documents', [CabinetDocumentController::class, 'index']);
    Route::post('/documents', [CabinetDocumentController::class, 'store']);
    Route::get('/documents/{cabinetDocument}', [CabinetDocumentController::class, 'show'])->whereNumber('cabinetDocument');
    Route::put('/documents/{cabinetDocument}', [CabinetDocumentController::class, 'update'])->whereNumber('cabinetDocument');
    Route::delete('/documents/{cabinetDocument}', [CabinetDocumentController::class, 'destroy'])->whereNumber('cabinetDocument');
    Route::get('/documents/{cabinetDocument}/download', [CabinetDocumentController::class, 'download'])->whereNumber('cabinetDocument');
    Route::patch('/documents/{cabinetDocument}/move', [CabinetDocumentController::class, 'move'])->whereNumber('cabinetDocument');

    // ── Shares ───────────────────────────────────────────────────────────────
    Route::get('/shares', [CabinetShareController::class, 'index']);
    Route::post('/shares', [CabinetShareController::class, 'store']);
    Route::delete('/shares/{cabinetShare}', [CabinetShareController::class, 'destroy'])->whereNumber('cabinetShare');
});
