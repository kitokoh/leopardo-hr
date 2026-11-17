<?php

declare(strict_types=1);

namespace App\Modules\Expense\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /expense/stats — KPIs des notes de frais pour dashboard Finance (#9550).
 *
 * Agrège : notes par statut, montants par statut et catégorie, délais moyens
 * de traitement et remboursement, top employés.
 *
 * Permission : `expense.reports` ou `expense.manage` (Gate), scope tenant `company_id`.
 */
class ExpenseStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // Check permission: either expense.reports or expense.manage
        if ($actor->cannot('expense.reports') && $actor->cannot('expense.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;
        $companyId = (string) $actor->company_id;

        // Claims stats by status
        $claimsQuery = ExpenseClaim::query()->where('company_id', $companyId);
        if ($from) {
            $claimsQuery->where('created_at', '>=', $from);
        }
        if ($to) {
            $claimsQuery->where('created_at', '<=', $to);
        }

        $totalClaims = $claimsQuery->count();

        $byStatus = ExpenseClaim::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Amounts stats
        $totalAmount = (clone $claimsQuery)->sum('total_amount');
        $currency = (clone $claimsQuery)->value('currency') ?? 'EUR';

        $amountsByStatus = ExpenseClaim::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('status', DB::raw('SUM(total_amount) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // Category breakdown (if category column exists, otherwise skip)
        $amountsByCategory = ExpenseClaim::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('category', DB::raw('SUM(total_amount) as total'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->get()
            ->pluck('total', 'category')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // Processing times
        $avgApprovalTime = ExpenseClaim::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->whereNotNull('approved_at')
            ->whereNotNull('submitted_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (approved_at - submitted_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        $avgPaymentTime = ExpenseClaim::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->whereNotNull('paid_at')
            ->whereNotNull('approved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (paid_at - approved_at)) / 86400) as avg_days')
            ->value('avg_days');

        $pendingApproval = (clone $claimsQuery)->where('status', 'submitted')->count();
        $pendingPayment = (clone $claimsQuery)->where('status', 'approved')->count();

        // Top claimants
        $topClaimants = ExpenseClaim::query()
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select(
                'employee_id',
                DB::raw('COUNT(*) as claims_count'),
                DB::raw('SUM(total_amount) as total_amount')
            )
            ->groupBy('employee_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->with('employee:id,first_name,last_name')
            ->get()
            ->map(function ($item) {
                $name = $item->employee
                    ? trim($item->employee->first_name.' '.$item->employee->last_name)
                    : 'Unknown';

                return [
                    'employee_id' => $item->employee_id,
                    'name' => $name,
                    'claims_count' => (int) $item->getAttribute('claims_count'),
                    'total_amount' => (float) $item->getAttribute('total_amount'),
                ];
            });

        return new JsonResponse([
            'data' => [
                'period' => [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                ],
                'claims' => [
                    'total' => $totalClaims,
                    'by_status' => $byStatus,
                ],
                'amounts' => [
                    'total' => (float) $totalAmount,
                    'currency' => $currency,
                    'by_status' => $amountsByStatus,
                    'by_category' => $amountsByCategory,
                ],
                'processing' => [
                    'avg_approval_time_hours' => $avgApprovalTime ? round((float) $avgApprovalTime, 1) : null,
                    'avg_payment_time_days' => $avgPaymentTime ? round((float) $avgPaymentTime, 1) : null,
                    'pending_approval' => $pendingApproval,
                    'pending_payment' => $pendingPayment,
                ],
                'top_claimants' => $topClaimants->toArray(),
            ],
        ]);
    }
}
