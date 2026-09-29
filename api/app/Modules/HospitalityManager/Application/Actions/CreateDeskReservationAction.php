<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;

/**
 * Création d'une réservation au guichet (BOS-024d, #8215) — extraite de
 * HospitalityReservationController::store. Comportement conservé à
 * l'identique : anti-overbooking transactionnel (409
 * HOSPITALITY_NO_AVAILABILITY), idempotence sur `idempotency_key` (le
 * rejeu retourne l'existant — le controller rend alors 200, pas 201, via
 * `wasRecentlyCreated`).
 */
final class CreateDeskReservationAction
{
    public function __construct(private readonly HospitalityReservationService $reservations) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (StoreHospitalityReservationRequest)
     */
    public function execute(string $companyId, array $validated): HospitalityReservation
    {
        return $this->reservations->createDeskReservation($companyId, $validated);
    }
}
