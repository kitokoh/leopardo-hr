<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;
use Illuminate\Support\Facades\DB;

/**
 * Cas d'usage : encaissement EN LOT au guichet (#9791).
 *
 * Le guichet encaissait une charge à la fois (`POST .../fee-charges/{charge}/
 * payments`). À la rentrée, une classe entière règle le même jour : le caissier
 * rouvrait la modale trente fois, et rien ne garantissait que la saisie fût
 * cohérente d'un élève à l'autre (même moyen, même référence de bordereau).
 *
 * DEUX GARANTIES, dans cet ordre :
 *
 * 1. **Validation intégrale AVANT toute écriture.** Chaque ligne est contrôlée
 *    (charge du tenant, non terminale, montant > 0, pas de surdébit, devise
 *    cohérente, pas de doublon de charge dans le lot). Si UNE ligne est
 *    invalide, RIEN n'est écrit et l'appelant reçoit la liste des lignes
 *    fautives avec leur index — le guichet corrige la ligne en surbrillance au
 *    lieu de chercher laquelle, parmi trente, a fait échouer le lot.
 *
 * 2. **Écriture atomique.** Une fois toutes les lignes valides, les
 *    encaissements sont enregistrés dans UNE transaction : un lot est un acte
 *    de caisse, il ne peut pas rester à moitié appliqué (les écritures
 *    comptables et les événements d'outbox d'un lot partiel seraient
 *    indémêlables au rapprochement).
 *
 * Idempotence : un `external_id` de lot dérive un `external_id` par ligne
 * (`{lot}:{charge_id}`), donc un rejeu du même bordereau ne double aucun
 * encaissement — la garantie existante de `EduFeeService::recordPayment` est
 * réutilisée telle quelle, sans la réimplémenter.
 */
final class RecordEduFeeBatchPaymentsAction
{
    public function __construct(
        private readonly EduFeeService $fees,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     ok: bool,
     *     errors: list<array{index: int, charge_id: int, error: string}>,
     *     results: list<array<string, mixed>>,
     *     summary: array{lines: int, recorded: int, total_amount: float, currency: string|null}
     * }
     */
    public function execute(Employee $actor, array $data): array
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = is_array($data['lines'] ?? null) ? array_values($data['lines']) : [];

        $method = (string) ($data['method'] ?? EduFeePayment::METHOD_CASH);
        $reference = isset($data['reference']) && $data['reference'] !== ''
            ? (string) $data['reference']
            : null;
        $paidAt = $data['paid_at'] ?? null;
        $batchExternalId = isset($data['external_id']) && $data['external_id'] !== ''
            ? (string) $data['external_id']
            : null;

        // #9793 — identifiant de LOT, distinct de l'idempotence : il regroupe
        // les encaissements d'un même dépôt de guichet pour que le reçu PDF
        // puisse être réimprimé. Sans `external_id` fourni, un UUID est généré
        // — sinon deux lots anonymes seraient indistinguables.
        $batchId = $batchExternalId ?? (string) \Illuminate\Support\Str::uuid();

        // ── 1. Charger toutes les charges en UNE requête, bornées au tenant ──
        $chargeIds = [];
        foreach ($lines as $line) {
            $chargeIds[] = (int) ($line['charge_id'] ?? 0);
        }

        /** @var \Illuminate\Support\Collection<int, EduFeeCharge> $charges */
        $charges = EduFeeCharge::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('id', array_values(array_unique($chargeIds)))
            ->withSum('payments as paid_amount', 'amount')
            ->get()
            ->keyBy(static fn (EduFeeCharge $charge): int => (int) $charge->getAttribute('id'));

        // ── 2. Valider TOUTES les lignes avant d'écrire quoi que ce soit ──
        /** @var list<array{index: int, charge_id: int, error: string}> $errors */
        $errors = [];
        /** @var array<int, true> $seen */
        $seen = [];

