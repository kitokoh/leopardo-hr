<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
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
 */
final class ClosePosSessionAction
{
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

        return $session;
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
