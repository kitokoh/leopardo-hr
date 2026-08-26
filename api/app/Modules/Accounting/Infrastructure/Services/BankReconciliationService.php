<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\BankStatementLineStatus;
use App\Modules\Accounting\Domain\Enums\BankStatementStatus;
use App\Modules\Accounting\Domain\Exceptions\BankStatementLineAlreadyMatchedException;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Domain\Models\BankStatement;
use App\Modules\Accounting\Domain\Models\BankStatementLine;
use Illuminate\Support\Collection;

/**
 * Rapprochement bancaire — matching heuristique + manuel + état (#5435).
 *
 * Matching : lignes relevé ↔ `accounting_payments` (statut `recorded`) sur
 * (montant ± tolérance, date ± N jours, référence/bénéficiaire) avec score de
 * confiance. Score 100 = correspondance exacte → rapprochement automatique ;
 * score < 100 → proposition en file de matching manuel (ligne `pending`).
 *
 * Le rapprochement passe le paiement `recorded → matched` via
 * `PaymentRegistrationService::reconcile()` (idempotent, horodaté
 * `reconciled_at`) — c'est le lettrage v1 (pas de `journal_entries` en Phase D).
 */
final class BankReconciliationService
{
    /** @var array<string, mixed> */
    private array $mapping = [
        'tolerance_amount' => 0.01,
        'tolerance_days' => 3,
    ];

    public function __construct(
        private readonly PaymentRegistrationService $paymentRegistration,
    ) {}

    /**
     * Lance le matching automatique sur un relevé.
     *
     * @return array{auto_matched: int, proposed: int, pending: int}
     */
    public function autoReconcile(BankStatement $statement): array
    {
        $this->loadMapping((string) $statement->company_id);
        $toleranceAmount = $this->toleranceAmount();
        $toleranceDays = $this->toleranceDays();

        $autoMatched = 0;
        $proposed = 0;

        $statement->pendingLines()->orderBy('line_number')->chunkById(100, function (Collection $lines) use (
            $toleranceAmount,
            $toleranceDays,
            &$autoMatched,
            &$proposed,
        ): void {
            foreach ($lines as $line) {
                $candidate = $this->bestCandidate($line, $toleranceAmount, $toleranceDays);
                if ($candidate === null) {
                    continue;
                }

                if ($candidate['score'] >= 100) {
                    $this->matchLine($line, $candidate['payment'], 100);
                    $autoMatched++;
                } else {
                    // correspondance approximative : proposition en file manuelle
                    $line->forceFill(['metadata' => ['proposed_payment_id' => $candidate['payment']->id, 'confidence' => $candidate['score']]])->save();
                    $proposed++;
                }
            }
        });

        $this->refreshStatementStatus($statement);

        return [
            'auto_matched' => $autoMatched,
            'proposed' => $proposed,
            'pending' => $statement->pendingLines()->count(),
        ];
    }

    /**
     * Rapprochement manuel d'une ligne avec un paiement explicite.
     *
     * @throws BankStatementLineAlreadyMatchedException si la ligne ou le paiement est déjà rapproché
     */
    public function matchManually(BankStatementLine $line, AccountingPayment $payment, ?int $confidence = null): void
    {
        if ($line->status === BankStatementLineStatus::Matched->value) {
            throw new BankStatementLineAlreadyMatchedException;
        }
        if ($payment->status === 'matched') {
            throw new BankStatementLineAlreadyMatchedException;
        }

        $this->loadMapping((string) $line->company_id);
        $this->matchLine($line, $payment, $confidence ?? $this->scoreFor($line, $payment, $this->mapping));
        $this->refreshStatementStatus($line->statement);
    }

    /**
     * État de rapprochement d'un relevé.
     *
     * @return array<string, mixed>
     */
    public function status(BankStatement $statement): array
    {
        $lines = $statement->lines()->get();
        $matched = $lines->where('status', BankStatementLineStatus::Matched->value);
        $pending = $lines->where('status', BankStatementLineStatus::Pending->value);

        $sum = static fn (Collection $items): float => (float) array_sum($items->pluck('amount')->all());
        $openingBalance = $statement->opening_balance;
        $expectedClosing = $openingBalance !== null
            ? $openingBalance + $sum($lines)
            : null;

        return [
            'statement_id' => $statement->id,
            'statement_period' => $statement->statement_period,
            'status' => $statement->status,
            'opening_balance' => $statement->opening_balance,
            'closing_balance_expected' => $expectedClosing,
            'closing_balance_reported' => $statement->closing_balance,
            'closing_gap' => $expectedClosing !== null && $statement->closing_balance !== null
                ? round($expectedClosing - (float) $statement->closing_balance, 2)
                : null,
            'total_lines' => $lines->count(),
            'matched_lines' => $matched->count(),
            'pending_lines' => $pending->count(),
            'matched_amount' => round($sum($matched), 2),
            'pending_amount' => round($sum($pending), 2),
        ];
    }

