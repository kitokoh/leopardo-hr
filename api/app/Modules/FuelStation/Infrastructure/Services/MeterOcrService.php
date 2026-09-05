<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\AI\Domain\Contracts\ModelInferencePort;
use App\Core\AI\Domain\Enums\ModelExecutionStatus;
use App\Core\AI\Domain\Enums\ModelType;
use App\Core\AI\Domain\ValueObjects\ModelRequest;
use App\Core\AI\Domain\ValueObjects\ModelResult;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\FuelStation\Domain\Exceptions\FuelOcrNotReviewableException;
use App\Modules\FuelStation\Domain\Exceptions\FuelOcrReviewValueRejectedException;
use App\Modules\FuelStation\Domain\Exceptions\FuelReadingRejectedException;
use App\Modules\FuelStation\Domain\Models\FuelMeterOcrRequest;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Jobs\ProcessMeterOcrJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * OCR des compteurs FuelStation via queue durable (AI-002, #6771).
 *
 * Flux : `submit()` (employé, requête HTTP) persist la photo + une ligne
 * `fuel_meter_ocr_requests` (statut queued) AVANT le dispatch du job —
 * une perte de queue ne perd jamais la demande. `process()` (job) exécute
 * l'inférence, puis :
 *  - confiance ≥ seuil (`ai.meter_ocr.confidence_threshold`) ET aucune
 *    anomalie → auto-enregistrement du relevé via MeterReadingService ;
 *  - sinon → `needs_review` (revue humaine par un manager), AUCUN relevé
 *    créé.
 *
 * Garanties :
 *  - l'OCR ne clôture JAMAIS seule une session de pompe/caisse (aucun appel
 *    vers les services de session — MeterReadingService::record() uniquement) ;
 *  - aucune valeur décroissante auto-enregistrée (anomalie
 *    DECREASING_READING, revue obligatoire — même en cas de rollover
 *    documenté, une lecture OCR douteuse reste vérifiée par un humain) ;
 *  - idempotence : le rejeu d'une même clé d'idempotence renvoie la demande
 *    existante, photo non re-stockée, aucun doublon ;
 *  - fournisseur indisponible/timeout → statut failed + RE-THROW
 *    RuntimeException pour que la queue réessaie avec backoff (après
 *    épuisement : failed_jobs = dead-letter, la ligne garde son état) ;
 *  - la photo est CONSERVÉE (revue humaine) ; politique de purge hors
 *    périmètre AI-002.
 */
final class MeterOcrService
{
    /** Borne haute d'un relevé en unités mineures (parité StoreMeterReadingRequest). */
    private const MAX_READING_VALUE_MINOR = 99999999999999;

