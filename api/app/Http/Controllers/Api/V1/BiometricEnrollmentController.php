<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BiometricEnrollmentRequest;
use App\Models\Employee;
use App\Services\BiometricEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricEnrollmentController extends Controller
{
    public function __construct(
        private readonly BiometricEnrollmentService $biometricEnrollmentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $items = BiometricEnrollmentRequest::query()
            ->where('company_id', $actor->company_id)
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('submitted_at')
            ->limit(50)
            ->get();

        return new JsonResponse([
            'data' => $items->map(fn (BiometricEnrollmentRequest $item) => $this->serialize($item))->values(),
        ]);
    }

    public function myStatus(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $requestItem = BiometricEnrollmentRequest::query()
            ->where('employee_id', $actor->id)
            ->latest('id')
            ->first();

        return new JsonResponse([
            'data' => $requestItem ? $this->serialize($requestItem) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'requested_face_enabled' => ['nullable', 'boolean'],
            'requested_fingerprint_enabled' => ['nullable', 'boolean'],
            'requested_fingerprint_reference_path' => ['nullable', 'string', 'max:255'],
            'requested_fingerprint_device_id' => ['nullable', 'string', 'max:100'],
            'employee_note' => ['nullable', 'string', 'max:1000'],
            'face_image' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $item = $this->biometricEnrollmentService->submit(
            employee: $actor,
            payload: [
                ...$validated,
                'request_source' => 'mobile',
            ],
            faceImage: $request->file('face_image'),
        );

        return new JsonResponse([
            'data' => $this->serialize($item),
        ], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $validated = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = BiometricEnrollmentRequest::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($id);

        $item = $this->biometricEnrollmentService->approve($actor, $item, $validated['manager_note'] ?? null);

        return new JsonResponse([
            'data' => $this->serialize($item),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $validated = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = BiometricEnrollmentRequest::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($id);

        $item = $this->biometricEnrollmentService->reject($actor, $item, $validated['manager_note'] ?? null);

        return new JsonResponse([
            'data' => $this->serialize($item),
        ]);
    }

    private function serialize(BiometricEnrollmentRequest $item): array
    {
        return [
            'id' => $item->id,
            'employee_id' => $item->employee_id,
            'status' => $item->status,
            'requested_face_enabled' => $item->requested_face_enabled,
            'requested_fingerprint_enabled' => $item->requested_fingerprint_enabled,
            'requested_face_reference_path' => $item->requested_face_reference_path,
            'requested_fingerprint_reference_path' => $item->requested_fingerprint_reference_path,
            'requested_fingerprint_device_id' => $item->requested_fingerprint_device_id,
            'employee_note' => $item->employee_note,
            'manager_note' => $item->manager_note,
            'submitted_at' => optional($item->submitted_at)->toIso8601String(),
            'approved_at' => optional($item->approved_at)->toIso8601String(),
            'rejected_at' => optional($item->rejected_at)->toIso8601String(),
        ];
    }
}
