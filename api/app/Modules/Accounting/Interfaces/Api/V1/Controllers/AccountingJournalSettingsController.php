<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Enums\JournalType;
use App\Modules\Accounting\Domain\Models\AccountingJournal;
use App\Modules\Accounting\Infrastructure\Services\ChartAccountResolver;
use App\Modules\Accounting\Infrastructure\Services\JournalResolver;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\AccountingJournalRequest;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\AccountingJournalUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Journaux comptables — paramétrage et lecture (issue #9370).
 *
 * Le module n'avait aucune notion de journal : `FecExporter` le devinait depuis
 * le préfixe des comptes, ce qui éclatait une facture entre `VE` et `OD` (la
 * ligne de TVA `4457` ne commence ni par 6, ni par 7, ni par `411`) et classait
 * en `OD` toutes les écritures d'un tenant non-PCG.
 *
 * RBAC (routes) : `comptable` + `principal` en lecture ; `principal` seul en
 * écriture — un journal est un paramétrage structurant, pas une saisie
 * quotidienne.
 */
final class AccountingJournalController extends Controller
{
    public function __construct(
        private readonly JournalResolver $journals,
    ) {}

    /**
     * GET /api/v1/accounting/journals
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = (string) $request->user()?->company_id;

        $journals = AccountingJournal::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $journals->map(static fn (AccountingJournal $journal): array => [
                'id' => $journal->id,
                'code' => $journal->code,
                'label' => $journal->label,
                'type' => $journal->type,
                'default_account_code' => $journal->default_account_code,
                'is_active' => $journal->is_active,
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/accounting/journals
     */
    public function store(AccountingJournalRequest $request): JsonResponse
    {
        $companyId = (string) $request->user()?->company_id;
        $validated = $request->validated();

        $duplicate = AccountingJournal::query()
            ->where('company_id', $companyId)
            ->where('code', strtoupper((string) $validated['code']))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'code' => 'JOURNAL_CODE_TAKEN',
                'message' => __('accounting.errors.journal_code_taken'),
            ], 422);
        }

        /** @var AccountingJournal $journal */
        $journal = AccountingJournal::create([
            'company_id' => $companyId,
            'code' => strtoupper((string) $validated['code']),
            'label' => $validated['label'],
            'type' => $validated['type'],
            'default_account_code' => $validated['default_account_code'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->payload($journal)], 201);
    }

    /**
     * PUT /api/v1/accounting/journals/{journal}
     *
     * Le code est **immuable** : des écritures le référencent et un changement
     * de code orphelinerait tout l'historique exporté.
     */
    public function update(AccountingJournalUpdateRequest $request, AccountingJournal $journal): JsonResponse
    {
        if ($journal->company_id !== (string) $request->user()?->company_id) {
            // 404 et non 403 : ne jamais divulguer l'existence d'un journal
            // d'un autre tenant.
            abort(404);
        }

        $validated = $request->validated();

        $journal->fill([
            'label' => $validated['label'] ?? $journal->label,
            'type' => $validated['type'] ?? $journal->type,
            'default_account_code' => $validated['default_account_code'] ?? $journal->default_account_code,
            'is_active' => $validated['is_active'] ?? $journal->is_active,
        ])->save();

        return response()->json(['data' => $this->payload($journal->refresh())]);
    }

    /**
     * DELETE /api/v1/accounting/journals/{journal}
     *
     * Refusé (409) dès qu'une écriture référence le journal : un journal
     * mouvementé ne se supprime pas, il se désactive.
     */
    public function destroy(Request $request, AccountingJournal $journal): JsonResponse|Response
    {
        if ($journal->company_id !== (string) $request->user()?->company_id) {
            abort(404);
        }

        if ($journal->hasEntries()) {
            return response()->json([
                'code' => 'JOURNAL_HAS_ENTRIES',
                'message' => __('accounting.errors.journal_has_entries'),
            ], 409);
        }

        $journal->delete();

        return response()->noContent();
    }

    /**
     * POST /api/v1/accounting/journals/seed
     *
     * Provisionne les journaux par défaut du référentiel du tenant (idempotent).
     * Utile pour les entreprises créées avant l'introduction de l'entité et
     * pour celles dont le plan comptable a été rechargé.
     */
    public function seed(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (string) $user?->company_id;

        /** @var \App\Core\Tenant\Domain\Models\Company|null $company */
        $company = \App\Core\Tenant\Domain\Models\Company::query()
            ->withoutGlobalScopes()
            ->find($companyId);

        $resolver = $company !== null
            ? ChartAccountResolver::forCompany($company)
            : ChartAccountResolver::forCountry(null);

        $codes = $this->journals->seedDefaults($companyId, $company?->country, $resolver);

        return response()->json(['data' => $codes], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AccountingJournal $journal): array
    {
        return [
            'id' => $journal->id,
            'code' => $journal->code,
            'label' => $journal->label,
            'type' => $journal->type,
            'default_account_code' => $journal->default_account_code,
            'is_active' => $journal->is_active,
        ];
    }
}
