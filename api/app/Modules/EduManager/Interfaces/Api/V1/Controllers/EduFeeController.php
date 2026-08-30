<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAccountingEntry;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeeChargeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeePaymentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeeTypeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des frais scolaires — EDU-016 (issue #5832).
 *
 * Catalogue des tarifs, facturation par élève (idempotente par
 * `external_id`), encaissements (non-surdébit garanti), abandon/annulation,
 * et écritures comptables du contrat Accounting (rapprochement audité).
 * Direction uniquement (PII élèves + données financières).
 */
class EduFeeController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduFeeService $fees)
    {
    }

    public function indexFeeTypes(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduFeeType::class);

        $query = EduFeeType::query()->where('company_id', $actor->company_id);

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $feeTypes = $query->orderBy('label')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($feeTypes->items())->map(fn (EduFeeType $type): array => $this->feeTypePayload($type)),
            'meta' => $this->meta($feeTypes),
        ]);
    }

    public function storeFeeType(StoreEduFeeTypeRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduFeeType::class);

        $feeType = $this->fees->createFeeType($actor, $request->validated());

        return response()->json(['data' => $this->feeTypePayload($feeType)], 201);
    }

    public function indexCharges(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAnyCharges', EduFeeType::class);

        $query = EduFeeCharge::query()
            ->with('student:id,student_number,display_name')
            ->with('feeType:id,code,label')
            ->where('company_id', $actor->company_id);

        foreach (['status', 'student_id', 'fee_type_id', 'academic_year_id'] as $field) {
            if ($request->filled($field)) {
                $query->where(
                    $field,
                    in_array($field, ['student_id', 'fee_type_id', 'academic_year_id'], true)
                        ? (int) $request->input($field)
                        : (string) $request->input($field)
                );
            }
        }

        $charges = $query->orderByDesc('created_at')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($charges->items())->map(fn (EduFeeCharge $charge): array => $this->chargePayload($charge)),
            'meta' => $this->meta($charges),
        ]);
    }

    public function storeCharge(StoreEduFeeChargeRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('createCharge', EduFeeType::class);

        $data = $request->validated();

        // Montant et devise figés à la création : défaut = tarif du type.
        if (empty($data['amount']) || empty($data['currency'])) {
            /** @var EduFeeType $feeType */
            $feeType = EduFeeType::query()->findOrFail((int) $data['fee_type_id']);
            $data['amount'] ??= (string) $feeType->amount;
            $data['currency'] ??= $feeType->currency;
        }

        $charge = $this->fees->createCharge($actor, $data);

        return response()->json(['data' => $this->chargePayload($charge->load('student:id,student_number,display_name')->load('feeType:id,code,label'))], 201);
    }

    public function storePayment(StoreEduFeePaymentRequest $request, EduFeeCharge $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($charge, $actor->company_id);
        $this->authorize('recordPayment', $charge);

        $payment = $this->fees->recordPayment($actor, $charge, $request->validated());

        return response()->json([
            'data' => [
                'payment' => $this->paymentPayload($payment),
                'charge' => $this->chargePayload($charge->refresh()->load('student:id,student_number,display_name')->load('feeType:id,code,label')),
            ],
        ], 201);
    }

    public function waive(Request $request, EduFeeCharge $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($charge, $actor->company_id);
        $this->authorize('waive', $charge);

        $charge = $this->fees->waive($actor, $charge);

        return response()->json(['data' => $this->chargePayload($charge->load('student:id,student_number,display_name')->load('feeType:id,code,label'))]);
    }

    public function cancel(Request $request, EduFeeCharge $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($charge, $actor->company_id);
        $this->authorize('cancel', $charge);

        $charge = $this->fees->cancel($actor, $charge);

        return response()->json(['data' => $this->chargePayload($charge->load('student:id,student_number,display_name')->load('feeType:id,code,label'))]);
    }

    public function indexEntries(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewEntries', EduFeeType::class);

        $query = EduAccountingEntry::query()->where('company_id', $actor->company_id);

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('from')) {
            $query->where('entry_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('entry_date', '<=', $request->input('to'));
        }

        $entries = $query->orderByDesc('entry_date')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($entries->items())->map(fn (EduAccountingEntry $entry): array => [
                'id' => (int) $entry->getAttribute('id'),
                'source_type' => $entry->source_type,
                'source_id' => (int) $entry->getAttribute('source_id'),
                'entry_date' => $entry->entry_date->toDateString(),
                'account_code' => $entry->account_code,
                'account_label' => $entry->account_label,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
                'reference' => $entry->reference,
            ]),
            'meta' => $this->meta($entries),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function feeTypePayload(EduFeeType $type): array
    {
        return [
            'id' => (int) $type->getAttribute('id'),
            'code' => $type->code,
            'label' => $type->label,
            'amount' => (float) $type->amount,
            'currency' => $type->currency,
            'billing_frequency' => $type->billing_frequency,
            'campus_id' => $type->campus_id,
            'is_active' => $type->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function chargePayload(EduFeeCharge $charge): array
    {
        return [
            'id' => (int) $charge->getAttribute('id'),
            'student_id' => (int) $charge->getAttribute('student_id'),
            'student' => $charge->relationLoaded('student') && $charge->student !== null
                ? [
                    'id' => (int) $charge->student->getAttribute('id'),
                    'student_number' => $charge->student->student_number,
                    'display_name' => $charge->student->display_name,
                ]
                : null,
            'fee_type_id' => (int) $charge->getAttribute('fee_type_id'),
            'fee_type' => $charge->relationLoaded('feeType') && $charge->feeType !== null
                ? [
                    'id' => (int) $charge->feeType->getAttribute('id'),
                    'code' => $charge->feeType->code,
                    'label' => $charge->feeType->label,
                ]
                : null,
            'academic_year_id' => (int) $charge->getAttribute('academic_year_id'),
            'amount' => (float) $charge->amount,
            'currency' => $charge->currency,
            'status' => $charge->status,
            'due_date' => $charge->due_date?->toDateString(),
            'external_id' => $charge->external_id,
            'paid_total' => (float) $charge->payments()->sum('amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(EduFeePayment $payment): array
    {
        return [
            'id' => (int) $payment->getAttribute('id'),
            'fee_charge_id' => (int) $payment->getAttribute('fee_charge_id'),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'external_id' => $payment->external_id,
            'paid_at' => $payment->paid_at->toIso8601String(),
        ];
    }

    /**
     * @return array{current_page: int, per_page: int, total: int}
     */
    private function meta(\Illuminate\Contracts\Pagination\Paginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
