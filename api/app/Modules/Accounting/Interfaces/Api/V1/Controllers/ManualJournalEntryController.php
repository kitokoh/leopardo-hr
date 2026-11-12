<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Services\ManualJournalEntryService;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingManualEntry;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\ManualJournalEntryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Écritures manuelles — journal d'opérations diverses (issue #9369).
 *
 * Le module n'avait **aucune** saisie libre (`/journal` ne proposait que la
 * lecture, l'export et la clôture) : impossible de passer une écriture
 * d'ouverture, une régularisation de TVA, une charge à payer, une dotation, un
 * virement interne ou une correction d'erreur.
 *
 * Codes de retour, alignés sur le reste du module :
 *  - 201 création / 200 modification / 204 suppression ;
 *  - 422 équilibre, compte inconnu ou inactif, ligne débit+crédit ;
 *  - 409 période clôturée ;
 *  - 404 écriture d'un autre tenant (jamais 403 : ne pas divulguer).
 */
final class ManualJournalEntryController extends Controller
{
    public function __construct(
        private readonly ManualJournalEntryService $service,
    ) {}

    /**
     * POST /api/v1/accounting/journal/entries
     */
    public function store(ManualJournalEntryRequest $request): JsonResponse
    {
        $companyId = (string) $request->user()?->getAttribute('company_id');

        /** @var array{entry_date: string, description?: string|null, journal_code?: string|null, piece?: string|null, lines: list<array{account_code: string, debit?: float|int|string, credit?: float|int|string, description?: string|null}>} $payload */
        $payload = $request->validated();

        try {
            $result = $this->service->create(
                $companyId,
                $payload,
                (string) $request->user()?->id,
            );
        } catch (PeriodClosedException $exception) {
            return $this->periodClosed($exception);
        } catch (UnbalancedJournalEntryException $exception) {
            return $this->unbalanced($exception);
        } catch (DocumentWorkflowException $exception) {
            return $this->rejected($exception);
        }

        return response()->json([
            'data' => [
                'manual_ref' => $result['manual_ref'],
                'piece' => $result['piece'],
                'lines' => $result['lines'],
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/accounting/journal/entries/{manual}
     */
    public function update(ManualJournalEntryRequest $request, AccountingManualEntry $manual): JsonResponse
    {
        $this->guardOwnership($request, $manual);

        /** @var array{entry_date: string, description?: string|null, journal_code?: string|null, lines: list<array{account_code: string, debit?: float|int|string, credit?: float|int|string, description?: string|null}>} $payload */
        $payload = $request->validated();

        try {
            $this->service->update($manual, $payload);
        } catch (PeriodClosedException $exception) {
            return $this->periodClosed($exception);
        } catch (UnbalancedJournalEntryException $exception) {
            return $this->unbalanced($exception);
        } catch (DocumentWorkflowException $exception) {
            return $this->rejected($exception);
        }

        return response()->json([
            'data' => [
                'manual_ref' => $manual->manual_ref,
                'piece' => $manual->piece,
                'lines' => $manual->lines()->count(),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/accounting/journal/entries/{manual}
     */
    public function destroy(Request $request, AccountingManualEntry $manual): JsonResponse|Response
    {
        $this->guardOwnership($request, $manual);

        try {
            $this->service->delete($manual);
        } catch (PeriodClosedException $exception) {
            return $this->periodClosed($exception);
        }

        return response()->noContent();
    }

    /**
     * GET /api/v1/accounting/journal/entries/{manual}
     */
    public function show(Request $request, AccountingManualEntry $manual): JsonResponse
    {
        $this->guardOwnership($request, $manual);

        $lines = AccountingJournalEntry::query()
            ->where('company_id', $manual->company_id)
            ->where('source_type', 'manual')
            ->where('manual_ref', $manual->manual_ref)
            ->orderBy('line_no')
            ->get();

        return response()->json([
            'data' => [
                'manual_ref' => $manual->manual_ref,
                'piece' => $manual->piece,
                'entry_date' => $manual->entry_date->toDateString(),
                'period' => $manual->period,
                'journal_code' => $manual->journal_code,
                'description' => $manual->description,
                'lines' => $lines->map(static fn (AccountingJournalEntry $line): array => [
                    'account_code' => $line->account_code,
                    'account_label' => $line->account_label,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'description' => $line->description,
                ])->values(),
            ],
        ]);
    }

    /**
     * Une écriture d'un autre tenant est invisible : 404, jamais 403.
     */
    private function guardOwnership(Request $request, AccountingManualEntry $manual): void
    {
        if ((string) $manual->company_id !== (string) $request->user()?->getAttribute('company_id')) {
            abort(404);
        }
    }

    private function periodClosed(PeriodClosedException $exception): JsonResponse
    {
        return response()->json([
            'code' => 'PERIOD_CLOSED',
            'message' => $exception->getMessage(),
        ], 409);
    }

    private function unbalanced(UnbalancedJournalEntryException $exception): JsonResponse
    {
        return response()->json([
            'code' => 'UNBALANCED_ENTRY',
            'message' => $exception->getMessage(),
        ], 422);
    }

    private function rejected(DocumentWorkflowException $exception): JsonResponse
    {
        return response()->json([
            'code' => 'INVALID_ENTRY',
            'message' => $exception->getMessage(),
        ], 422);
    }
}