        foreach ($lines as $index => $line) {
            $chargeId = (int) ($line['charge_id'] ?? 0);
            $amount = round((float) ($line['amount'] ?? 0), 2);

            if (isset($seen[$chargeId])) {
                $errors[] = [
                    'index' => $index,
                    'charge_id' => $chargeId,
                    'error' => 'EDU_FEE_BATCH_DUPLICATE',
                ];

                continue;
            }
            $seen[$chargeId] = true;

            $charge = $charges->get($chargeId);

            if (! $charge instanceof EduFeeCharge) {
                $errors[] = [
                    'index' => $index,
                    'charge_id' => $chargeId,
                    'error' => 'EDU_FEE_CHARGE_NOT_FOUND',
                ];

                continue;
            }

            if ($charge->isTerminal()) {
                $errors[] = [
                    'index' => $index,
                    'charge_id' => $chargeId,
                    'error' => 'EDU_FEE_TERMINAL',
                ];

                continue;
            }

            $remaining = $this->remainingOf($charge);

            if ($amount > $remaining + 0.004) {
                $errors[] = [
                    'index' => $index,
                    'charge_id' => $chargeId,
                    'error' => 'EDU_FEE_OVERPAYMENT',
                ];

                continue;
            }

            $lineCurrency = isset($line['currency']) && $line['currency'] !== ''
                ? (string) $line['currency']
                : (string) $charge->currency;

            if (strtoupper($lineCurrency) !== strtoupper((string) $charge->currency)) {
                $errors[] = [
                    'index' => $index,
                    'charge_id' => $chargeId,
                    'error' => 'EDU_FEE_CURRENCY_MISMATCH',
                ];
            }
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'results' => [],
                'summary' => [
                    'lines' => count($lines),
                    'recorded' => 0,
                    'total_amount' => 0.0,
                    'currency' => null,
                ],
            ];
        }

        // ── 3. Écrire : une seule transaction, tout ou rien ──
        /** @var array<int, float> $paidSoFar */
        $paidSoFar = [];
        foreach ($charges as $id => $charge) {
            $paidSoFar[(int) $id] = round((float) ($charge->getAttribute('paid_amount') ?? 0), 2);
        }

        /** @var list<array<string, mixed>> $results */
        $results = DB::transaction(function () use (
            $actor,
            $lines,
            $charges,
            $method,
            $reference,
            $paidAt,
            $batchExternalId,
            $batchId,
            &$paidSoFar,
        ): array {
            $written = [];

            foreach ($lines as $line) {
                $chargeId = (int) ($line['charge_id'] ?? 0);
                /** @var EduFeeCharge $charge */
                $charge = $charges->get($chargeId);
                $amount = round((float) ($line['amount'] ?? 0), 2);

                $payload = [
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => isset($line['reference']) && $line['reference'] !== ''
                        ? (string) $line['reference']
                        : $reference,
                    'paid_at' => $paidAt,
                ];

                if ($batchExternalId !== null) {
                    $payload['external_id'] = $batchExternalId.':'.$chargeId;
                }

                $payload['batch_id'] = $batchId;

                ['payment' => $payment, 'charge' => $updated] = $this->fees->recordPayment(
                    $actor,
                    $charge,
                    $payload,
                );

                $paidSoFar[$chargeId] = round($paidSoFar[$chargeId] + $amount, 2);
                $due = round((float) $updated->amount, 2);

                $written[] = [
                    'charge_id' => $chargeId,
                    'payment_id' => (int) $payment->getAttribute('id'),
                    'batch_id' => $batchId,
                    'student_id' => (int) $updated->student_id,
                    'amount' => $amount,
                    'paid_amount' => $paidSoFar[$chargeId],
                    'remaining_amount' => round($due - $paidSoFar[$chargeId], 2),
                    'currency' => (string) $updated->currency,
                    'status' => (string) $updated->status,
                ];
            }

            return $written;
        });

        $total = 0.0;
        $currency = null;
        foreach ($results as $result) {
            $total += (float) $result['amount'];
            $currency ??= (string) $result['currency'];
        }

        return [
            'ok' => true,
            'errors' => [],
            'results' => $results,
            'summary' => [
                'lines' => count($lines),
                'recorded' => count($results),
                'total_amount' => round($total, 2),
                'currency' => $currency,
                'batch_id' => $batchId,
            ],
        ];
    }

    /**
     * Solde restant d'une charge, calculé sur la somme déjà encaissée.
     */
    private function remainingOf(EduFeeCharge $charge): float
    {
        $due = round((float) $charge->amount, 2);
        $paid = round((float) ($charge->getAttribute('paid_amount') ?? 0), 2);

        return round($due - $paid, 2);
    }
}
