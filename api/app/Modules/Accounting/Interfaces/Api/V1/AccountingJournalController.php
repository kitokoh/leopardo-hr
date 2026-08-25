<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Exports\JournalCsvExporter;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\JournalPeriodRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Journal comptable — consultation par période, export expert, clôture de
 * période, re-posting d'un document + ses paiements. RBAC : comptable /
 * principal (middleware api.manager sur les routes). Issue #5234.
 */
final class AccountingJournalController extends Controller
{
    public function __construct(
        private readonly JournalPostingService $journal,
        private readonly JournalCsvExporter $exporter,
    ) {}

    /**
     * GET /api/v1/accounting/journal?period=YYYY-MM
     */
    public function index(JournalPeriodRequest $request): JsonResponse
    {
        $period = $request->validated('period');

        $entries = $this->journal->entriesForPeriod($period);

        return response()->json([
            'period' => $period,
            'balanced' => $this->journal->isPeriodBalanced($period),
            'closed' => $this->journal->isPeriodClosed($period),
            'totals' => $this->journal->totalsForPeriod($period),
            'entries' => $entries->map(static fn (AccountingJournalEntry $entry): array => [
                'id' => $entry->id,
                'date' => $entry->entry_date->toDateString(),
                'piece' => $entry->piece,
                'description' => $entry->description,
                'account_code' => $entry->account_code,
                'account_label' => $entry->account_label,
                'debit' => $entry->debit,
                'credit' => $entry->credit,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
            ])->values(),
        ]);
    }

    /**
     * GET /api/v1/accounting/journal/export.csv?period=YYYY-MM
     */
    public function export(JournalPeriodRequest $request): StreamedResponse
    {
        $period = $request->validated('period');

        return response()->streamDownload(
            $this->exporter->generateCsvClosure($period),
            'journal-'.$period.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * POST /api/v1/accounting/journal/periods/{period}/close
     */
    public function closePeriod(string $period, Request $request): JsonResponse
    {
        Validator::make(['period' => $period], [
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'period.regex' => __('accounting.validation.period_invalid'),
        ])->validate();

        $closed = $this->journal->closePeriod($period, $request->user() !== null ? (string) $request->user()->id : null);

        return response()->json([
            'period' => $closed->period,
            'closed_at' => $closed->closed_at?->toIso8601String(),
        ], 201);
    }

    /**
     * POST /api/v1/accounting/documents/{document}/journal
     * Re-posting idempotent du document + de ses paiements.
     */
    public function postDocument(string $document, Request $request): JsonResponse
    {
        /** @var AccountingDocument $documentModel */
        $documentModel = AccountingDocument::query()->findOrFail((int) $document);

        $documentEntries = $this->journal->postDocument($documentModel);
        $paymentEntries = 0;
        foreach ($documentModel->payments()->get() as $payment) {
            $paymentEntries += $this->journal->postPayment($payment);
        }

        return response()->json([
            'document_id' => $documentModel->id,
            'entries' => $documentEntries + $paymentEntries,
        ]);
    }
}
