<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollPaymentOrder;
use App\Modules\Payroll\Domain\Models\PayrollPaymentOrderItem;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Issue #5239 — Phase C : ordre de virement.
 *
 * Prépare un ordre de virement depuis le net par employé d'un `PayrollRun`
 * validé (réutilisation des formats d'export banque du module Payroll :
 * SEPA XML, CSV générique, virement MA, CPA/BNA DZ…), puis l'exécute
 * (référence banque + date) et le rapproche.
 *
 * Cycle de vie : `prepared` → `executed` → `reconciled`.
 *
 * Aucune modification du moteur Payroll ni de `BankExportGenerator`
 * (FOCUS / PRs en cours) : le générateur est APPELÉ en lecture seule.
 */
class PayrollPaymentOrderService
{
    public const DEFAULT_FORMAT = 'sepa_xml';

    public function __construct(
        private readonly BankExportGenerator $bankExportGenerator,
    ) {}

    /**
     * Prépare un ordre de virement pour un run validé.
     *
     * @param  array<string, mixed>|null  $companyBank  coordonnées banque entreprise
     *                                                  (metadata.bank.*), cf. BankExportGenerator
     */
    public function prepare(
        PayrollRun $run,
        string $format = self::DEFAULT_FORMAT,
        ?array $companyBank = null,
        ?Employee $actor = null,
    ): PayrollPaymentOrder {
        if ($run->status !== PayrollRun::STATUS_VALIDATED && $run->status !== PayrollRun::STATUS_LOCKED) {
            throw new \RuntimeException(
                'Un run doit être validé avant préparation d\'un ordre de virement (statut actuel : '.$run->status.').'
            );
        }

        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule,iban'])
            ->where('status', 'validated')
            ->get();

        if ($slips->isEmpty()) {
            throw new \RuntimeException('Aucun bulletin validé sur ce run — ordre de virement impossible.');
        }

        // Contenu du fichier banque : réutilisation des formats existants.
        // Les gardes is_string de BankExportGenerator valident iban/bic ;
        // le type est volontairement large (array<string, mixed>) car la
        // valeur provient d'un validated() de requête (metadata.bank.*).
        $content = $this->bankExportGenerator->generate($run, $format, $companyBank);
        $fileName = sprintf(
            'payroll-%s-%s.%s',
            $run->id,
            now()->format('Ymd-His'),
            $this->bankExportGenerator->fileExtension($format)
        );
        $filePath = Storage::disk('local')->put("bank-exports/{$fileName}", $content) !== false
            ? "bank-exports/{$fileName}"
            : null;
        if ($filePath === null) {
            Log::warning('payroll.payment_order.file_store_failed', [
                'payroll_run_id' => $run->id,
                'file_name' => $fileName,
            ]);
        }

        $total = (float) $slips->sum('net_salary');

        return DB::transaction(function () use ($run, $format, $filePath, $total, $slips, $actor): PayrollPaymentOrder {
            $order = PayrollPaymentOrder::create([
                'company_id' => $run->company_id,
                'payroll_run_id' => $run->id,
                'status' => PayrollPaymentOrder::STATUS_PREPARED,
                'format' => $format,
                'file_path' => $filePath,
                'total_amount' => $total,
                'transfer_count' => $slips->count(),
                'created_by' => $actor?->id,
            ]);

            $items = $slips->map(fn (PaySlip $slip): array => [
                'payment_order_id' => $order->id,
                'employee_id' => $slip->employee_id,
                'net_amount' => (float) $slip->net_salary,
                'iban' => $slip->employee->iban ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            PayrollPaymentOrderItem::query()->insert($items);

            Log::info('payroll.payment_order.prepared', [
                'payment_order_id' => $order->id,
                'payroll_run_id' => $run->id,
                'total_amount' => $total,
                'transfer_count' => count($items),
                'by' => $actor?->id,
            ]);

            return $order->load('items');
        });
    }

    /**
     * Marque un ordre préparé comme exécuté par le comptable.
     *
     * @param  Carbon|string|null  $executedAt  date d'exécution (défaut : maintenant)
     */
    public function markExecuted(
        PayrollPaymentOrder $order,
        string $bankReference,
        Carbon|string|null $executedAt = null,
        ?Employee $actor = null,
    ): PayrollPaymentOrder {
        if ($order->status !== PayrollPaymentOrder::STATUS_PREPARED) {
            throw new \RuntimeException(
                'Seul un ordre "prepared" peut être exécuté (statut actuel : '.$order->status.').'
            );
        }
        if (trim($bankReference) === '') {
            throw new \InvalidArgumentException('La référence banque est obligatoire.');
        }

        $order->update([
            'status' => PayrollPaymentOrder::STATUS_EXECUTED,
            'bank_reference' => trim($bankReference),
            'executed_by' => $actor?->id,
            'executed_at' => $executedAt ?? now(),
        ]);

        Log::info('payroll.payment_order.executed', [
            'payment_order_id' => $order->id,
            'bank_reference' => $order->bank_reference,
            'by' => $actor?->id,
        ]);

        return $order->fresh() ?? $order;
    }

    /** Rapproche l'ordre exécuté avec les paiements (marquage + audit). */
    public function reconcile(PayrollPaymentOrder $order, ?Employee $actor = null): PayrollPaymentOrder
    {
        if ($order->status !== PayrollPaymentOrder::STATUS_EXECUTED) {
            throw new \RuntimeException(
                'Seul un ordre "executed" peut être rapproché (statut actuel : '.$order->status.').'
            );
        }

        $order->update([
            'status' => PayrollPaymentOrder::STATUS_RECONCILED,
            'reconciled_at' => now(),
        ]);

        Log::info('payroll.payment_order.reconciled', [
            'payment_order_id' => $order->id,
            'by' => $actor?->id,
        ]);

        return $order->fresh() ?? $order;
    }
}
