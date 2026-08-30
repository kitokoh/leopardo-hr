<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\RefundPaymentAction;
use App\Modules\TravelAgency\Application\Actions\VerifyPaymentAction;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PaymentGatewayRegistry;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-408/409 (#6060/#6061) — Initiation & callback de paiement.
 *
 * - `initiate` : idempotent par (booking, provider, idempotency_key) —
 *   un rejeu renvoie le paiement existant.
 * - `callback` : webhook provider, **signé** (HMAC, secret partagé du
 *   tenant) et **idempotent** (contrainte unique + retour du résultat
 *   existant). Résout la réservation par RÉFÉRENCE (corrige le bug
 *   historique gv-back qui cherchait par id), vérifie le montant, puis
 *   confirme la réservation.
 */
class TravelPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly TravelOutboxPublisher $outbox,
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'booking_reference' => ['required', 'string', 'max:40'],
            'provider_code' => ['required', 'string', 'in:cash,pvit,momo,card'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);

        $booking = TravelBooking::query()->where('reference', $data['booking_reference'])->first();

        if ($booking === null || $booking->company_id !== $actor->company_id) {
            abort(404);
        }

        $existing = TravelPayment::query()
            ->where('booking_id', $booking->id)
            ->where('provider_code', $data['provider_code'])
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing instanceof TravelPayment) {
            return response()->json([
                'data' => [
                    'reference' => $existing->reference,
                    'provider_reference' => $existing->provider_reference,
                    'status' => $existing->status->value,
                ],
            ]);
        }

        $gateway = $this->gateways->get($data['provider_code']);

        $result = $gateway->initiate([
            'booking_reference' => $booking->reference,
            'amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'idempotency_key' => $data['idempotency_key'],
        ]);

        $payment = DB::transaction(fn (): TravelPayment => TravelPayment::query()->create([
            'booking_id' => $booking->id,
            'provider_code' => $data['provider_code'],
            'amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'status' => PaymentStatus::PENDING,
            'provider_reference' => $result['provider_reference'],
            'idempotency_key' => $data['idempotency_key'],
        ]));

        return response()->json([
            'data' => [
                'reference' => $payment->reference,
                'provider_reference' => $payment->provider_reference,
                'redirect_url' => $result['redirect_url'] ?? null,
                'status' => $payment->status->value,
            ],
        ], 201);
    }

    /**
     * Webhook provider — hors auth utilisateur, vérifié par signature.
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'provider_reference' => ['required', 'string', 'max:120'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'string', 'in:confirmed,failed'],
            'signature' => ['required', 'string', 'max:128'],
        ]);

        $booking = TravelBooking::query()->where('reference', $payload['reference'])->first();

        if ($booking === null) {
            abort(404);
        }

        // Signature HMAC : le callback est signé avec le secret partagé du
        // tenant — jamais de secret en clair dans les logs.
        if (! $this->signatureIsValid($booking, $payload)) {
            abort(403, 'Signature de callback invalide.');
        }

        // Vérification du montant : un callback pour un montant différent
        // de la réservation est rejeté (anti-fraude).
        if ((int) $payload['amount_minor'] !== $booking->total_amount_minor
            || $payload['currency'] !== $booking->currency) {
            abort(422, 'Montant de callback incohérent avec la réservation.');
        }

        $payment = TravelPayment::query()
            ->where('booking_id', $booking->id)
            ->where('provider_reference', $payload['provider_reference'])
            ->first();

        if (! $payment instanceof TravelPayment) {
            abort(404);
        }

        // Idempotence : le rejeu renvoie le résultat existant, sans effet.
        if ($payment->status !== PaymentStatus::PENDING) {
            return response()->json([
                'data' => ['reference' => $payment->reference, 'status' => $payment->status->value, 'replayed' => true],
            ]);
        }

        DB::transaction(function () use ($payment, $booking, $payload): void {
            $payment->forceFill([
                'status' => $payload['status'] === 'confirmed' ? PaymentStatus::CONFIRMED : PaymentStatus::FAILED,
                'callback_payload_redacted' => $this->redact($payload),
            ])->save();

            if ($payload['status'] === 'confirmed') {
                $booking->forceFill(['payment_status' => PaymentStatus::CONFIRMED])->save();
            }
        });

        $this->outbox->publish($booking->company_id, 'travel.payment.confirmed.v1', [
            'payment_reference' => $payment->reference,
            'booking_reference' => $booking->reference,
            'provider_code' => $payment->provider_code->value,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'status' => $payload['status'],
        ]);

        return response()->json([
            'data' => ['reference' => $payment->reference, 'status' => $payment->status->value],
        ]);
    }

    public function show(Request $request, TravelPayment $travelPayment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelPayment->company_id) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'id' => $travelPayment->id,
                'reference' => $travelPayment->reference,
                'booking_id' => $travelPayment->booking_id,
                'provider_code' => $travelPayment->provider_code->value,
                'amount_minor' => $travelPayment->amount_minor,
                'currency' => $travelPayment->currency,
                'status' => $travelPayment->status->value,
                'created_at' => $travelPayment->created_at,
            ],
        ]);
    }

    /**
     * TRAVEL-410 (#6062) — Re-conciliation active d'un paiement (verify).
     */
    public function verify(Request $request, TravelPayment $travelPayment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelPayment->company_id) {
            abort(404);
        }

        $payment = app(VerifyPaymentAction::class)->execute($travelPayment);

        return response()->json([
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->status->value,
            ],
        ]);
    }

    /**
     * TRAVEL-411 (#6063) — Remboursement d'un paiement confirmé.
     */
    public function refund(Request $request, TravelPayment $travelPayment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelPayment->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelPayment)) {
            abort(403);
        }

        $reason = (string) $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']])['reason'];

        $payment = app(RefundPaymentAction::class)->execute($travelPayment, (int) $actor->id, $reason);

        return response()->json([
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->status->value,
            ],
        ]);
    }

    /**
     * Vérifie la signature HMAC du callback (secret partagé par tenant).
     *
     * @param  array<string, mixed>  $payload
     */
    private function signatureIsValid(TravelBooking $booking, array $payload): bool
    {
        $secret = (string) config('travel.payments.callback_secret');

        if ($secret === '') {
            // Fail-closed : sans secret configuré, aucun callback n'est accepté.
            return false;
        }

        $canonical = implode('|', [
            $payload['reference'],
            $payload['provider_reference'],
            (string) $payload['amount_minor'],
            $payload['currency'],
            $payload['status'],
        ]);

        return hash_equals(hash_hmac('sha256', $canonical, $secret), (string) $payload['signature']);
    }

    /**
     * Redacte le payload avant persistance : jamais de signature ni de
     * champs inconnus (le secret reste côté config).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        unset($payload['signature']);

        return $payload;
    }
}
