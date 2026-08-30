<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\PaymentProvider;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-401 (#6188) — Clôture d'une session de caisse POS.
 *
 * Totaux TOUJOURS recalculés côté serveur (aucun montant accepté du client) :
 *   expected_cash_minor = opening_cash_minor + Σ(paiements confirmés de la session)
 * où chaque paiement vaut `amount_minor + tip_minor`. L'écart est
 * `counted_cash_minor − expected_cash_minor` ; tout écart non nul exige un
 * motif. Clôture immuable : verrou optimiste `version` (deux clôtures
 * concurrentes → 409) et statut `closed` définitif.
 *
 * RESTO-412 (#6199) — la clôture publie l'événement `restaurant.pos.closed.v1`
 * dans l'outbox (payload redigé : totaux, écart, période, encaissements par
 * provider) ; rejouable sans doublon via la clé d'idempotence
 * `pos-closed-{sessionId}` (consommateurs : Accounting/reporting, spec §6.3).
 */
final class ClosePosSessionAction
{
    public const EVENT_POS_CLOSED = 'restaurant.pos.closed.v1';

    public function __construct(private readonly RestaurantOutboxPublisher $outbox)
    {
    }

    /**
     * @param  array{counted_cash_minor: int, variance_reason?: string|null}  $data
     */
    public function close(Employee $actor, RestaurantPosSession $session, array $data): RestaurantPosSession
    {
        if ($session->company_id !== $actor->company_id) {
            throw new RuntimeException('Session does not belong to tenant.');
        }

        if ($session->status !== PosSessionStatus::OPEN) {
            abort(409, 'POS session is not open.');
        }

        $expected = $this->expectedCashMinor($session);

        $counted = $data['counted_cash_minor'];
        $variance = $counted - $expected;
        $reason = $data['variance_reason'] ?? null;

        if ($variance !== 0 && $reason === null) {
            abort(422, 'A variance reason is required when counted cash differs from expected cash.');
        }

        $affected = DB::table('restaurant_pos_sessions')
            ->where('id', $session->id)
            ->where('company_id', $session->company_id)
            ->where('version', $session->version)
            ->update([
                'status' => PosSessionStatus::CLOSED->value,
                'closed_at' => now(),
                'closed_by_user_id' => $actor->id,
                'expected_cash_minor' => $expected,
                'counted_cash_minor' => $counted,
                'variance_minor' => $variance,
                'variance_reason' => $reason,
                'version' => $session->version + 1,
            ]);

        if ($affected !== 1) {
            abort(409, 'POS session was modified concurrently; reload and retry.');
        }

        $session->refresh();

        // RESTO-412 : événement de clôture après commit de la transaction
        // (payload redigé, idempotent par session — rejeu sans doublon).
        $this->outbox->publish(
            $session->company_id,
            self::EVENT_POS_CLOSED,
            $this->redactedPayload($session),
            idempotencyKey: sprintf('pos-closed-%d', $session->id),
        );

        return $session;
    }

    /**
     * Payload redigé de l'événement de clôture (spec §6.3) : totaux recalculés
     * serveur, écart, période et encaissements confirmés par provider —
     * aucune PII (pas de nom de serveur, pas de détail de commandes).
     *
     * @return array<string, mixed>
     */
    private function redactedPayload(RestaurantPosSession $session): array
    {
        return [
            'pos_session_id' => $session->id,
            'branch_id' => $session->branch_id,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_cash_minor' => $session->opening_cash_minor,
            'expected_cash_minor' => $session->expected_cash_minor,
            'counted_cash_minor' => $session->counted_cash_minor,
            'variance_minor' => $session->variance_minor,
            'payments_confirmed_minor' => $this->confirmedByProvider($session),
        ];
    }

    /**
     * Montants confirmés par provider sur la session (minor units).
     *
     * @return array<string, int>
     */
    private function confirmedByProvider(RestaurantPosSession $session): array
    {
        $rows = RestaurantOrderPayment::query()
            ->where('company_id', $session->company_id)
            ->where('pos_session_id', $session->id)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->get(['provider_code', 'amount_minor', 'tip_minor']);

        $totals = [];
        foreach ($rows as $row) {
            $provider = $row->provider_code instanceof PaymentProvider ? $row->provider_code->value : (string) $row->provider_code;
            $totals[$provider] = ($totals[$provider] ?? 0) + (int) $row->amount_minor + (int) ($row->tip_minor ?? 0);
        }

        return $totals;
    }

    /**
     * Total attendu en caisse : fonds d'ouverture + encaissements confirmés
     * (montant + pourboire) rattachés à la session — calcul serveur pur.
     */
    private function expectedCashMinor(RestaurantPosSession $session): int
    {
        $payments = RestaurantOrderPayment::query()
            ->where('company_id', $session->company_id)
            ->where('pos_session_id', $session->id)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->get(['amount_minor', 'tip_minor']);

        $sum = (int) $session->opening_cash_minor;
        foreach ($payments as $payment) {
            $sum += (int) $payment->amount_minor + (int) ($payment->tip_minor ?? 0);
        }

        return $sum;
    }
}
