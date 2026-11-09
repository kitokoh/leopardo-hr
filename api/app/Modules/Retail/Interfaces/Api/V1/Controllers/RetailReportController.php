<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Rapports de vente du POS Retail (BC-17 RETAIL, #9209).
 *
 * Avant ce controleur, la verticale Retail n'avait AUCUN rapport : un
 * commercant ne pouvait pas savoir ce qu'il avait vendu, ni quel produit
 * marchait, ni quel etait son ecart de caisse.
 *
 * RBAC : `retail.reports` (Gate) — direction, ou niveau `manage` sur au moins
 * une boutique assignee (un gerant voit les chiffres de SA boutique).
 * Toutes les agregations sont serveur et bornees par `company_id`.
 */
class RetailReportController extends Controller
{
    public function __construct(private readonly RetailReportService $reports) {}

    /**
     * GET /retail/reports/sales — CA, tickets, panier moyen, moyens de paiement.
     */
    public function sales(Request $request): JsonResponse|Response
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('retail.reports');

        $report = $this->reports->salesReport(
            companyId: (string) $actor->company_id,
            from: $this->date($request, 'from'),
            to: $this->date($request, 'to'),
            locationId: $request->filled('location_id') ? $request->integer('location_id') : null,
        );

        return $this->respond($request, $report, 'retail-sales');
    }

    /**
     * GET /retail/reports/top-products — quantites et CA par produit.
     */
    public function topProducts(Request $request): JsonResponse|Response
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('retail.reports');

        $rows = $this->reports->topProductsReport(
            companyId: (string) $actor->company_id,
            from: $this->date($request, 'from'),
            to: $this->date($request, 'to'),
            locationId: $request->filled('location_id') ? $request->integer('location_id') : null,
            limit: $request->integer('limit', 20),
        );

        return $this->respond($request, ['lines' => $rows], 'retail-top-products');
    }

    /**
     * GET /retail/reports/sessions — clotures de caisse (attendu, compte, ecart).
     */
    public function sessions(Request $request): JsonResponse|Response
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('retail.reports');

        $rows = $this->reports->sessionsReport(
            companyId: (string) $actor->company_id,
            from: $this->date($request, 'from'),
            to: $this->date($request, 'to'),
            locationId: $request->filled('location_id') ? $request->integer('location_id') : null,
        );

        return $this->respond($request, ['lines' => $rows], 'retail-sessions');
    }

    /**
     * GET /retail/reports/stock-valuation — valeur du stock au cout et au prix.
     */
    public function stockValuation(Request $request): JsonResponse|Response
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('retail.reports');

        $report = $this->reports->stockValuationReport(
            companyId: (string) $actor->company_id,
            locationId: $request->filled('location_id') ? $request->integer('location_id') : null,
        );

        return $this->respond($request, $report, 'retail-stock-valuation');
    }

    /**
     * Reponse JSON, ou CSV quand `?format=csv` est demande.
     *
     * @param  array<string, mixed>  $report
     */
    private function respond(Request $request, array $report, string $basename): JsonResponse|Response
    {
        if ($request->input('format') === 'csv') {
            return response($this->reports->toCsv($report), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$basename.'-'.Carbon::now()->format('Ymd').'.csv"',
            ]);
        }

        return response()->json(['data' => $report]);
    }

    private function date(Request $request, string $key): ?Carbon
    {
        if (! $request->filled($key)) {
            return null;
        }

        return Carbon::parse((string) $request->input($key));
    }
}