    public function __construct(
        private readonly ModelInferencePort $inference,
        private readonly MeterReadingService $readings,
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * Soumet une photo de compteur pour traitement OCR asynchrone.
     *
     * La ligne est créée (statut queued) puis le job est dispatché : un
     * crash entre les deux ne perd pas la demande. Un rejeu réseau (même
     * idempotency_key) renvoie la demande existante.
     */
    public function submit(
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
        UploadedFile $photo,
        Employee $actor,
        ?int $shiftId,
        string $idempotencyKey,
    ): FuelMeterOcrRequest {
        $companyId = (string) $station->getAttribute('company_id');

        // Rejeu idempotent : même clé → même demande (aucun doublon, la photo
        // d'origine reste la référence).
        /** @var FuelMeterOcrRequest|null $existing */
        $existing = FuelMeterOcrRequest::query()
            ->where('company_id', $companyId)
            ->where('correlation_id', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $photoPath = $this->storePhoto($companyId, $photo);

        try {
            /** @var FuelMeterOcrRequest $row */
            $row = FuelMeterOcrRequest::query()->create([
                'company_id' => $companyId,
                'station_id' => (int) $station->getAttribute('id'),
                'pump_id' => (int) $pump->getAttribute('id'),
                'meter_id' => (int) $meter->getAttribute('id'),
                'requested_by_employee_id' => (int) $actor->getAttribute('id'),
                'shift_id' => $shiftId,
                'photo_path' => $photoPath,
                'status' => FuelMeterOcrRequest::STATUS_QUEUED,
                'correlation_id' => $idempotencyKey,
                'attempts' => 0,
            ]);
        } catch (Throwable $e) {
            // Pas de photo orpheline si la création échoue (ex. violation de
            // la contrainte unique correlation_id inter-tenant).
            Storage::disk('local')->delete($photoPath);
            throw $e;
        }

        // Ligne persistée AVANT le dispatch : une perte de queue ne perd pas
        // la demande (elle reste visible et rejouable).
        ProcessMeterOcrJob::dispatch((int) $row->getAttribute('id'), $companyId);

        return $row;
    }

    /**
     * Traite une demande OCR (appelé par ProcessMeterOcrJob).
     *
     * Établit le contexte tenant de la demande (search_path + current_company
     * via TenantManager) : le job ne dépend d'aucun contexte d'authentification.
     *
     * @throws RuntimeException  fournisseur indisponible/timeout/erreur de
     *                           transport — la queue réessaie avec backoff
     */
    public function process(FuelMeterOcrRequest $request): FuelMeterOcrRequest
    {
        $company = Company::query()->find((string) $request->getAttribute('company_id'));

        if (! $company instanceof Company) {
            $this->markFailed($request, 'COMPANY_NOT_FOUND');

            return $request->refresh();
        }

        return $this->tenantManager->withinTenant(
            $company,
            fn (): FuelMeterOcrRequest => $this->processWithinTenant($request),
        );
    }

    /**
     * Revue humaine d'une demande en attente (statut needs_review).
     *
     * accept → le relevé est enregistré via MeterReadingService (valeur
     * corrigée si fournie, sinon valeur extraite) et la demande passe
     * `succeeded` ; reject → la demande passe `rejected` (motif tracé dans
     * error_code, borné à la taille de la colonne).
     *
     * @param  string|null  $correctedUnit  unité corrigée par le manager
     *                                      (sinon unité extraite si elle
     *                                      correspond au compteur, sinon
     *                                      unité du compteur)
     * @throws FuelOcrNotReviewableException      statut ≠ needs_review
     * @throws FuelOcrReviewValueRejectedException valeur refusée par MeterReadingService
     */
    public function review(
        FuelMeterOcrRequest $request,
        Employee $actor,
        bool $accepted,
        ?int $correctedValueMinor,
        ?string $reason,
        ?string $correctedUnit = null,
    ): FuelMeterOcrRequest {
        if (! $request->isNeedsReview()) {
            throw new FuelOcrNotReviewableException;
        }

        if ($accepted) {
            return $this->reviewAccept($request, $actor, $correctedValueMinor, $correctedUnit);
        }

        $request->forceFill([
            'status' => FuelMeterOcrRequest::STATUS_REJECTED,
            'review_decision' => FuelMeterOcrRequest::REVIEW_DECISION_REJECTED,
            'reviewed_by_employee_id' => (int) $actor->getAttribute('id'),
            'reviewed_at' => Carbon::now(),
            // Motif de rejet tracé (colonne string(60)) — code stable si absent.
            'error_code' => $reason !== null && $reason !== ''
                ? mb_substr($reason, 0, 60)
                : 'REJECTED_BY_MANAGER',
        ])->save();

        return $request->refresh();
    }

    private function processWithinTenant(FuelMeterOcrRequest $request): FuelMeterOcrRequest
    {
        // Rechargement tenant-scoped : le traitement ne porte JAMAIS sur une
        // ligne d'un autre tenant, même si le modèle a été résolu hors contexte.
        /** @var FuelMeterOcrRequest|null $row */
        $row = FuelMeterOcrRequest::query()
            ->where('company_id', (string) $request->getAttribute('company_id'))
            ->find((int) $request->getAttribute('id'));

        if ($row === null) {
            throw new RuntimeException('ocr.request_not_found');
        }

        // Garde de rejeu : seules les demandes queued|failed sont traitables
        // (succeeded/needs_review/rejected ne sont jamais re-traitées).
        if (! $row->canBeProcessed()) {
            Log::warning('[MeterOcrService] traitement OCR ignoré (statut non rejouable)', [
                'request_id' => (int) $row->getAttribute('id'),
                'status' => $row->getAttribute('status'),
            ]);

            return $row;
        }

        $row->forceFill([
            'status' => FuelMeterOcrRequest::STATUS_PROCESSING,
            'attempts' => (int) $row->getAttribute('attempts') + 1,
        ])->save();

        // Contexte métier : station/pompe/compteur + demandeur, tous résolus
        // dans le tenant de la demande.
        $station = FuelStation::query()->find((int) $row->getAttribute('station_id'));
        $pump = FuelPump::query()->find((int) $row->getAttribute('pump_id'));
        $meter = FuelMeterRegister::query()->find((int) $row->getAttribute('meter_id'));

        if (! $station instanceof FuelStation) {
            return $this->markFailed($row, 'STATION_NOT_FOUND')->refresh();
        }

        if (! $pump instanceof FuelPump || (int) $pump->getAttribute('station_id') !== (int) $station->getAttribute('id')) {
            return $this->markFailed($row, 'PUMP_NOT_FOUND')->refresh();
        }

        if (! $meter instanceof FuelMeterRegister || (int) $meter->getAttribute('pump_id') !== (int) $pump->getAttribute('id')) {
            return $this->markFailed($row, 'METER_NOT_FOUND')->refresh();
        }

        /** @var Employee|null $requester */
        $requester = Employee::query()->find((int) $row->getAttribute('requested_by_employee_id'));

        if (! $requester instanceof Employee) {
            return $this->markFailed($row, 'REQUESTER_NOT_FOUND')->refresh();
        }

        // 1. Inférence (adaptateur scriptable/configuré). Toute exception de
        //    transport → failed + rethrow : la queue réessaie avec backoff.
        try {
            $result = $this->inference->infer(new ModelRequest(
                type: ModelType::OcrReading,
                correlationId: (string) $row->getAttribute('correlation_id'),
                input: [
                    'photo_path' => (string) $row->getAttribute('photo_path'),
                    'expected_unit' => (string) $meter->getAttribute('unit_code'),
                ],
            ));
        } catch (Throwable $e) {
            $this->markFailed($row, 'INTERNAL');

            throw new RuntimeException('ocr.provider_unavailable', 0, $e);
        }

        if (! $result->isUsable()) {
            return $this->handleUnusableResult($row, $result);
        }

        return $this->handleUsableResult($row, $station, $pump, $meter, $requester, $result);
    }

    /**
     * Résultat d'inférence non exploitable → statut failed.
     *
     * Unavailable/Timeout sont TRANSITOIRES (retry queue) ; InvalidInput et
     * Rejected sont définitifs (aucun rethrow).
     *
     * @throws RuntimeException  uniquement pour Unavailable/Timeout
     */
    private function handleUnusableResult(FuelMeterOcrRequest $row, ModelResult $result): FuelMeterOcrRequest
    {
        $status = $result->status;
        $errorCode = $this->stableErrorCode($status);

        $this->markFailed($row, $errorCode);

        if ($status === ModelExecutionStatus::Unavailable || $status === ModelExecutionStatus::Timeout) {
            // Panne fournisseur / timeout : rejeté par la queue avec backoff
            // ($tries=3, $backoff=[10,60]) — après épuisement, le job part en
            // failed_jobs (dead-letter) et la ligne reste en `failed`.
            throw new RuntimeException('ocr.provider_unavailable');
        }

        return $row->refresh();
    }

    /**
     * Résultat exploitable : conversion stricte de la valeur, détection des
     * anomalies, puis auto-enregistrement OU revue humaine.
     */
    private function handleUsableResult(
        FuelMeterOcrRequest $row,
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
        Employee $requester,
        ModelResult $result,
    ): FuelMeterOcrRequest {
        /** @var array<string, mixed> $payload */
        $payload = $result->payload;

        $valueMinor = $this->toMinorUnits($payload['value'] ?? null, (int) $meter->getAttribute('precision_scale'));

        if ($valueMinor === null) {
            // Valeur absente, non numérique, non finie, non positive, trop
            // précise pour l'échelle du compteur ou hors bornes → définitif.
            return $this->markFailed($row, 'INVALID_OCR_VALUE')->refresh();
        }

        $unit = strtolower(trim((string) ($payload['unit'] ?? '')));
        $confidence = $result->confidence;
        $payloadConfidence = $payload['confidence'] ?? null;

        if (is_numeric($payloadConfidence)) {
            $confidence = (float) $payloadConfidence;
        }

        $threshold = (float) config('ai.meter_ocr.confidence_threshold', 0.92);

        $anomalies = [];

        if ($confidence < $threshold) {
            $anomalies[] = FuelMeterOcrRequest::ANOMALY_LOW_CONFIDENCE;
        }

        $expectedUnit = strtolower(trim((string) $meter->getAttribute('unit_code')));

        if ($unit === '' || $unit !== $expectedUnit) {
            $anomalies[] = FuelMeterOcrRequest::ANOMALY_UNIT_MISMATCH;
        }

        if ($this->isDecreasingValue($row, $meter, $valueMinor)) {
            $anomalies[] = FuelMeterOcrRequest::ANOMALY_DECREASING_READING;
        }

        $modelVersion = (string) $result->modelVersion;

        if ($anomalies !== []) {
            return $this->storeNeedsReview($row, $valueMinor, $unit, $confidence, $anomalies, $modelVersion);
        }

        // 2. Auto-enregistrement — uniquement haute confiance SANS anomalie.
        try {
            $recorded = $this->readings->record(
                $station,
                $pump,
                $meter,
                [
                    'reading_value_minor' => $valueMinor,
                    'reading_unit' => $unit,
                    'captured_at' => Carbon::now('UTC')->toIso8601String(),
                    'timezone' => (string) ($station->getAttribute('timezone') ?? 'UTC'),
                    'shift_id' => $row->getAttribute('shift_id') !== null ? (int) $row->getAttribute('shift_id') : null,
                    'device_reference' => 'ocr:'.(int) $row->getAttribute('id'),
                    'idempotency_key' => 'ocr-'.(string) $row->getAttribute('correlation_id'),
                ],
                $requester,
            );
        } catch (FuelReadingRejectedException) {
            // Défensif (valeur négative/décroissante détectée entre le
            // pré-contrôle et l'écriture) : jamais d'auto-enregistrement.
            return $this->storeNeedsReview(
                $row,
                $valueMinor,
                $unit,
                $confidence,
                [FuelMeterOcrRequest::ANOMALY_DECREASING_READING],
                $modelVersion,
            );
        } catch (Throwable $e) {
            $this->markFailed($row, 'INTERNAL');

            throw new RuntimeException('ocr.record_failed', 0, $e);
        }

        $readingPayload = $recorded['reading'] ?? null;

        if (! is_array($readingPayload) || ! isset($readingPayload['id'])) {
            $this->markFailed($row, 'INTERNAL');

            throw new RuntimeException('ocr.record_response_invalid');
        }

        $row->forceFill([
            'status' => FuelMeterOcrRequest::STATUS_SUCCEEDED,
            'extracted_value_minor' => $valueMinor,
            'extracted_unit' => $unit,
            'confidence' => $confidence,
            'anomalies' => [],
            'model_version' => $modelVersion,
            'error_code' => null,
            'reading_id' => (int) $readingPayload['id'],
        ])->save();

        return $row->refresh();
    }

    private function reviewAccept(
        FuelMeterOcrRequest $request,
        Employee $actor,
        ?int $correctedValueMinor,
        ?string $correctedUnit,
    ): FuelMeterOcrRequest {
        $companyId = (string) $request->getAttribute('company_id');

        $station = FuelStation::query()->where('company_id', $companyId)->find((int) $request->getAttribute('station_id'));
        $pump = FuelPump::query()->where('company_id', $companyId)->find((int) $request->getAttribute('pump_id'));
        $meter = FuelMeterRegister::query()->where('company_id', $companyId)->find((int) $request->getAttribute('meter_id'));

        if (! $station instanceof FuelStation || ! $pump instanceof FuelPump || ! $meter instanceof FuelMeterRegister) {
            // Références perdues entre la soumission et la revue : état
            // terminal traçable (aucun relevé créé).
            return $this->markFailed($request, 'REFERENCE_LOST')->refresh();
        }

        $valueMinor = $correctedValueMinor ?? $request->getAttribute('extracted_value_minor');

        if ($valueMinor === null) {
            throw new FuelOcrReviewValueRejectedException('Aucune valeur a enregistrer (extraction absente).');
        }

        // Unité d'enregistrement : unité corrigée par le manager, sinon unité
        // extraite SI elle correspond au compteur, sinon unité du compteur —
        // jamais de relevé enregistré dans une unité étrangère au compteur.
        $expectedUnit = strtolower(trim((string) $meter->getAttribute('unit_code')));
        $extractedUnit = strtolower(trim((string) ($request->getAttribute('extracted_unit') ?? $expectedUnit)));
        $unit = $correctedUnit !== null && $correctedUnit !== ''
            ? strtolower(trim($correctedUnit))
            : ($extractedUnit === $expectedUnit ? $extractedUnit : $expectedUnit);

        try {
            $recorded = $this->readings->record(
                $station,
                $pump,
                $meter,
                [
                    'reading_value_minor' => (int) $valueMinor,
                    'reading_unit' => $unit,
                    'captured_at' => Carbon::now('UTC')->toIso8601String(),
                    'timezone' => (string) ($station->getAttribute('timezone') ?? 'UTC'),
                    'shift_id' => $request->getAttribute('shift_id') !== null ? (int) $request->getAttribute('shift_id') : null,
                    'device_reference' => 'ocr-review:'.(int) $request->getAttribute('id'),
                    'idempotency_key' => 'ocr-review-'.(string) $request->getAttribute('correlation_id'),
                ],
                $actor,
            );
        } catch (FuelReadingRejectedException $e) {
            throw new FuelOcrReviewValueRejectedException($e->getMessage());
        }

        $readingPayload = $recorded['reading'] ?? null;

        if (! is_array($readingPayload) || ! isset($readingPayload['id'])) {
            $this->markFailed($request, 'INTERNAL');

            throw new RuntimeException('ocr.record_response_invalid');
        }

        $request->forceFill([
            'status' => FuelMeterOcrRequest::STATUS_SUCCEEDED,
            'reading_id' => (int) $readingPayload['id'],
            'review_decision' => FuelMeterOcrRequest::REVIEW_DECISION_ACCEPTED,
            'reviewed_by_employee_id' => (int) $actor->getAttribute('id'),
            'reviewed_at' => Carbon::now(),
            'error_code' => null,
        ])->save();

        return $request->refresh();
    }

    /**
     * Valeur décroissante par rapport au dernier relevé accepté du compteur ?
     *
     * Même logique de « relevé précédent » que MeterReadingService (même
     * compteur, horodatage strictement antérieur, hors rejets). L'OCR ne
     * s'auto-enregistre JAMAIS sur une valeur décroissante — un humain
     * vérifie la photo (rollover inclus : une erreur de lecture peut
     * ressembler à un rollover).
     */
    private function isDecreasingValue(FuelMeterOcrRequest $row, FuelMeterRegister $meter, int $valueMinor): bool
    {
        $companyId = (string) $row->getAttribute('company_id');

        /** @var FuelMeterReading|null $previous */
        $previous = FuelMeterReading::query()
            ->where('company_id', $companyId)
            ->where('meter_id', (int) $meter->getAttribute('id'))
            ->where('captured_at_utc', '<', Carbon::now('UTC'))
            ->where('status', '!=', FuelMeterReading::STATUS_REJECTED)
            ->orderByDesc('captured_at_utc')
            ->first();

        return $previous !== null && $valueMinor < (int) $previous->getAttribute('reading_value_minor');
    }

    /**
     * Persiste une demande en attente de revue humaine (jamais de relevé créé).
     *
     * @param  list<string>  $anomalies
     */
    private function storeNeedsReview(
        FuelMeterOcrRequest $row,
        int $valueMinor,
        string $unit,
        float $confidence,
        array $anomalies,
        string $modelVersion,
    ): FuelMeterOcrRequest {
        $row->forceFill([
            'status' => FuelMeterOcrRequest::STATUS_NEEDS_REVIEW,
            'extracted_value_minor' => $valueMinor,
            'extracted_unit' => $unit,
            'confidence' => $confidence,
            'anomalies' => $anomalies,
            'model_version' => $modelVersion,
            'error_code' => null,
        ])->save();

        return $row->refresh();
    }

    private function markFailed(FuelMeterOcrRequest $row, string $errorCode): FuelMeterOcrRequest
    {
        $row->forceFill([
            'status' => FuelMeterOcrRequest::STATUS_FAILED,
            'error_code' => $errorCode,
        ])->save();

        return $row;
    }

    /**
     * Code machine STABLE persisté en base (jamais le code brut d'un
     * fournisseur) : la colonne error_code reste interprétable en ops.
     */
    private function stableErrorCode(ModelExecutionStatus $status): string
    {
        return match ($status) {
            ModelExecutionStatus::Unavailable => 'PROVIDER_UNAVAILABLE',
            ModelExecutionStatus::Timeout => 'PROVIDER_TIMEOUT',
            ModelExecutionStatus::Rejected => 'MODEL_REJECTED',
            ModelExecutionStatus::InvalidInput => 'INVALID_INPUT',
            ModelExecutionStatus::Succeeded => 'INTERNAL',
        };
    }

    /**
     * Stocke la photo dans un dossier tenant-scoped (pattern BIO-004).
     *
     * La photo est CONSERVÉE après traitement : elle sert à la revue humaine
     * (`needs_review`). Politique de purge / rétention hors périmètre AI-002.
     *
     * @return string chemin relatif stocké (ex. ocr/{company_id}/{uuid}.jpg)
     */
    private function storePhoto(string $companyId, UploadedFile $photo): string
    {
        $extension = (string) ($photo->getClientOriginalExtension() !== ''
            ? $photo->getClientOriginalExtension()
            : 'jpg');

        $directory = 'ocr/'.$companyId;
        $filename = (string) Str::uuid().'.'.$extension;

        $stored = $photo->storeAs($directory, $filename, 'local');

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException('ocr.photo_store_failed');
        }

        return $stored;
    }

    /**
     * Convertit strictement une valeur de compteur lue par OCR en unités
     * mineures entières (échelle du compteur).
     *
     * - int : valeur entière (aucune décimale) ;
     * - string : décimale exacte (`1234.56`), jamais d'exposant ;
     * - float : repris via sa représentation JSON (round-trip le plus court),
     *   puis décimale exacte.
     *
     * Rejet (null → INVALID_OCR_VALUE) : valeur absente/non numérique/non
     * finie/non positive, plus de décimales que precision_scale, ou hors
     * bornes [1, 99999999999999] unités mineures.
     */
    private function toMinorUnits(mixed $value, int $scale): ?int
    {
        if (is_int($value)) {
            $decimal = (string) $value;
        } elseif (is_string($value)) {
            $decimal = trim($value);
        } elseif (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }

            // json_encode produit la représentation décimale la plus courte
            // qui encode fidèlement le flottant (ex. 1234.56 → "1234.56").
            $encoded = json_encode($value);
            $decimal = is_string($encoded) ? $encoded : null;

            if ($decimal === null) {
                return null;
            }
        } else {
            return null;
        }

        if (preg_match('/^([0-9]+)(?:\.([0-9]+))?$/', $decimal, $matches) !== 1) {
            return null;
        }

        $intPart = ltrim($matches[1], '0');
        $intPart = $intPart === '' ? '0' : $intPart;

        $fracPart = rtrim($matches[2] ?? '', '0');

        // Borne : 14 chiffres max dans la partie entière (parité
        // StoreMeterReadingRequest) — évite tout débordement d'entier PHP.
        if (strlen($intPart) > 14) {
            return null;
        }

        if (strlen($fracPart) > $scale) {
            // Trop de décimales pour la précision du compteur : refuser
            // plutôt que d'arrondir silencieusement un relevé.
            return null;
        }

        $factor = 10 ** $scale;

        $integer = (int) $intPart;

        if ($integer > intdiv(PHP_INT_MAX, $factor)) {
            return null;
        }

        $valueMinor = $integer * $factor + (int) str_pad($fracPart, $scale, '0');

        if ($valueMinor < 1 || $valueMinor > self::MAX_READING_VALUE_MINOR) {
            return null;
        }

        return $valueMinor;
    }
}
