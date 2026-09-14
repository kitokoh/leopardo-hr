<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAccountingEntry;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Écritures comptables du flux « frais scolaires » — Issue #5832 (EDU-016).
 *
 * Le module EduManager ne tient PAS la comptabilité : il publie les lignes
 * équilibrées (`edu_accounting_entries`) que le module Accounting consomme
 * (pattern PayrollAccountingEntry #5239). Cet endpoint est la vue de
 * rapprochement : lecture seule, bornée au tenant, paginée (`meta.total`).
 *
 * Sans lui, la facturation scolaire était créée sans aucun moyen de contrôle
 * côté client — c'est exactement ce que verrouille `EduFeeTest::
 * test_entries_are_reconciliable_and_isolated`.
 */
class EduAccountingEntryController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_FEE_ADMIN_ONLY');

        $query = EduAccountingEntry::query()
            ->where('company_id', $actor->company_id);

        if ($request->filled('source_type')) {
            $query->where('source_type', (string) $request->input('source_type'));
        }

        if ($request->filled('source_id')) {
            $query->where('source_id', (int) $request->input('source_id'));
        }

        $entries = $query->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($entries->items())->map(fn (EduAccountingEntry $entry): array => [
                'id' => (int) $entry->getAttribute('id'),
                'source_type' => (string) $entry->source_type,
                'source_id' => (int) $entry->source_id,
                'entry_date' => $entry->entry_date->toDateString(),
                'account_code' => (string) $entry->account_code,
                'account_label' => (string) $entry->account_label,
                'debit' => $this->numeric($entry->debit),
                'credit' => $this->numeric($entry->credit),
                'reference' => (string) $entry->reference,
            ])->values(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    private function numeric(mixed $raw): int|float|null
    {
        if (! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        return $value === floor($value) ? (int) $value : $value;
    }
}
