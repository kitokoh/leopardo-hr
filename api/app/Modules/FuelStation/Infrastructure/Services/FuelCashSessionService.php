<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Events\FuelCashSessionClosed;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelCashSessionMovement;

/**
 * Cycle de vie des sessions de caisse FuelStation (FUEL-007, issue #5801).
 *
 * - Ouverture : nouveau session (statut open) par le pompiste.
 * - Mouvements : in/out tant que la session est ouverte (422 SESSION_NOT_OPEN).
 * - Clôture IDEMPOTENTE : une session déjà close renvoie son état inchangé
 *   (aucun recalcul, aucun double effet — rejeu sûr). expected_balance =
 *   opening + Σin − Σout ; variance = closing_balance − expected_balance ;
 *   les deux sont calculés SERVEUR, jamais fournis par le client.
 * - Approbation (manager) : verrouille l'état (status approved).
 * - Audit : chaque clôture/approbation est tracée dans `audit_logs`
 *   (catégorie fuel_cash_session) ; l'événement `FuelCashSessionClosed`
 *   est émis à la clôture (contrat Accounting, FUEL-015).
 */
final class FuelCashSessionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function open(Employee $actor, array $data): FuelCashSession
    {
        $rawOpening = $data['opening_balance'] ?? 0;
        $openingBalance = is_numeric($rawOpening) ? (float) $rawOpening : 0.0;

        $session = FuelCashSession::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $data['station_id'] ?? null,
            'opened_by' => $actor->id,
            'opening_balance' => $openingBalance,
            'status' => FuelCashSession::STATUS_OPEN,
            'notes' => $data['notes'] ?? null,
        ]);

        // opened_at est un défaut DB (useCurrent) : refresh pour le charger
        // (le modèle en mémoire ne reçoit pas les defaults PostgreSQL).
        return $session->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMovement(FuelCashSession $session, Employee $actor, array $data): FuelCashSessionMovement
    {
        abort_if($session->status !== FuelCashSession::STATUS_OPEN, 422, 'SESSION_NOT_OPEN');

        return FuelCashSessionMovement::query()->create([
            'company_id' => $actor->company_id,
            'session_id' => $session->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Clôture idempotente : rejouer la clôture d'une session déjà close
     * renvoie l'état courant sans modification (statut terminal).
     *
     * @param  array<string, mixed>  $data
     */
    public function close(FuelCashSession $session, Employee $actor, array $data): FuelCashSession
    {
        if ($session->status !== FuelCashSession::STATUS_OPEN) {
            return $session->refresh();
        }

        $movements = FuelCashSessionMovement::query()
            ->where('session_id', $session->id)
            ->get();

        $inValue = $movements->where('type', FuelCashSessionMovement::TYPE_IN)->sum('amount');
        $outValue = $movements->where('type', FuelCashSessionMovement::TYPE_OUT)->sum('amount');
        $in = is_numeric($inValue) ? (float) $inValue : 0.0;
        $out = is_numeric($outValue) ? (float) $outValue : 0.0;
        $expected = (float) $session->opening_balance + $in - $out;
        $rawClosing = $data['closing_balance'];
        $closingBalance = is_numeric($rawClosing) ? (float) $rawClosing : 0.0;

        $session->update([
            'closed_by' => $actor->id,
            'closed_at' => now(),
            'closing_balance' => $closingBalance,
            'expected_balance' => $expected,
            'variance' => $closingBalance - $expected,
            'status' => FuelCashSession::STATUS_CLOSED,
        ]);

        $session = $session->refresh();

        // Contrat Accounting (FUEL-015) : publier l'agrégat validé dans
        // l'outbox (après commit, idempotent par clé dérivée) + émettre
        // l'événement de domaine pour les listeners synchrones.
        $this->publishClosedAggregate($session, $actor);

        FuelCashSessionClosed::dispatch($session);

        return $session;
    }

    /**
     * Publie l'agrégat de clôture dans l'outbox FuelStation
     * (événement `fuel.cash.closed.v1`, contrat Accounting).
     */
    private function publishClosedAggregate(FuelCashSession $session, Employee $actor): void
    {
        $outbox = app(FuelOutboxPublisher::class);

        $aggregate = [
            'session_id' => $session->id,
            'station_id' => $session->station_id,
            'opened_by' => $session->opened_by,
            'closed_by' => $session->closed_by,
            'opened_at' => $session->opened_at->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_balance' => $session->opening_balance,
            'closing_balance' => $session->closing_balance,
            'expected_balance' => $session->expected_balance,
            'variance' => $session->variance,
            'currency' => 'DZD', // devise du tenant pilote (FUEL-021) ; alignée via Company.currency par le consommateur.
        ];

        $outbox->publish(
            companyId: (string) $actor->company_id,
            eventType: 'fuel.cash.closed.v1',
            payload: [
                'schema_version' => '1.0',
                'event' => 'cash.closed',
                'company_id' => (string) $actor->company_id,
                'idempotency_key' => 'fuel.cash.closed.v1:'.$session->id,
                'aggregate' => $aggregate,
            ],
            aggregateType: 'fuel_cash_session',
            aggregateId: (string) $session->id,
        );
    }

    /**
     * Approbation manager : verrouille l'état. Idempotente (déjà approuvée
     * → état inchangé). Refusée tant que la session n'est pas close.
     */
    public function approve(FuelCashSession $session, Employee $actor): FuelCashSession
    {
        if ($session->status === FuelCashSession::STATUS_APPROVED) {
            return $session->refresh();
        }

        abort_if($session->status !== FuelCashSession::STATUS_CLOSED, 422, 'SESSION_NOT_CLOSED');

        $session->update([
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'status' => FuelCashSession::STATUS_APPROVED,
        ]);

        return $session->refresh();
    }
}