    /**
     * Meilleur paiement candidat pour une ligne.
     *
     * @return array{payment: AccountingPayment, score: int}|null
     */
    private function bestCandidate(BankStatementLine $line, float $toleranceAmount, int $toleranceDays): ?array
    {
        $candidates = AccountingPayment::query()
            ->where('status', 'recorded')
            ->get()
            ->filter(function (AccountingPayment $payment) use ($line, $toleranceAmount, $toleranceDays): bool {
                $amountDiff = abs((float) $payment->amount - abs($line->amount));
                if ($amountDiff > $toleranceAmount) {
                    return false;
                }

                $dateDiff = $payment->received_at !== null
                    ? abs($payment->received_at->diffInDays($line->line_date))
                    : PHP_INT_MAX;

                return $dateDiff <= $toleranceDays;
            })
            ->map(fn (AccountingPayment $payment): array => [
                'payment' => $payment,
                'score' => $this->scoreFor($line, $payment, $this->mapping),
            ])
            ->sortByDesc('score')
            ->values();

        return $candidates->first();
    }

    /**
     * Score de confiance 0-100 : montant (60) + date (20) + référence (20).
     */
    /** @param  array<string, mixed>  $mapping */
    private function scoreFor(BankStatementLine $line, AccountingPayment $payment, array $mapping): int
    {
        $score = 0;

        $amountDiff = abs((float) $payment->amount - abs($line->amount));
        $rawTolerance = $mapping['tolerance_amount'] ?? 0.01;
        $toleranceAmount = is_numeric($rawTolerance) ? (float) $rawTolerance : 0.01;
        if ($amountDiff <= $toleranceAmount) {
            $score += 60;
        }

        $rawDays = $mapping['tolerance_days'] ?? 3;
        $toleranceDays = is_int($rawDays) ? $rawDays : 3;
        $dateDiff = $payment->received_at?->diffInDays($line->line_date);
        if ($dateDiff === 0.0) {
            $score += 20;
        } elseif ($dateDiff !== null && $dateDiff <= (float) $toleranceDays) {
            $score += 10;
        }

        $lineRef = strtolower((string) $line->external_reference);
        $paymentRef = strtolower((string) $payment->reference);
        if ($lineRef !== '' && $paymentRef !== '' && (str_contains($lineRef, $paymentRef) || str_contains($paymentRef, $lineRef))) {
            $score += 20;
        } elseif ($lineRef === '' && $paymentRef === '') {
            $score += 20; // montant + date exacts sans référence = correspondance forte
        }

        return min(100, $score);
    }

    /**
     * Applique le rapprochement (paiement → matched, ligne → matched).
     */
    private function matchLine(BankStatementLine $line, AccountingPayment $payment, int $confidence): void
    {
        $this->paymentRegistration->reconcile($payment);

        $line->forceFill([
            'status' => BankStatementLineStatus::Matched->value,
            'matched_payment_id' => $payment->id,
            'confidence' => $confidence,
        ])->save();
    }

    private function toleranceAmount(): float
    {
        $value = $this->mapping['tolerance_amount'] ?? 0.01;

        return is_numeric($value) ? (float) $value : 0.01;
    }

    private function toleranceDays(): int
    {
        $value = $this->mapping['tolerance_days'] ?? 3;

        return is_int($value) ? $value : 3;
    }

    private function loadMapping(string $companyId): void
    {
        /** @var AccountingSettings|null $settings */
        $settings = AccountingSettings::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $configured = $settings->bank_statement_mapping ?? null;
        if (is_array($configured)) {
            $this->mapping = array_replace($this->mapping, $configured);
        }
    }

    private function refreshStatementStatus(BankStatement $statement): void
    {
        $total = $statement->lines()->count();
        $matched = $statement->matchedLines()->count();
        // Les propositions (matching approximatif) passent aussi le relevé en
        // `reconciling` : une action manuelle est attendue (#5435).
        $proposed = $statement->pendingLines()
            ->get()
            ->filter(static fn (BankStatementLine $line): bool => isset(((array) $line->metadata)['proposed_payment_id']))
            ->count();

        $status = $total > 0 && $matched === $total
            ? BankStatementStatus::Reconciled
            : ($matched > 0 || $proposed > 0 ? BankStatementStatus::Reconciling : BankStatementStatus::Imported);

        $statement->forceFill(['status' => $status->value])->save();
    }
}
