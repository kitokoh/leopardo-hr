<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Actions;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideCancelled;
use App\Modules\Vtc\Domain\Exceptions\InvalidRideTransitionException;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Models\VtcRideEvent;
use App\Modules\Vtc\Domain\Support\VtcRideStateMachine;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/**
 * Annulation d'une course VTC (BC-35 VTC, VTC-03/#8359 — étendue #8423
 * M-21 aux acteurs ops : chauffeur, dispatcher, admin).
 *
 * Règle métier (spec §5.2), déclinée PAR ACTEUR :
 *  - passager : annulation AVANT acceptation uniquement
 *    (requested/dispatching) — après, la course appartient au cycle
 *    chauffeur/dispatcher ;
 *  - chauffeur : après acceptation (accepted/arrived — panne, empêchement)
 *    sur SA course uniquement (périmètre porté par le contrôleur) ;
 *  - dispatcher/admin : tout état non terminal (arbitrage ops console).
 *
 * Le motif et l'acteur sont toujours tracés (colonne + journal append-only
 * `cancelled_by` + événement de domaine ; la cascade d'offres s'arrête
 * via les gardes de statut du dispatch, qui n'offre qu'en `dispatching`).
 * Un chauffeur encore `busy` sur la course est LIBÉRÉ (→ available),
 * sinon il resterait coincé sur une course morte — mais un chauffeur
 * suspendu ou hors ligne n'est JAMAIS « ressuscité » dans le pool de
 * dispatch : la libération est strictement conditionnée au statut `busy`.
 */
final class CancelRideAction
{
    public const ACTOR_PASSENGER = 'passenger';

    public const ACTOR_DRIVER = 'driver';

    public const ACTOR_DISPATCHER = 'dispatcher';

    public const ACTOR_ADMIN = 'admin';

    /**
     * États sources autorisés PAR ACTEUR (spec §5.2 + #8423 M-21). Les
     * cases d'enum ne sont pas des expressions constantes en PHP 8.1 —
     * valeurs string, miroir explicite de VtcRideStateMachine (qui reste
     * le filet de sécurité : `cancelled` n'est atteignable que depuis un
     * état non terminal, jamais depuis in_progress).
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_FROM_BY_ACTOR = [
        self::ACTOR_PASSENGER => ['requested', 'dispatching'],
        self::ACTOR_DRIVER => ['accepted', 'arrived'],
        self::ACTOR_DISPATCHER => ['requested', 'dispatching', 'accepted', 'arrived'],
        self::ACTOR_ADMIN => ['requested', 'dispatching', 'accepted', 'arrived'],
    ];

    public function __construct(
        private readonly VtcRideStateMachine $stateMachine,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @throws InvalidRideTransitionException
     * @throws InvalidArgumentException acteur inconnu (défaut interne —
     *                                  jamais une entrée client : les contrôleurs passent les
     *                                  constantes ACTOR_*)
     */
    public function execute(VtcRide $ride, string $reason, string $cancelledBy = self::ACTOR_PASSENGER): VtcRide
    {
        $allowedFrom = self::ALLOWED_FROM_BY_ACTOR[$cancelledBy]
            ?? throw new InvalidArgumentException("Acteur d'annulation VTC inconnu : {$cancelledBy}.");

        $locked = $this->db->transaction(function () use ($ride, $reason, $cancelledBy, $allowedFrom): VtcRide {
            /** @var VtcRide $locked — ligne VERROUILLÉE : la règle par
             * acteur et la state machine sont vérifiées sur l'état frais,
             * jamais sur le modèle potentiellement périmé du contrôleur
             * (une acceptation chauffeur concurrente ne doit pas être
             * écrasée par une annulation). */
            $locked = VtcRide::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $from = $locked->status;

            // Règle par acteur (spec §5.2) : refus uniforme 409 avant le
            // filet de la state machine.
            if (! in_array($from->value, $allowedFrom, true)) {
                throw new InvalidRideTransitionException($from, VtcRideStatus::Cancelled);
            }

            $this->stateMachine->assertCanTransitionTo($from, VtcRideStatus::Cancelled);

            $locked->forceFill([
                'status' => VtcRideStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            // Chauffeur assigné sur la course annulée : libéré UNIQUEMENT
            // s'il est encore `busy` — jamais de chauffeur `busy` orphelin
            // d'une course morte, et jamais de « resurrection » d'un
            // chauffeur suspendu/hors ligne dans le pool de dispatch.
            if ($locked->driver_id !== null) {
                VtcDriver::query()
                    ->whereKey($locked->driver_id)
                    ->where('status', VtcDriverStatus::Busy->value)
                    ->update(['status' => VtcDriverStatus::Available->value]);
            }

            VtcRideEvent::create([
                'ride_id' => $locked->id,
                'type' => VtcRideEventType::RideCancelled->value,
                'payload' => [
                    'reason' => $reason,
                    'cancelled_by' => $cancelledBy,
                ],
            ]);

            return $locked;
        });

        VtcRideCancelled::dispatch(
            (string) $locked->company_id,
            $locked->id,
            $locked->reference,
            $reason,
            $cancelledBy,
        );

        return $locked->refresh();
    }
}
