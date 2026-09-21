<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Infrastructure\Services;

use App\Modules\HospitalityManager\Domain\Exceptions\HospitalityCheckoutBeforeCheckinException;
use App\Modules\HospitalityManager\Domain\Exceptions\HospitalityInvalidTransitionException;
use App\Modules\HospitalityManager\Domain\Exceptions\HospitalityNoAvailabilityException;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Règles métier des réservations — HOSP-004 (#7946, BC-32, spec §3/§5).
 *
 * Anti-overbooking transactionnel (spec §3) : la création/édition verrouille
 * le TYPE de chambre (`lockForUpdate` — sérialise les écritures concurrentes
 * sur ce type) puis COMPTE les réservations qui immobilisent l'inventaire
 * sur l'intervalle [check_in, check_out) chevauchant la demande ; refus
 * (409 HOSPITALITY_NO_AVAILABILITY) si le compte atteint la capacité
 * opérationnelle (unités du type hors maintenance / hors service).
 *
 * Une réservation `pending` en ligne EXPIRÉE n'immobilise plus
 * (comptage explicite `heldCount()`) — filet avant le passage de la commande
 * d'expiration.
 */
final class HospitalityReservationService
{
    /**
     * Disponibilités par type de chambre d'un établissement sur [from, to).
     *
     * @return list<array<string, mixed>>
     */
    public function availability(string $companyId, int $propertyId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $roomTypes = HospitalityRoomType::query()
            ->where('company_id', $companyId)
            ->where('property_id', $propertyId)
            ->where('status', HospitalityRoomType::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($roomTypes as $roomType) {
            $capacity = $this->operationalCapacity($companyId, (int) $roomType->getKey());
            $held = $this->heldCount($companyId, (int) $roomType->getKey(), $from, $to);

            $result[] = [
                'room_type_id' => (int) $roomType->getKey(),
                'code' => $roomType->code,
                'name' => $roomType->name,
                'capacity' => $capacity,
                'held' => $held,
                'available' => max(0, $capacity - $held),
                'base_price_minor' => $roomType->base_price_minor,
                'currency' => $roomType->currency,
            ];
        }

        return $result;
    }

    /**
     * Création guichet (`desk`) : la réservation naît `pending` (ou
     * `confirmed` si demandé explicitement), SANS expiration automatique.
     * Idempotente sur `idempotency_key` (rejeu → l'existant, 23505 → relecture).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDeskReservation(string $companyId, array $data): HospitalityReservation
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $existing = $this->findByIdempotencyKey($companyId, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($companyId, $data): HospitalityReservation {
                $this->assertAvailabilityFor(
                    $companyId,
                    (int) $data['room_type_id'],
                    CarbonImmutable::parse($data['check_in']),
                    CarbonImmutable::parse($data['check_out'])
                );

                return HospitalityReservation::query()->create([
                    'company_id' => $companyId,
                    'reference' => $this->generateReference($companyId),
                    'property_id' => (int) $data['property_id'],
                    'room_type_id' => (int) $data['room_type_id'],
                    'unit_id' => $data['unit_id'] ?? null,
                    'guest_name' => $data['guest_name'],
                    'contact_email' => $data['contact_email'] ?? null,
                    'contact_phone' => $data['contact_phone'] ?? null,
                    'check_in' => $data['check_in'],
                    'check_out' => $data['check_out'],
                    'adults' => $data['adults'] ?? 1,
                    'children' => $data['children'] ?? 0,
                    'status' => $data['status'] ?? HospitalityReservation::STATUS_PENDING,
                    'total_amount_minor' => $data['total_amount_minor'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'source' => HospitalityReservation::SOURCE_DESK,
                    'expires_at' => null,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (Throwable $e) {
            // Course sur la clé d'idempotence (23505) : le premier arrivé a
            // gagné — rejeu idempotent = retourner son enregistrement.
            if (is_string($idempotencyKey) && $idempotencyKey !== '' && str_contains($e->getMessage(), '23505')) {
                $existing = $this->findByIdempotencyKey($companyId, $idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    /**
     * Mise à jour des champs éditables (dates, type, invités, montant,
     * notes) : interdite sur un état terminal ; tout changement d'intervalle
     * ou de type re-vérifie la disponibilité (en s'excluant du comptage).
     *
     * #8019 : la garde d'état terminal vit DÉSORMAIS dans la transaction,
     * sous verrou de ligne (`lockForUpdate`) et re-vérifiée sur la ligne
     * fraîche — la garde hors transaction était contournable par une course
     * (annulation/check-out concurrent entre la lecture et l'écriture).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateReservation(HospitalityReservation $reservation, array $data): HospitalityReservation
    {
        return DB::transaction(function () use ($reservation, $data): HospitalityReservation {
            /** @var HospitalityReservation $fresh */
            $fresh = HospitalityReservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-vérification SOUS verrou (#8019) : un autre guichet a pu
            // annuler (ou clôturer) la réservation entre la lecture et ici.
            if ($fresh->isTerminal()) {
                throw new HospitalityInvalidTransitionException($fresh->status, 'update');
            }

            $checkIn = CarbonImmutable::parse($data['check_in'] ?? $fresh->check_in);
            $checkOut = CarbonImmutable::parse($data['check_out'] ?? $fresh->check_out);
            $roomTypeId = (int) ($data['room_type_id'] ?? $fresh->room_type_id);

            if ($checkOut->lessThanOrEqualTo($checkIn)) {
                // #8019 : exception de DOMAINE (l'`abort(422, …)` du service
                // faisait fuiter la couche HTTP) — rendu HTTP identique :
                // 422 + error/message `CHECKOUT_BEFORE_CHECKIN`.
                throw new HospitalityCheckoutBeforeCheckinException;
            }

            $this->assertAvailabilityFor($fresh->company_id, $roomTypeId, $checkIn, $checkOut, (int) $fresh->getKey());

            $fresh->update(array_merge($data, [
                'version' => $fresh->version + 1,
            ]));

            return $fresh->refresh();
        });
    }

    /**
     * Transition de la machine à états (confirm / check-in / check-out /
     * cancel / no-show). 409 INVALID_RESERVATION_TRANSITION si interdite.
     * Effet de bord inventaire : check-in occupe l'unité affectée,
     * check-out la libère.
     */
    public function transition(HospitalityReservation $reservation, string $target): HospitalityReservation
    {
        if (! $reservation->canTransitionTo($target)) {
            throw new HospitalityInvalidTransitionException($reservation->status, $target);
        }

        return DB::transaction(function () use ($reservation, $target): HospitalityReservation {
            // #8019 : `firstOrFail()` remplace l'`abort(404)` du service — la
            // ligne disparue est signalée par l'absence de résultat
            // (ModelNotFoundException) que le renderer global mappe sur le
            // MÊME corps 404 `RESOURCE_NOT_FOUND` qu'avant.
            /** @var HospitalityReservation $fresh */
            $fresh = HospitalityReservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-vérification sous verrou : un autre guichet a pu transiter
            // entre la lecture et la transaction.
            if (! $fresh->canTransitionTo($target)) {
                throw new HospitalityInvalidTransitionException($fresh->status, $target);
            }

            $fresh->status = $target;
            $fresh->version = $fresh->version + 1;

            // Une confirmation ne peut jamais dépasser la capacité (ex.
            // réservation pending créée avant une vente concurrente).
            if ($target === HospitalityReservation::STATUS_CONFIRMED) {
                $this->assertAvailabilityFor(
                    $fresh->company_id,
                    (int) $fresh->room_type_id,
                    CarbonImmutable::parse($fresh->check_in),
                    CarbonImmutable::parse($fresh->check_out),
                    (int) $fresh->getKey()
                );
            }

            $fresh->save();

            if ($fresh->unit_id !== null) {
                if ($target === HospitalityReservation::STATUS_CHECKED_IN) {
                    $this->setUnitStatus($fresh, HospitalityUnit::STATUS_OCCUPIED);
                } elseif ($target === HospitalityReservation::STATUS_CHECKED_OUT) {
                    $this->setUnitStatus($fresh, HospitalityUnit::STATUS_AVAILABLE);
                }
            }

            return $fresh->refresh();
        });
    }

    /**
     * Expiration des réservations `pending` dépassées (online : +30 min) —
     * commande `hospitality:expire-pending-reservations`. Idempotent.
     *
     * @return int nombre de réservations expirées
     */
    public function expirePending(string $companyId, int $limit): int
    {
        $expired = HospitalityReservation::query()
            ->where('company_id', $companyId)
            ->where('status', HospitalityReservation::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($expired as $reservation) {
            try {
                DB::transaction(function () use ($reservation): void {
                    /** @var HospitalityReservation|null $fresh */
                    $fresh = HospitalityReservation::query()
                        ->whereKey($reservation->getKey())
                        ->lockForUpdate()
                        ->first();

                    // Re-vérification sous verrou : un autre worker a pu
                    // confirmer/annuler entre la sélection et le verrouillage.
                    if (! $fresh instanceof HospitalityReservation
                        || $fresh->status !== HospitalityReservation::STATUS_PENDING) {
                        return;
                    }

                    $fresh->status = HospitalityReservation::STATUS_CANCELLED;
                    $fresh->version = $fresh->version + 1;
                    $fresh->notes = trim(($fresh->notes ?? '').' [expirée automatiquement]');
                    $fresh->save();
                });
                $count++;
            } catch (Throwable) {
                // Une réservation en échec ne bloque pas le reste du lot ;
                // la prochaine passe la retentera (idempotence).
            }
        }

        return $count;
    }

    /**
     * Refuse (409) si le type de chambre ne peut plus honorer l'intervalle :
     * verrou du type (sérialisation) puis comptage des immobilisations
     * chevauchantes vs capacité opérationnelle. DOIT être appelé dans une
     * transaction ouverte par l'appelant.
     */
    private function assertAvailabilityFor(
        string $companyId,
        int $roomTypeId,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        ?int $excludeReservationId = null
    ): void {
        // Verrou pessimiste sur le type : sérialise les créations/éditions
        // concurrentes portant le même inventaire (spec §3).
        HospitalityRoomType::query()
            ->where('company_id', $companyId)
            ->whereKey($roomTypeId)
            ->lockForUpdate()
            ->firstOrFail();

        $capacity = $this->operationalCapacity($companyId, $roomTypeId);
        $held = $this->heldCount($companyId, $roomTypeId, $checkIn, $checkOut, $excludeReservationId);

        if ($held >= $capacity) {
            throw new HospitalityNoAvailabilityException;
        }
    }

    /**
     * Capacité opérationnelle du type : unités du type hors maintenance et
     * hors service (elles ne peuvent honorer une réservation).
     */
    private function operationalCapacity(string $companyId, int $roomTypeId): int
    {
        return HospitalityUnit::query()
            ->where('company_id', $companyId)
            ->where('room_type_id', $roomTypeId)
            ->whereNotIn('status', [
                HospitalityUnit::STATUS_MAINTENANCE,
                HospitalityUnit::STATUS_OUT_OF_SERVICE,
            ])
            ->count();
    }

    /**
     * Réservations immobilisant l'inventaire du type sur le chevauchement
     * [check_in, check_out) : pending non expirées + confirmed + checked_in.
     */
    private function heldCount(
        string $companyId,
        int $roomTypeId,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        ?int $excludeReservationId = null
    ): int {
        return HospitalityReservation::query()
            ->where('company_id', $companyId)
            ->where('room_type_id', $roomTypeId)
            ->where('check_in', '<', $checkOut->toDateString())
            ->where('check_out', '>', $checkIn->toDateString())
            ->where(function ($query): void {
                $query->whereIn('status', [
                    HospitalityReservation::STATUS_CONFIRMED,
                    HospitalityReservation::STATUS_CHECKED_IN,
                ])->orWhere(function ($pending): void {
                    $pending->where('status', HospitalityReservation::STATUS_PENDING)
                        ->where(function ($expiry): void {
                            $expiry->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
            })
            ->when($excludeReservationId !== null, fn ($query) => $query->whereKeyNot($excludeReservationId))
            ->count();
    }

    /**
     * Référence publique unique PAR TENANT : HRS-<année>-<6 alphanum> re-tirée
     * jusqu'à unicité (l'index unique reste la garde ultime).
     */
    private function generateReference(string $companyId): string
    {
        do {
            $reference = 'HRS-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (HospitalityReservation::query()
            ->where('company_id', $companyId)
            ->where('reference', $reference)
            ->exists());

        return $reference;
    }

    private function findByIdempotencyKey(string $companyId, string $key): ?HospitalityReservation
    {
        /** @var HospitalityReservation|null $existing */
        $existing = HospitalityReservation::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        return $existing;
    }

    private function setUnitStatus(HospitalityReservation $reservation, string $status): void
    {
        HospitalityUnit::query()
            ->where('company_id', $reservation->company_id)
            ->whereKey($reservation->unit_id)
            ->update(['status' => $status]);
    }
}
