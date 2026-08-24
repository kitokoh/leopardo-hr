<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Exceptions\DomainException;
use App\Modules\Accounting\Domain\Enums\PaymentOrderStatus;
use App\Modules\Accounting\Domain\Exceptions\PaymentOrderNotExecutableException;
use App\Modules\Accounting\Domain\Exceptions\PaymentOrderNotPreparableException;
use App\Modules\Accounting\Domain\Models\AccountingPaymentOrder;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use App\Support\CountryDefaults;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ordres de virement salarial — flux Paie → Comptabilité (issue #5239, Phase C).
 *
 * Un ordre est créé depuis un run de paie validé (net par employé, somme des
 * bulletins validés), préparé par le comptable (export banque — formats
 * Payroll réutilisés via BankExportGenerator : cnep_dz, sepa_xml, csv_generic…),
 * puis exécuté (référence banque + date) — l'exécution vaut rapprochement.
 *
 * Règles :
 *   - le run doit être `validated` ou `locked` (jamais avant l'étape RH) ;
 *   - un seul ordre par (company, run) — création idempotente (UNIQUE) ;
 *   - `prepare` uniquement depuis `draft` ; `execute` uniquement depuis
 *     `prepared` (double exécution refusée) ;
 *   - aucune modification du moteur Payroll (FOCUS intact) : lecture seule du
 *     run et des bulletins validés.
 */
final class PaymentOrderService
{
    private const ALLOWED_RUN_STATUSES = ['validated', 'locked'];

    public function __construct(
        private readonly BankExportGenerator $bankExportGenerator,
    ) {}

    public function createFromRun(PayrollRun $run, ?int $actorId = null): AccountingPaymentOrder
    {
        if (! in_array($run->status, self::ALLOWED_RUN_STATUSES, true)) {
            throw new DomainException(
                'PAYROLL_RUN_NOT_VALIDATED',
                422,
                'PAYROLL_RUN_NOT_VALIDATED'
            );
        }

        $existing = AccountingPaymentOrder::query()
            ->where('payroll_run_id', $run->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $totalNet = (float) $run->paySlips()
            ->where('status', 'validated')
            ->sum('net_salary');

        $currency = CountryDefaults::for($run->country_code)['currency'];

        /** @var AccountingPaymentOrder $order */
        $order = AccountingPaymentOrder::create([
            'payroll_run_id' => $run->id,
            'status' => PaymentOrderStatus::Draft->value,
            'total_net' => round($totalNet, 2),
            'currency' => $currency,
            'created_by' => $actorId,
        ]);

        return $order;
    }

    public function prepare(AccountingPaymentOrder $order, string $format, ?int $actorId = null): AccountingPaymentOrder
    {
        if ($order->status !== PaymentOrderStatus::Draft->value) {
            throw new PaymentOrderNotPreparableException($order->status);
        }

        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->find($order->payroll_run_id);
        if ($run === null) {
            throw new DomainException('NOT_FOUND', 404, 'NOT_FOUND');
        }

        try {
            $content = $this->bankExportGenerator->generate($run, $format);
        } catch (Throwable $throwable) {
            Log::error('PaymentOrder: échec de génération de l\'export banque', [
                'order_id' => $order->id,
                'payroll_run_id' => $run->id,
                'format' => $format,
                'error' => $throwable->getMessage(),
            ]);

            throw new DomainException(
                'PAYMENT_ORDER_EXPORT_FAILED',
                422,
                'PAYMENT_ORDER_EXPORT_FAILED'
            );
        }

        $extension = $format === 'sepa_xml' ? 'xml' : 'csv';
        $path = sprintf('payment-orders/order-%d-%s.%s', $order->id, $format, $extension);
        Storage::disk('local')->put($path, $content);

        $order->update([
            'status' => PaymentOrderStatus::Prepared->value,
            'export_format' => $format,
            'export_file' => $path,
        ]);

        return $order;
    }

    public function execute(
        AccountingPaymentOrder $order,
        string $bankReference,
        ?Carbon $executedAt = null,
        ?int $actorId = null,
    ): AccountingPaymentOrder {
        if (! $order->isPrepared()) {
            throw new PaymentOrderNotExecutableException($order->status);
        }

        $order->update([
            'status' => PaymentOrderStatus::Executed->value,
            'bank_reference' => $bankReference,
            'executed_at' => $executedAt ?? Carbon::now(),
            'executed_by' => $actorId,
        ]);

        return $order;
    }
}
