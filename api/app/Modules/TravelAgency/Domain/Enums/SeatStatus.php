<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut d'un siège de l'inventaire d'un trajet (TRAVEL-208, issue #6021).
 *
 * Mission Leopardo — Travel OS : le modèle d'origine ne connaissait que trois
 * états (`free`/`reserved`/`sold`). Le plan de sièges doit distinguer plus
 * finement l'état métier réel d'une place (disponible, réservation
 * temporairement en cours, réservé, payé/vendu, bloqué par l'opérateur,
 * indisponible pour raison documentée) — sans casser le stock existant.
 *
 * Les trois valeurs CANONIQUES (`free`, `reserved`, `sold`) restent la source
 * de vérité persistée en base et ne changent jamais de sens. `blocked` et
 * `unavailable` sont des états persistables supplémentaires (CHECK élargi par
 * migration). `selected` et `pending_payment` sont des états de PRÉSENTATION
 * (sélection locale côté client, paiement en attente dérivé du statut de
 * paiement) : ils ne sont jamais écrits dans `travel_trip_seats.status`.
 */
enum SeatStatus: string
{
    /** Disponible à la vente. */
    case FREE = 'free';

    /** Sélectionné par l'utilisateur — état de vue, non persisté. */
    case SELECTED = 'selected';

    /** Réservation temporairement en cours (grâce period, `reserved_until`). */
    case HELD = 'held';

    /** Réservé (en attente de paiement / confirmation) — persisté. */
    case RESERVED = 'reserved';

    /** Paiement en attente — état de vue dérivé du statut de paiement. */
    case PENDING_PAYMENT = 'pending_payment';

    /** Réservé et confirmé : vendu, plus jamais libéré par l'expiration. */
    case SOLD = 'sold';

    /** Bloqué par l'opérateur (raison opérationnelle) — persisté. */
    case BLOCKED = 'blocked';

    /** Indisponible pour une raison documentée — persisté. */
    case UNAVAILABLE = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Libre',
            self::SELECTED => 'Sélectionné',
            self::HELD => 'Réservation en cours',
            self::RESERVED => 'Réservé',
            self::PENDING_PAYMENT => 'Paiement en attente',
            self::SOLD => 'Vendu',
            self::BLOCKED => 'Bloqué',
            self::UNAVAILABLE => 'Indisponible',
        };
    }

    /**
     * Un siège est-il commercialisable dans cet état ?
     *
     * Seul `free` est sélectionnable. Tous les autres états (y compris les
     * états de présentation) rendent la place indisponible — la garde finale
     * reste de toute façon le verrou serveur (`SELECT … FOR UPDATE`).
     */
    public function isSelectable(): bool
    {
        return $this === self::FREE;
    }

    /**
     * Cet état est-il persistable dans `travel_trip_seats.status` ?
     *
     * Les états de présentation (`selected`, `held`, `pending_payment`) ne le
     * sont pas : ils sont projetés par l'UI à partir du stock et du statut de
     * paiement. La migration d'alignement n'élargit donc le CHECK qu'aux
     * valeurs réellement écrites.
     */
    public function isPersistable(): bool
    {
        return match ($this) {
            self::FREE, self::RESERVED, self::SOLD, self::BLOCKED, self::UNAVAILABLE => true,
            self::SELECTED, self::HELD, self::PENDING_PAYMENT => false,
        };
    }

    /**
     * États persistables (valeurs du CHECK `travel_trip_seats_status_check`).
     *
     * @return list<string>
     */
    public static function persistableValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isPersistable()),
        ));
    }
}
