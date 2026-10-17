<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Mission Leopardo — Travel OS (#8644) — disposition PHYSIQUE des sièges.
 *
 * Source de vérité unique de la géométrie d'un véhicule :
 *   - `seatsPerRow` : nombre de colonnes de sièges par rangée (1..6) ;
 *   - `aisleAfter`  : index (1-based) de la colonne APRÈS laquelle se trouve le
 *                     couloir, ou `null` quand le véhicule n'en a pas.
 *
 * Le value object refuse par construction les dispositions impossibles : un
 * couloir ne peut pas tomber après la dernière colonne, ni avant la première.
 *
 * Quand un véhicule n'est pas configuré, `fallbackForCapacity()` reproduit
 * EXACTEMENT l'heuristique historique (2+2 au-delà de 15 places, 2+1 en deçà)
 * afin qu'aucun plan existant ne change d'apparence sans configuration
 * explicite. Cette méthode est le SEUL endroit où une déduction est autorisée,
 * et elle est toujours signalée comme telle par l'API (`is_configured`).
 */
final class SeatLayout
{
    public const MIN_SEATS_PER_ROW = 1;

    public const MAX_SEATS_PER_ROW = 6;

    private function __construct(
        public readonly int $seatsPerRow,
        public readonly ?int $aisleAfter,
    ) {
        if ($seatsPerRow < self::MIN_SEATS_PER_ROW || $seatsPerRow > self::MAX_SEATS_PER_ROW) {
            throw new InvalidArgumentException(
                'seats_per_row doit etre compris entre '.self::MIN_SEATS_PER_ROW
                .' et '.self::MAX_SEATS_PER_ROW.'.'
            );
        }

        if ($aisleAfter !== null && ($aisleAfter < 1 || $aisleAfter >= $seatsPerRow)) {
            throw new InvalidArgumentException(
                'aisle_after doit etre compris entre 1 et seats_per_row - 1.'
            );
        }
    }

    /**
     * Construit une disposition depuis la valeur brute stockée en base.
     *
     * `null` (ou une forme inexploitable) → `null` : le véhicule n'est PAS
     * configuré. On ne devine jamais une géométrie à partir d'une donnée
     * corrompue — l'appelant retombe alors explicitement sur le repli.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public static function tryFromArray(?array $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $perRow = $raw['seats_per_row'] ?? null;
        if (! is_numeric($perRow)) {
            return null;
        }

        $aisle = $raw['aisle_after'] ?? null;
        if ($aisle !== null && ! is_numeric($aisle)) {
            return null;
        }

        try {
            return new self(
                (int) $perRow,
                $aisle === null ? null : (int) $aisle,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Repli historique, utilisé UNIQUEMENT quand le véhicule n'est pas
     * configuré : 2+2 au-delà de 15 places, 2+1 en deçà.
     */
    public static function fallbackForCapacity(int $totalSeats): self
    {
        $perRow = $totalSeats > 15 ? 4 : 3;

        // Les deux gabarits de repli (3 et 4 colonnes) ont un couloir ; il tombe
        // au milieu de la rangée, comme dans le rendu historique : 2 pour 3
        // colonnes comme pour 4 (équivalent de `ceil(perRow / 2)`).
        return new self($perRow, intdiv($perRow + 1, 2));
    }

    /**
     * @return array{seats_per_row: int, aisle_after: int|null}
     */
    public function toArray(): array
    {
        return [
            'seats_per_row' => $this->seatsPerRow,
            'aisle_after' => $this->aisleAfter,
        ];
    }
}
