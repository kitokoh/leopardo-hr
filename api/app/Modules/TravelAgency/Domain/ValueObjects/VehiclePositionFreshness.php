<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\ValueObjects;

use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use Illuminate\Support\Carbon;

/**
 * #8548 (TRAVEL-OS) — fraîcheur OPPOSABLE d'une dernière position connue.
 *
 * Aucun fournisseur de télémétrie n'est intégré : la donnée ne peut provenir
 * que d'un dispositif autorisé (boîtier GPS ou application). Ce value object
 * répond à une seule question — « cette position peut-elle être présentée
 * comme courante ? » — et refuse par construction les trois confusions
 * interdites par la mission :
 *
 *  - une position ABSENTE n'est jamais « à l'heure » : elle est `unavailable` ;
 *  - une position plus vieille que le seuil est `stale`, jamais « courante » ;
 *  - une position `estimated` n'est jamais une mesure GPS (`is_measured`).
 *
 * Le seuil est explicite (config `travel.fleet_position.stale_after_minutes`)
 * et jamais deviné : une position sans horodatage est traitée comme
 * `unavailable` (fail-closed), pas comme fraîche.
 */
final class VehiclePositionFreshness
{
    /** Aucune position connue — la donnée est indisponible, pas « à l'heure ». */
    public const UNAVAILABLE = 'unavailable';

    /** Position connue et plus récente que le seuil. */
    public const FRESH = 'fresh';

    /** Position connue mais plus vieille que le seuil — jamais « courante ». */
    public const STALE = 'stale';

    private function __construct(
        public readonly string $state,
        public readonly ?int $ageSeconds,
        public readonly ?int $staleAfterSeconds,
    ) {}

    /**
     * Position absente (aucun dispositif n'a jamais transmis) ou sans
     * horodatage exploitable : `unavailable`, âge inconnu.
     */
    public static function unavailable(): self
    {
        return new self(self::UNAVAILABLE, null, null);
    }

    /**
     * Évalue la fraîcheur d'une position horodatée.
     *
     * @param  Carbon  $recordedAt  horodatage de la mesure (jamais deviné)
     * @param  int  $staleAfterMinutes  seuil explicite, en minutes
     */
    public static function fromRecordedAt(Carbon $recordedAt, int $staleAfterMinutes): self
    {
        $staleAfterSeconds = max(1, $staleAfterMinutes) * 60;
        $ageSeconds = max(0, (int) $recordedAt->diffInSeconds(now()));

        return new self(
            $ageSeconds > $staleAfterSeconds ? self::STALE : self::FRESH,
            $ageSeconds,
            $staleAfterSeconds,
        );
    }

    /**
     * Construit la fraîcheur depuis les colonnes nullable d'un véhicule.
     *
     * `null` (aucune coordonnée OU aucun horodatage) ⇒ `unavailable` : on ne
     * suppose jamais qu'une position sans date est récente.
     */
    public static function fromNullable(?Carbon $recordedAt, int $staleAfterMinutes): self
    {
        if ($recordedAt === null) {
            return self::unavailable();
        }

        return self::fromRecordedAt($recordedAt, $staleAfterMinutes);
    }

    /** La position peut-elle être présentée comme courante ? */
    public function isCurrent(): bool
    {
        return $this->state === self::FRESH;
    }

    /** La donnée est-elle absente (à afficher « indisponible ») ? */
    public function isUnavailable(): bool
    {
        return $this->state === self::UNAVAILABLE;
    }

    /** La donnée est-elle trop ancienne pour être présentée comme courante ? */
    public function isStale(): bool
    {
        return $this->state === self::STALE;
    }

    /**
     * Libellé destiné au tableau de bord — jamais « à l'heure » sans donnée.
     */
    public function label(): string
    {
        return match ($this->state) {
            self::FRESH => 'Position récente',
            self::STALE => 'Position ancienne',
            default => 'Position indisponible',
        };
    }

    /**
     * Une position `estimated` ne peut jamais être présentée comme une mesure
     * GPS, quelle que soit sa fraîcheur.
     */
    public static function isMeasuredSource(?TravelVehiclePositionSource $source): bool
    {
        return $source?->isMeasured() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'label' => $this->label(),
            'is_current' => $this->isCurrent(),
            'is_stale' => $this->isStale(),
            'is_unavailable' => $this->isUnavailable(),
            'age_seconds' => $this->ageSeconds,
            'stale_after_seconds' => $this->staleAfterSeconds,
        ];
    }
}
