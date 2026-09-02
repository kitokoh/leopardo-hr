<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelMeterOcrRequest;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\MeterOcrService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\ReviewMeterOcrRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SubmitMeterOcrRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * OCR des compteurs FuelStation (AI-002, #6771).
 *
 * Toutes les routes sont tenant-scoped ; la solution doit être ACTIVE sur le
 * tenant (feature flag `fuel_station`), sinon 403 (fail-closed).
 *
 *  - submit : tout employé authentifié du tenant (multipart, photo) → 202
 *    (traitement asynchrone, la réponse ne contient AUCUN chemin de photo) ;
 *  - show : consultation du statut/résultat d'une demande du tenant ;
 *  - review : manager (middleware api.manager) — accepte (enregistre le
 *    relevé) ou rejette une demande `needs_review`.
 *
 * L'OCR ne clôture jamais seule une session de pompe/caisse : le service
 * n'appelle que MeterReadingService::record().
 */
class FuelMeterOcrController extends Controller
{
    public function __construct(
        private readonly MeterOcrService $service,
    ) {}

    public function submit(
        SubmitMeterOcrRequest $request,
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
    ): JsonResponse {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        // Fail-closed cross-tenant (convention #5445) : toute référence hors
        // tenant est introuvable → 404 AVANT tout traitement (ne pas révéler
        // l'existence d'équipements d'un autre tenant).
        $station = $this->stationInTenant($station, $actor);
        $pump = $this->pumpInStation($pump, $station);
        $meter = $this->meterInTenant($meter, $station, $pump);

        /** @var \Illuminate\Http\UploadedFile $photo */
        $photo = $request->file('photo');
        $shiftId = $request->input('shift_id') !== null ? (int) $request->input('shift_id') : null;

        $ocr = $this->service->submit(
            $station,
            $pump,
            $meter,
            $photo,
            $actor,
            $shiftId,
            (string) $request->input('idempotency_key'),
        );

        // 202 : demande acceptée pour traitement asynchrone. Données neutres
        // (jamais le chemin de la photo) + lien de suivi.
        return response()->json([
            'data' => [
                'id' => (int) $ocr->getAttribute('id'),
                'status' => $ocr->getAttribute('status'),
                'correlation_id' => $ocr->getAttribute('correlation_id'),
                'links' => [
                    'self' => url('/api/v1/fuel-station/meter-ocr-requests/'.(int) $ocr->getAttribute('id')),
                ],
            ],
        ], 202);
    }

    public function show(Request $request, FuelMeterOcrRequest $ocr): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $ocr = $this->ocrInTenant($ocr, $actor);

        return response()->json(['data' => $this->present($ocr)]);
    }

    public function review(ReviewMeterOcrRequest $request, FuelMeterOcrRequest $ocr): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $ocr = $this->ocrInTenant($ocr, $actor);

        $reviewed = $this->service->review(
            $ocr,
            $actor,
            (bool) $request->input('accepted', false),
            $request->input('reading_value_minor') !== null ? (int) $request->input('reading_value_minor') : null,
            $request->input('reason') !== null ? (string) $request->input('reason') : null,
            $request->input('reading_unit') !== null ? (string) $request->input('reading_unit') : null,
        );

        return response()->json(['data' => $this->present($reviewed)]);
    }

    private function stationInTenant(FuelStation $station, Employee $actor): FuelStation
    {
        /** @var FuelStation|null $scoped */
        $scoped = FuelStation::query()
            ->where('company_id', (string) $actor->getAttribute('company_id'))
            ->find((int) $station->getAttribute('id'));

        if ($scoped === null) {
            abort(404, 'Station introuvable dans le tenant.');
        }

        return $scoped;
    }

    private function pumpInStation(FuelPump $pump, FuelStation $station): FuelPump
    {
        /** @var FuelPump|null $scoped */
        $scoped = FuelPump::query()
            ->where('station_id', (int) $station->getAttribute('id'))
            ->find((int) $pump->getAttribute('id'));

        if ($scoped === null) {
            abort(404, 'Pompe introuvable dans le tenant.');
        }

        return $scoped;
    }

    private function meterInTenant(FuelMeterRegister $meter, FuelStation $station, FuelPump $pump): FuelMeterRegister
    {
        /** @var FuelMeterRegister|null $scoped */
        $scoped = FuelMeterRegister::query()
            ->where('company_id', (string) $station->getAttribute('company_id'))
            ->where('pump_id', (int) $pump->getAttribute('id'))
            ->find((int) $meter->getAttribute('id'));

        if ($scoped === null) {
            abort(404, 'Compteur introuvable dans le tenant.');
        }

        return $scoped;
    }

    /**
     * Résolution tenant fail-closed d'une demande OCR : la route porte un
     * model binding, mais on re-vérifie explicitement le tenant (pattern
     * resolveManagerKiosk) — cross-tenant → 404.
     */
    private function ocrInTenant(FuelMeterOcrRequest $ocr, Employee $actor): FuelMeterOcrRequest
    {
        /** @var FuelMeterOcrRequest|null $scoped */
        $scoped = FuelMeterOcrRequest::query()
            ->where('company_id', (string) $actor->getAttribute('company_id'))
            ->find((int) $ocr->getAttribute('id'));

        if ($scoped === null) {
            abort(404, 'Demande OCR introuvable dans le tenant.');
        }

        return $scoped;
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /**
     * Présentation neutre d'une demande OCR (aucun chemin de fichier exposé).
     *
     * @return array<string, mixed>
     */
    private function present(FuelMeterOcrRequest $ocr): array
    {
        $reviewDecision = $ocr->getAttribute('review_decision');
        $reviewedAt = $ocr->getAttribute('reviewed_at');
        $createdAt = $ocr->getAttribute('created_at');
        $updatedAt = $ocr->getAttribute('updated_at');

        return [
            'id' => (int) $ocr->getAttribute('id'),
            'station_id' => (int) $ocr->getAttribute('station_id'),
            'pump_id' => (int) $ocr->getAttribute('pump_id'),
            'meter_id' => (int) $ocr->getAttribute('meter_id'),
            'shift_id' => $ocr->getAttribute('shift_id'),
            'status' => $ocr->getAttribute('status'),
            'correlation_id' => $ocr->getAttribute('correlation_id'),
            'extracted_value_minor' => $ocr->getAttribute('extracted_value_minor'),
            'extracted_unit' => $ocr->getAttribute('extracted_unit'),
            'confidence' => $ocr->getAttribute('confidence'),
            'anomalies' => $ocr->getAttribute('anomalies') ?? [],
            'model_version' => $ocr->getAttribute('model_version'),
            'attempts' => (int) $ocr->getAttribute('attempts'),
            'error_code' => $ocr->getAttribute('error_code'),
            'reading_id' => $ocr->getAttribute('reading_id'),
            'review' => $reviewDecision !== null ? [
                'decision' => $reviewDecision,
                'reviewed_by_employee_id' => $ocr->getAttribute('reviewed_by_employee_id'),
                'reviewed_at' => $reviewedAt instanceof Carbon
                    ? $reviewedAt->toIso8601String()
                    : null,
            ] : null,
            'created_at' => $createdAt instanceof Carbon
                ? $createdAt->toIso8601String()
                : null,
            'updated_at' => $updatedAt instanceof Carbon
                ? $updatedAt->toIso8601String()
                : null,
        ];
    }
}
