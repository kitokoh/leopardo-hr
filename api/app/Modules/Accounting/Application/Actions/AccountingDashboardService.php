<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Support\CsvCellSanitizer;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tableaux de bord comptables — issue #5230.
 *
 * Agrégations de pilotage (lecture seule) sur les modèles existants :
 * factures émises, encaissements, impayés (aging) et dépenses fournisseurs.
 * Toutes les requêtes sont scopées sur `company_id` (isolation tenant
 * fail-closed #3727).
 */
final class AccountingDashboardService
{
    /**
     * Statuts comptés comme « factures émises » / « dépenses » (hors
     * brouillons et annulés).
     *
     * @var list<string>
     */
    private const ISSUED_STATUSES = [
        DocumentStatus::Sent->value,
        DocumentStatus::PartiallyPaid->value,
        DocumentStatus::Paid->value,
        DocumentStatus::Overdue->value,
    ];

    /**
     * Statuts d'un document en attente de règlement (impayé).
     *
     * @var list<string>
     */
    private const UNPAID_STATUSES = [
        DocumentStatus::Sent->value,
        DocumentStatus::PartiallyPaid->value,
        DocumentStatus::Overdue->value,
    ];

    /**
     * @return array<string, mixed>
     */
    public function summary(string $companyId, ?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolvePeriod($from, $to);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'invoices' => $this->invoices($companyId, $from, $to),
            'collections' => $this->collections($companyId, $from, $to),
            'expenses' => $this->expenses($companyId, $from, $to),
            'outstanding' => $this->outstanding($companyId),
        ];
    }

    /**
     * Export CSV (UTF-8) de la liste des impayés.
     */
    public function toOutstandingCsv(string $companyId, ?string $from, ?string $to): Response
    {
        [$from, $to] = $this->resolvePeriod($from, $to);

        $outstanding = $this->outstanding($companyId);

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            abort(500, 'CSV_EXPORT_FAILED');
        }

        fputcsv($handle, ['number', 'contact', 'issue_date', 'due_date', 'days_late', 'total_ttc', 'paid_amount', 'due_amount', 'status']);

        foreach ($outstanding['list'] as $row) {
            fputcsv($handle, [
                CsvCellSanitizer::neutralize((string) $row['number']),
                CsvCellSanitizer::neutralize((string) $row['contact']),
                $row['issue_date'],
                $row['due_date'],
                $row['days_late'],
                $row['total_ttc'],
                $row['paid_amount'],
                $row['due_amount'],
                $row['status'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="accounting-dashboard-outstanding-'.$from.'_'.$to.'.csv"',
        ]);
    }

    /**
     * Factures émises (ventes) : documents liés à un contact client ou mixte,
     * hors brouillons/annulés, émis dans la période.
     *
     * @return array<string, mixed>
     */
    private function invoices(string $companyId, string $from, string $to): array
    {
        $query = AccountingDocument::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::ISSUED_STATUSES)
            ->whereBetween('issue_date', [$from, $to])
            ->whereHas('contact', static function ($contactQuery): void {
                $contactQuery->whereIn('type', ['customer', 'both']);
            });

        return [
            'count' => (int) (clone $query)->count(),
            'total_ttc' => round((float) (clone $query)->sum('total_ttc'), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collections(string $companyId, string $from, string $to): array
    {
        $query = AccountingPayment::query()
            ->where('company_id', $companyId)
            ->whereNotNull('received_at')
            ->whereBetween('received_at', [$from, $to]);

        return [
            'count' => (int) (clone $query)->count(),
            'total' => round((float) (clone $query)->sum('amount'), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expenses(string $companyId, string $from, string $to): array
    {
        $query = AccountingDocument::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::ISSUED_STATUSES)
            ->whereBetween('issue_date', [$from, $to])
            ->whereHas('contact', static function ($contactQuery): void {
                $contactQuery->whereIn('type', ['supplier', 'both']);
            });

        return [
            'count' => (int) (clone $query)->count(),
            'total_ttc' => round((float) (clone $query)->sum('total_ttc'), 2),
        ];
    }

    /**
     * Impayés : documents émis non soldés + aging par retard d'échéance.
     *
     * @return array<string, mixed>
     */
    private function outstanding(string $companyId): array
    {
        $today = Carbon::today();

        $query = AccountingDocument::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::UNPAID_STATUSES)
            ->whereColumn('total_ttc', '>', 'paid_amount');

        $documents = (clone $query)
            ->with('contact:id,company_id,name')
            ->orderBy('due_date')
            ->limit(100)
            ->get();

        $aging = [
            '0_30' => ['bucket' => '0_30', 'count' => 0, 'total_due' => 0.0],
            '31_60' => ['bucket' => '31_60', 'count' => 0, 'total_due' => 0.0],
            '61_90' => ['bucket' => '61_90', 'count' => 0, 'total_due' => 0.0],
            '90_plus' => ['bucket' => '90_plus', 'count' => 0, 'total_due' => 0.0],
        ];

        $list = [];

        foreach ($documents as $document) {
            $paidAmount = (float) $document->paid_amount;
            $totalTtc = (float) $document->total_ttc;
            $dueAmount = round(max($totalTtc - $paidAmount, 0.0), 2);
            $dueDate = $document->due_date;

            $daysLate = $dueDate !== null && $dueDate->isBefore($today)
                ? max((int) $dueDate->diffInDays($today), 0)
                : 0;

            $list[] = [
                'id' => $document->id,
                'number' => $document->number,
                'contact' => $document->contact->name ?? '',
                'issue_date' => $document->issue_date->toDateString(),
                'due_date' => $dueDate?->toDateString(),
                'days_late' => $daysLate,
                'total_ttc' => $totalTtc,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'status' => $document->status,
            ];

            if ($dueDate === null || ! $dueDate->isBefore($today)) {
                continue;
            }

            $bucket = match (true) {
                $daysLate > 90 => '90_plus',
                $daysLate > 60 => '61_90',
                $daysLate > 30 => '31_60',
                default => '0_30',
            };

            $aging[$bucket]['count'] += 1;
            $aging[$bucket]['total_due'] = round($aging[$bucket]['total_due'] + $dueAmount, 2);
        }

        $count = (int) (clone $query)->count();
        $totalDue = (float) (clone $query)->selectRaw('SUM(total_ttc - paid_amount) as due')->value('due');

        return [
            'count' => $count,
            'total_due' => round($totalDue, 2),
            'aging' => array_values($aging),
            'list' => $list,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(?string $from, ?string $to): array
    {
        $fromDate = $from !== null && $from !== ''
            ? Carbon::parse($from)
            : Carbon::today()->startOfMonth();

        $toDate = $to !== null && $to !== ''
            ? Carbon::parse($to)
            : Carbon::today();

        if ($fromDate->isAfter($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate->toDateString(), $toDate->toDateString()];
    }
}
