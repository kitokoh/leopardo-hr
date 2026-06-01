<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentDocumentResource;
use App\Models\Employee;
use App\Models\PaymentDocument;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentDocumentController extends Controller
{
    public function myDocuments(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'document_type' => ['sometimes', 'string', 'in:'.implode(',', PaymentDocument::TYPES)],
            'status' => ['sometimes', 'string', 'in:pending,generating,available,failed'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = PaymentDocument::query()
            ->availableToEmployee($actor)
            ->latest();

        if (isset($validated['document_type'])) {
            $query->where('document_type', $validated['document_type']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return PaymentDocumentResource::collection($query->paginate((int) ($validated['per_page'] ?? 15)))
            ->response();
    }

    public function download(Request $request, PaymentDocument $paymentDocument): StreamedResponse|JsonResponse|Response
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorizeDocumentAccess($paymentDocument, $actor);

        if ($paymentDocument->status !== PaymentDocument::STATUS_AVAILABLE || $paymentDocument->path === null) {
            return response()->json([
                'message' => 'Document is not available yet.',
                'status' => $paymentDocument->status,
            ], 409);
        }

        $disk = Storage::disk($paymentDocument->disk ?: 'local');
        if (! $disk->exists($paymentDocument->path)) {
            return response()->json(['message' => 'Document file is missing.'], 404);
        }

        return $disk->download(
            $paymentDocument->path,
            $paymentDocument->filename ?: 'payment-document.pdf',
            ['Content-Type' => $paymentDocument->mime_type ?: 'application/pdf'],
        );
    }

    public function payrollDocuments(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }

        $documents = PaymentDocument::query()
            ->where('company_id', $actor->company_id)
            ->where('payroll_run_id', $payrollRun->id)
            ->with('employee:id,first_name,last_name,email')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return PaymentDocumentResource::collection($documents)->response();
    }

    private function authorizeDocumentAccess(PaymentDocument $paymentDocument, Employee $actor): void
    {
        if ($paymentDocument->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($actor->isManager()) {
            return;
        }

        if ($paymentDocument->employee_id !== $actor->id) {
            abort(403);
        }
    }
}
