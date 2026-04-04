<?php

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => '4.1.4',
        ]);
    });

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('/auth/me', function (Request $request) {
            return response()->json([
                'data' => $request->user(),
            ]);
        });

        Route::get('/employees', function () {
            return response()->json([
                'data' => Employee::query()
                    ->select(['id', 'company_id', 'first_name', 'last_name', 'email', 'status'])
                    ->orderBy('id')
                    ->get(),
            ]);
        });
    });
});
