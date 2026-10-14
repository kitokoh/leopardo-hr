<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Models\TradeEvent;

/**
 * Ecriture d'un evenement de la timeline d'un dossier Trade — TRADE-005/#8480.
 *
 * UN SEUL POINT D'ECRITURE pour la timeline : chaque action metier passe par
 * ici, ce qui garantit un format homogene (`subject_type` + `type` + `payload`)
 * et permet d'appeler l'enregistrement DANS la transaction de la transition.
 * L'historique ne peut donc pas mentir par desynchronisation : si la
 * transition est annulee, son evenement disparait avec elle.
 *
 * `employee_id` null = evenement SYSTEME. La distinction humain/systeme reste
 * ainsi toujours lisible dans la timeline.
 */
final class RecordTradeEventAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        string $subjectType,
        int $subjectId,
        string $type,
        array $payload = [],
        ?int $employeeId = null,
    ): TradeEvent {
        /** @var TradeEvent $event */
        $event = TradeEvent::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'type' => $type,
            'payload' => $payload === [] ? null : $payload,
            'employee_id' => $employeeId,
        ]);

        return $event;
    }
}
