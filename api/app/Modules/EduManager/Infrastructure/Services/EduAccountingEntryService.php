<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Exceptions\UnbalancedEduAccountingEntriesException;
use App\Modules\EduManager\Domain\Models\EduAccountingEntry;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5832 (EDU-016) — contrat Accounting des frais scolaires.
 *
 * Persiste les lignes d'écriture équilibrées (débit = crédit) du flux
 * Frais scolaires → Comptabilité, consommées par le module Accounting
 * (pattern PayrollAccountingEntry #5239) :
 *
 * - charge créée  : 411 Clients (débit) / 706 Prestations (crédit) ;
 * - encaissement  : 512 Banque ou 531 Caisse (débit) / 411 Clients (crédit) ;
 * - abandon       : 654 Pertes sur créances (débit) / 411 Clients (crédit).
 *
 * Idempotence : la contrainte UNIQUE (company_id, source_type, source_id,
 * account_code) garantit la régénération sans doublon (on remplace les lignes
 * de la source). Équilibre vérifié avant persistance (exception sinon).
 * Chaque ligne porte `reference` stable pour le rapprochement audité.
 */
final class EduAccountingEntryService
{
    public const ACCOUNT_RECEIVABLE = '411100';

    public const ACCOUNT_RECEIVABLE_LABEL = 'Clients — frais scolaires';

    public const ACCOUNT_REVENUE = '706100';

    public const ACCOUNT_REVENUE_LABEL = 'Prestations de services — frais scolaires';

    public const ACCOUNT_CASH = '531000';

    public const ACCOUNT_CASH_LABEL = 'Caisse';

    public const ACCOUNT_BANK = '512000';

    public const ACCOUNT_BANK_LABEL = 'Banque';

    public const ACCOUNT_BAD_DEBT = '654000';

    public const ACCOUNT_BAD_DEBT_LABEL = 'Pertes sur créances irrécouvrables';

    /**
     * Génère (ou régénère) les écritures de facturation d'une charge.
     */
    public function generateForCharge(EduFeeCharge $charge, ?Employee $actor = null): int
    {
        $amount = $this->amount($charge->amount);
        $lines = [[
            'account_code' => self::ACCOUNT_RECEIVABLE,
            'account_label' => self::ACCOUNT_RECEIVABLE_LABEL,
            'debit' => $amount,
            'credit' => 0,
        ], [
            'account_code' => self::ACCOUNT_REVENUE,
            'account_label' => self::ACCOUNT_REVENUE_LABEL,
            'debit' => 0,
            'credit' => $amount,
        ]];

        return $this->replace(
            $charge->company_id,
            EduAccountingEntry::SOURCE_FEE_CHARGE,
            (int) $charge->getAttribute('id'),
            $charge->due_date?->toDateString() ?? now()->toDateString(),
            'EDU-FEE-CHARGE-'.$charge->getAttribute('id'),
            $lines,
            $actor
        );
    }

    /**
     * Génère (ou régénère) les écritures d'encaissement d'un paiement.
     */
    public function generateForPayment(EduFeePayment $payment, ?Employee $actor = null): int
    {
        $amount = $this->amount($payment->amount);
        $bank = $payment->method !== EduFeePayment::METHOD_CASH;
        $lines = [[
            'account_code' => $bank ? self::ACCOUNT_BANK : self::ACCOUNT_CASH,
            'account_label' => $bank ? self::ACCOUNT_BANK_LABEL : self::ACCOUNT_CASH_LABEL,
            'debit' => $amount,
            'credit' => 0,
        ], [
            'account_code' => self::ACCOUNT_RECEIVABLE,
            'account_label' => self::ACCOUNT_RECEIVABLE_LABEL,
            'debit' => 0,
            'credit' => $amount,
        ]];

        return $this->replace(
            $payment->company_id,
            EduAccountingEntry::SOURCE_FEE_PAYMENT,
            (int) $payment->getAttribute('id'),
            $payment->paid_at->toDateString(),
            'EDU-FEE-PAYMENT-'.$payment->getAttribute('id'),
            $lines,
            $actor
        );
    }

    /**
     * Génère (ou régénère) les écritures d'abandon d'une charge.
     *
     * L'abandon solde la créance RESTANTE (montant − Σ encaissements) :
     * 654 Pertes (débit) / 411 Clients (crédit). Charge déjà soldée → 0.
     */
    public function generateForWaiver(EduFeeCharge $charge, ?Employee $actor = null): int
    {
        $amount = $this->amount($charge->amount) - (float) $charge->payments()->sum('amount');

        if ($amount <= 0.004) {
            return 0;
        }

        $lines = [[
            'account_code' => self::ACCOUNT_BAD_DEBT,
            'account_label' => self::ACCOUNT_BAD_DEBT_LABEL,
            'debit' => $amount,
            'credit' => 0,
        ], [
            'account_code' => self::ACCOUNT_RECEIVABLE,
            'account_label' => self::ACCOUNT_RECEIVABLE_LABEL,
            'debit' => 0,
            'credit' => $amount,
        ]];

        return $this->replace(
            $charge->company_id,
            EduAccountingEntry::SOURCE_FEE_WAIVER,
            (int) $charge->getAttribute('id'),
            now()->toDateString(),
            'EDU-FEE-WAIVER-'.$charge->getAttribute('id'),
            $lines,
            $actor
        );
    }

    /**
     * @param  array<int, array{account_code: string, account_label: string, debit: float, credit: float}>  $lines
     */
    private function replace(
        string $companyId,
        string $sourceType,
        int $sourceId,
        string $date,
        string $reference,
        array $lines,
        ?Employee $actor,
    ): int {
        $balance = array_sum(array_map(fn (array $line): float => $line['debit'], $lines))
            - array_sum(array_map(fn (array $line): float => $line['credit'], $lines));

        if (abs($balance) > 0.004) {
            throw new UnbalancedEduAccountingEntriesException(
                "Écritures déséquilibrées pour {$sourceType} {$sourceId} : débit − crédit = {$balance}"
            );
        }

        return DB::transaction(function () use ($companyId, $sourceType, $sourceId, $date, $reference, $lines, $actor): int {
            EduAccountingEntry::query()
                ->where('company_id', $companyId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->delete();

            $now = now();
            $rows = [];
            foreach ($lines as $line) {
                $rows[] = [
                    'company_id' => $companyId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'entry_date' => $date,
                    'account_code' => $line['account_code'],
                    'account_label' => $line['account_label'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'reference' => $reference,
                    'created_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                EduAccountingEntry::query()->insert($rows);
            }

            return count($rows);
        });
    }

    private function amount(string $amount): float
    {
        return round((float) $amount, 2);
    }
}
