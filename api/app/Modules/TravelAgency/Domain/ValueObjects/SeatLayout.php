<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Mission Leopardo — Travel OS (#8644, #8652) — disposition PHYSIQUE des sièges.
 *
 * Source de vérité unique de la géométrie d'un véhicule :
 *   - `seatsPerRow`  : nombre de colonnes de sièges par rangée (1..6) ;
 *   - `aisleAfter`   : index (1-based) de la colonne APRÈS laquelle se trouve le
 *                      couloir, ou `null` quand le véhicule n'en a pas ;
 *   - `absentSeats`  : numéros de sièges PHYSIQUEMENT ABSENTS du véhicule
 *                      (#8652). Une dernière rangée incomplète n'est pas
 *                      exprimable par des colonnes : un car de 30 places dont
 *                      la dernière rangée n'en a que 3 doit pouvoir le dire,
 *                      sinon l'inventaire vend un siège qui n'existe pas.
 *
 * Le value object refuse par construction les dispositions impossibles : un
 * couloir ne peut pas tomber après la dernière colonne, ni avant la première ;
 * un siège absent ne peut pas être dupliqué, ni sortir de la capacité.
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

    /**
     * @param  list<int>  $absentSeats  numéros de sièges physiquement absents, triés et uniques
     * @param  int|null  $capacity  capacité du véhicule, quand elle est connue (borne les sièges absents)
     */
    private function __construct(
        public readonly int $seatsPerRow,
        public readonly ?int $aisleAfter,
        public readonly array $absentSeats = [],
        public readonly ?int $capacity = null,
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

        $seen = [];
        foreach ($absentSeats as $seat) {
            if ($seat < 1) {
                throw new InvalidArgumentException(
                    'absent_seats doit contenir des numeros de siege entiers et positifs.'
                );
            }

            if (isset($seen[$seat])) {
                throw new InvalidArgumentException(
                    'absent_seats ne peut pas contenir deux fois le meme numero de siege.'
                );
            }

            // Un siège absent doit rester un siège PLAUSIBLE du véhicule : on ne
            // devine pas, on refuse. La borne n'est appliquée que si la capacité
            // est connue (le value object peut être construit sans elle).
            if ($capacity !== null && $seat > $capacity) {
                throw new InvalidArgumentException(
                    'absent_seats ne peut pas depasser la capacite du vehicule.'
                );
            }

            $seen[$seat] = true;
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
    public static function tryFromArray(?array $raw, ?int $capacity = null): ?self
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

        // #8652 — `absent_seats` est optionnel : son absence signifie « aucun
        // siège absent », jamais « disposition corrompue ». Une forme présente
        // mais inexploitable (non-tableau, valeur non numérique) est en revanche
        // traitée comme corrompue → repli, jamais une géométrie inventée.
        $absentRaw = $raw['absent_seats'] ?? [];
        if (! is_array($absentRaw)) {
            return null;
        }

        $absent = [];
        foreach ($absentRaw as $seat) {
            if (! is_numeric($seat)) {
                return null;
            }

            $absent[] = (int) $seat;
        }

        $absent = array_values(array_unique($absent));
        sort($absent);

        try {
            return new self(
                (int) $perRow,
                $aisle === null ? null : (int) $aisle,
                $absent,
                $capacity,
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
     * #8652 — capacité réellement COMMERCIALISABLE du véhicule.
     *
     * `seat_capacity` est le nombre de places déclarées ; les sièges
     * physiquement absents ne sont pas vendables. C'est cette valeur — et non
     * `seat_capacity` — qui doit borner l'inventaire d'un trajet.
     */
    public function sellableCapacity(int $capacity): int
    {
        return max(0, $capacity - count($this->absentSeats));
    }

    /**
     * @return array{seats_per_row: int, aisle_after: int|null, absent_seats?: list<int>}
     */
    public function toArray(): array
    {
        $payload = [
            'seats_per_row' => $this->seatsPerRow,
            'aisle_after' => $this->aisleAfter,
        ];

        // `absent_seats` n'est émis QUE lorsqu'il y en a : un véhicule sans
        // siège absent garde exactement la charge utile d'avant #8652, ce qui
        // préserve les contrats et les assertions existantes.
        if ($this->absentSeats !== []) {
            $payload['absent_seats'] = $this->absentSeats;
        }

        return $payload;
    }
}
