<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Services\CogsCalculator;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-401 (#6188) / RESTO-412 (#6199) — Clôture d'une session de caisse POS.
 *
 * Totaux TOUJOURS recalculés côté serveur (aucun montant accepté du client) :
 *   expected_cash_minor = opening_cash_minor + Σ(paiements confirmés de la session)
 * où chaque paiement vaut `amount_minor + tip_minor`. L'écart est
 * `counted_cash_minor − expected_cash_minor` ; tout écart non nul exige un
 * motif. Clôture immuable : verrou optimiste `version` (deux clôtures
 * concurrentes → 409) et statut `closed` définitif. Événement outbox
 * `restaurant.pos.closed.v1` publié après commit (totaux, écart, période —
 * consommateurs Accounting/reporting, RESTO-412).
 */
final class ClosePosSessionAction
{
    public const EVENT_POS_CLOSED = 'restaurant.pos.closed.v1';

    public function __construct(
        private readonly RestaurantOutboxPublisher $outbox,
        private readonly CogsCalculator $cogs,
    ) {}

    /**
     * @param  array{counted_cash_minor: int, variance_reason?: string|null}  $data
     */
    public function execute(Employee $actor, RestaurantPosSession $session, array $data): RestaurantPosSession
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

        // RESTO-412 (#6199) — événement de clôture publié APRÈS commit
        // (outbox idempotente : clé dérivée du payload → rejeu sans doublon).
        // RESTO-506 (#6205) — COGS de la session inclus (calcul serveur pur).
        $this->outbox->publish(
            $session->company_id,
            self::EVENT_POS_CLOSED,
            [
                'pos_session_id' => $session->id,
                'branch_id' => $session->branch_id,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'opening_cash_minor' => (int) $session->opening_cash_minor,
                'expected_cash_minor' => $expected,
                'counted_cash_minor' => $counted,
                'variance_minor' => $variance,
                'variance_reason' => $reason,
                // RESTO-412 (#6199) — ventilation des encaissements confirmés
                // par moyen de paiement. Le consommateur Accounting doit
                // rapprocher la caisse par canal (espèces vs carte vs mobile
                // money) : un total unique ne le permet pas, et un écart de
                // caisse ne peut pas être imputé à un moyen plutôt qu'un autre.
                // Les clés sont les `provider_code` réellement présents sur la
                // session — aucune clé vide n'est inventée.
                'payments_confirmed_minor' => $this->confirmedPaymentsByProvider($session),
                'cogs_minor' => $this->cogs->calculateForSession($session),
            ],
        );

        return $session;
    }

    /**
     * Encaissements confirmés de la session, ventilés par moyen de paiement.
     *
     * @return array<string, int> `provider_code` => somme (montant + pourboire)
     */
    private function confirmedPaymentsByProvider(RestaurantPosSession $session): array
    {
        $payments = RestaurantOrderPayment::query()
            ->where('company_id', $session->company_id)
            ->where('pos_session_id', $session->id)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->get(['provider_code', 'amount_minor', 'tip_minor']);

        $byProvider = [];
        foreach ($payments as $payment) {
            // `provider_code` est casté en PaymentProvider (BackedEnum) par le
            // modèle : le test `instanceof BackedEnum` serait toujours vrai et
            // PHPStan le rejette. On lit directement la valeur scalaire.
            $code = (string) $payment->provider_code->value;
            $byProvider[$code] = ($byProvider[$code] ?? 0)
                + (int) $payment->amount_minor
                + (int) ($payment->tip_minor ?? 0);
        }

        return $byProvider;
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
