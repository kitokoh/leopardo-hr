<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\AI\Predictions\AbsenteeismPredictor;
use App\AI\Predictions\ProactiveNotificationService;
use App\AI\Predictions\TurnoverPredictor;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function turnover(Request $request, TurnoverPredictor $predictor): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $months = $request->integer('months', 6);

        return response()->json([
            'data' => $predictor->predict($actor->company_id, $months),
        ]);
    }

    public function absenteeism(Request $request, AbsenteeismPredictor $predictor): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $horizon = $request->integer('horizon', 3);

        return response()->json([
            'data' => $predictor->predict($actor->company_id, $horizon),
        ]);
    }

    public function proactiveNotifications(Request $request, ProactiveNotificationService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        return response()->json([
            'data' => $service->getNotifications($actor->company_id),
        ]);
    }
}
