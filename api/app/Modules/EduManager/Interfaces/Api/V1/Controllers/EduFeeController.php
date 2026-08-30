<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduFee;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\MarkEduFeePaidRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des frais scolaires — EDU-016 (issue #5832).
 *
 * Contrat Accounting : EduManager ne crée AUCUNE écriture comptable ;
 * `EduFee` est le read model consommé par Accounting. Règlement idempotent,
 * remise et annulation terminales, audit edu.fee.*, isolation tenant (404).
 */
class EduFeeController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduFeeService $fees) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduFee::class);

        $query = EduFee::query()->with('student:id,student_number,display_name')
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->input('student_id'));
        }

        $fees = $query->orderByDesc('created_at')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($fees->items())->map(fn (EduFee $fee): array => $this->payload($fee)),
            'meta' => [
                'current_page' => $fees->currentPage(),
                'per_page' => $fees->perPage(),
                'total' => $fees->total(),
            ],
        ]);
    }

    public function store(StoreEduFeeRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduFee::class);

        $fee = $this->fees->create($actor, $request->validated());

        return response()->json(['data' => $this->payload($fee->load('student:id,student_number,display_name'))], 201);
    }

    public function show(Request $request, EduFee $fee): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($fee, $actor->company_id);
        $this->authorize('view', $fee);

        return response()->json(['data' => $this->payload($fee->load('student:id,student_number,display_name'))]);
    }

    public function pay(MarkEduFeePaidRequest $request, EduFee $fee): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($fee, $actor->company_id);
        $this->authorize('update', $fee);

        $fee = $this->fees->markPaid($actor, $fee, $request->validated());

        return response()->json(['data' => $this->payload($fee->load('student:id,student_number,display_name'))]);
    }

    public function cancel(Request $request, EduFee $fee): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($fee, $actor->company_id);
        $this->authorize('update', $fee);

        $fee = $this->fees->cancel($actor, $fee);

        return response()->json(['data' => $this->payload($fee->load('student:id,student_number,display_name'))]);
    }

    public function waive(Request $request, EduFee $fee): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($fee, $actor->company_id);
        $this->authorize('update', $fee);

        $fee = $this->fees->waive($actor, $fee);

        return response()->json(['data' => $this->payload($fee->load('student:id,student_number,display_name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduFee $fee): array
    {
        return [
            'id' => (int) $fee->getAttribute('id'),
            'student_id' => $fee->student_id,
            'student' => $fee->relationLoaded('student') && $fee->student !== null
                ? [
                    'id' => (int) $fee->student->getAttribute('id'),
                    'student_number' => $fee->student->student_number,
                    'display_name' => $fee->student->display_name,
                ]
                : null,
            'admission_id' => $fee->admission_id,
            'label' => $fee->label,
            'amount' => $fee->amount,
            'due_date' => $fee->due_date->toDateString(),
            'status' => $fee->status,
            'external_reference' => $fee->external_reference,
            'payment_reference' => $fee->payment_reference,
            'paid_at' => $fee->paid_at?->toIso8601String(),
        ];
    }
}
