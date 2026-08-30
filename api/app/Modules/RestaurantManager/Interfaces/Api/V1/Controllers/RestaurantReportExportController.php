<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RESTO-702 (#6215) — Export CSV idempotent + URL signée éphémère.
 *
 * `POST /restaurant/reports/export` (auth tenant) génère/retrouve le CSV et
 * renvoie une URL signée ; `GET /restaurant/reports/export/{export}` est une
 * route publique signée (middleware `signed`, TTL 10 min) qui stream le
 * fichier — hors groupe auth, la signature EST l'authentification.
 */
class RestaurantReportExportController extends Controller
{
    public function __construct(
        private readonly RestaurantReportExportService $exports,
    ) {
    }

    public function export(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('restaurant.reports')) {
            abort(403);
        }

        $request->validate([
            'report_type' => ['required', 'string', 'in:sales,products,cogs,pos'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $from = $request->input('from') !== null
            ? Carbon::parse((string) $request->input('from'))->startOfDay()
            : Carbon::today();
        $to = $request->input('to') !== null
            ? Carbon::parse((string) $request->input('to'))->endOfDay()
            : Carbon::today()->endOfDay();

        try {
            $result = $this->exports->export(
                $actor,
                (string) $request->input('report_type'),
                $from,
                $to,
                $request->input('branch_id') !== null ? (int) $request->input('branch_id') : null,
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Route signée (middleware `signed`) — la signature valide l'accès.
     */
    public function download(Request $request, string $export): StreamedResponse|JsonResponse
    {
        return $this->exports->download($export);
    }
}
