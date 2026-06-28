<?php

declare(strict_types=1);

use App\Modules\Notification\Interfaces\Api\V1\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1/notifications')->group(function () {
    Route::get('/',               [NotificationController::class, 'index']);
    Route::post('/mark-read',     [NotificationController::class, 'markRead']);
    Route::delete('/{notification}', [NotificationController::class, 'destroy']);
});
