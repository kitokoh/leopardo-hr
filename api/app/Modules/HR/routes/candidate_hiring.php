<?php

declare(strict_types=1);

/**
 * Routes — embauche candidat (issue #5261).
 *
 * Chargées par HRServiceProvider (fichier dédié pour ne pas toucher
 * rh.php / hr_extended.php, verrouillés par les PRs en vol).
 */

use App\Modules\HR\Interfaces\Api\V1\Controllers\CandidateHiringController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'token.refresh'])
    ->prefix('api/v1/hr')
    ->group(function (): void {
        // Embauche d'un candidat (principal ou RH)
        Route::middleware('api.manager:principal,rh')
            ->post('/candidates/{applicant}/hire', [CandidateHiringController::class, 'store'])
            ->whereNumber('applicant');
    });
