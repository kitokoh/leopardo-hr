<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePaymentDocumentJob;
use App\Models\Employee;
use App\Models\PaymentBatch;
use App\Models\PaymentConfirmation;
use App\Models\PaymentItem;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentBatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $batches = PaymentBatch::query()
            ->where('company_id', $actor->company_id)
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(max(1, min(50, $request->integer('per_page', 20))));

        return response()->json([
            'data' => $batches->getCollection()->map(fn (PaymentBatch $batch): array => $this->batchPayload($batch))->values(),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'payroll_run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ]);

        $run = PayrollRun::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail((int) $validated['payroll_run_id']);

        if (! in_array($run->status, ['calculated', 'validated', 'paid'], true)) {
            throw ValidationException::withMessages([
                'payroll_run_id' => ['Le cycle de paie doit etre calcule ou valide avant de creer un lot de paiement.'],
            ]);
        }

        $slips = PaySlip::query()
            ->where('company_id', $actor->company_id)
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated', 'sent'])
            ->get();

        if ($slips->isEmpty()) {
            throw ValidationException::withMessages([
                'payroll_run_id' => ['Aucun bulletin payable trouve pour ce cycle.'],
            ]);
        }

        $currency = strtoupper((string) ($validated['currency'] ?? currentCompany()->currency ?? 'DZD'));

        $batch = DB::transaction(function () use ($actor, $run, $slips, $currency, $validated): PaymentBatch {
            $batch = PaymentBatch::query()->create([
                'company_id' => $actor->company_id,
                'payroll_run_id' => $run->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'status' => PaymentBatch::STATUS_DRAFT,
                'total_amount' => $slips->sum('net_salary'),
                'currency' => $currency,
                'items_count' => $slips->count(),
                'created_by' => $actor->id,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            foreach ($slips as $slip) {
                PaymentItem::query()->create([
                    'company_id' => $actor->company_id,
                    'payment_batch_id' => $batch->id,
                    'employee_id' => $slip->employee_id,
                    'pay_slip_id' => $slip->id,
                    'amount' => $slip->net_salary,
                    'currency' => $currency,
                    'status' => PaymentItem::STATUS_PENDING,
                ]);
            }

            return $batch->fresh(['items.employee']);
        });

        return response()->json(['data' => $this->batchPayload($batch, includeItems: true)], 201);
    }

    public function show(Request $request, PaymentBatch $paymentBatch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->ensureBatchCompany($paymentBatch, $actor);

        return response()->json([
            'data' => $this->batchPayload($paymentBatch->load(['items.employee', 'items.paySlip']), includeItems: true),
        ]);
    }

    public function markPaid(Request $request, PaymentBatch $paymentBatch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->ensureBatchCompany($paymentBatch, $actor);

        if (! in_array($paymentBatch->status, [PaymentBatch::STATUS_DRAFT, PaymentBatch::STATUS_PROCESSING], true)) {
            throw ValidationException::withMessages([
                'status' => ['Ce lot de paiement ne peut plus etre marque comme paye.'],
            ]);
        }

        $batch = DB::transaction(function () use ($paymentBatch, $actor): PaymentBatch {
            $paymentBatch->forceFill([
                'status' => PaymentBatch::STATUS_PAID,
                'marked_paid_by' => $actor->id,
                'marked_paid_at' => now(),
            ])->save();

            PaymentItem::query()
                ->where('payment_batch_id', $paymentBatch->id)
                ->where('company_id', $actor->company_id)
                ->update([
                    'status' => PaymentItem::STATUS_PAID,
                    'paid_at' => now(),
                ]);

            return $paymentBatch->fresh(['items.paySlip']);
        });

        foreach ($batch->items as $item) {
            if ($item->pay_slip_id && $item->paySlip) {
                GeneratePaymentDocumentJob::dispatchForPaySlip($item->paySlip, $actor->id);
            }
        }

        return response()->json([
            'data' => $this->batchPayload($batch, includeItems: true),
            'message' => 'Paiement en masse declare. Les confirmations employes et documents sont traites en arriere-plan.',
        ], 202);
    }

    public function confirm(Request $request, PaymentItem $paymentItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($paymentItem->company_id !== $actor->company_id || $paymentItem->employee_id !== $actor->id) {
            abort(404);
        }

        if ($paymentItem->status === PaymentItem::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Le paiement doit etre declare par le manager avant confirmation.'],
            ]);
        }

        $validated = $request->validate([
            'device_signature' => ['nullable', 'string', 'max:255'],
            'document_version' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ]);

        $confirmation = DB::transaction(function () use ($request, $paymentItem, $actor, $validated): PaymentConfirmation {
            $confirmation = PaymentConfirmation::query()->firstOrCreate(
                ['payment_item_id' => $paymentItem->id],
                [
                    'company_id' => $actor->company_id,
                    'payment_batch_id' => $paymentItem->payment_batch_id,
                    'employee_id' => $actor->id,
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'device_signature' => $validated['device_signature'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'document_version' => $validated['document_version'] ?? 'v1',
                    'metadata' => $validated['metadata'] ?? null,
                ],
            );

            $paymentItem->forceFill([
                'status' => PaymentItem::STATUS_CONFIRMED,
                'confirmed_at' => $confirmation->confirmed_at,
            ])->save();

            $this->refreshBatchConfirmationStatus($paymentItem->batch);

            return $confirmation;
        });

        return response()->json([
            'data' => [
                'id' => $confirmation->id,
                'payment_item_id' => $confirmation->payment_item_id,
                'status' => $confirmation->status,
                'confirmed_at' => $confirmation->confirmed_at?->toIso8601String(),
                'document_version' => $confirmation->document_version,
            ],
            'message' => 'Reception du paiement confirmee.',
        ]);
    }

    private function ensureBatchCompany(PaymentBatch $batch, Employee $actor): void
    {
        if ($batch->company_id !== $actor->company_id) {
            abort(404);
        }
    }

    private function refreshBatchConfirmationStatus(PaymentBatch $batch): void
    {
        $total = $batch->items()->count();
        $confirmed = $batch->items()->where('status', PaymentItem::STATUS_CONFIRMED)->count();

        if ($total > 0 && $confirmed === $total) {
            $batch->forceFill([
                'status' => PaymentBatch::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ])->save();

            return;
        }

        if ($confirmed > 0) {
            $batch->forceFill(['status' => PaymentBatch::STATUS_PARTIALLY_CONFIRMED])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(PaymentBatch $batch, bool $includeItems = false): array
    {
        $payload = [
            'id' => $batch->id,
            'company_id' => $batch->company_id,
            'payroll_run_id' => $batch->payroll_run_id,
            'period_start' => $batch->period_start?->format('Y-m-d'),
            'period_end' => $batch->period_end?->format('Y-m-d'),
            'status' => $batch->status,
            'total_amount' => $batch->total_amount,
            'currency' => $batch->currency,
            'items_count' => $batch->items_count,
            'marked_paid_at' => $batch->marked_paid_at?->toIso8601String(),
            'confirmed_at' => $batch->confirmed_at?->toIso8601String(),
        ];

        if ($includeItems) {
            $payload['items'] = $batch->items->map(fn (PaymentItem $item): array => [
                'id' => $item->id,
                'employee_id' => $item->employee_id,
                'employee_name' => $item->relationLoaded('employee') && $item->employee
                    ? trim(($item->employee->first_name ?? '').' '.($item->employee->last_name ?? ''))
                    : null,
                'pay_slip_id' => $item->pay_slip_id,
                'amount' => $item->amount,
                'currency' => $item->currency,
                'status' => $item->status,
                'paid_at' => $item->paid_at?->toIso8601String(),
                'confirmed_at' => $item->confirmed_at?->toIso8601String(),
            ])->values();
        }

        return $payload;
    }
}
