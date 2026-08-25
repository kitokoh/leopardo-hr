<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Enums\AccountType;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use App\Modules\Accounting\Infrastructure\Services\ChartOfAccountsService;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\StoreChartAccountRequest;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\UpdateChartAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Plan comptable par entreprise — issue #5422.
 *
 * RBAC (matrice comptabilité) : `comptable` (CRUD complet), `principal`
 * (lecture + paramétrage) — mêmes middlewares que les contacts (#5222).
 *
 * Isolation tenant : AccountingChartAccount porte BelongsToCompany
 * (scope global fail-closed #3727) — un compte d'un autre tenant est 404.
 *
 * Invariants :
 *   - code unique par entreprise, normalisé (chiffres) ;
 *   - les comptes système (provisionnés) sont désactivables mais jamais
 *     supprimables (trace d'audit) ;
 *   - un compte porteur d'écritures au journal ne peut pas être supprimé.
 */
class AccountingChartController extends Controller
{
    public function index(Request $request, ChartOfAccountsService $service): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', AccountType::values())],
            'active_only' => ['nullable', 'boolean'],
        ]);

        $type = isset($validated['type']) ? (string) $validated['type'] : null;
        $activeOnly = $request->boolean('active_only', true);

        $accounts = $service->list(
            (string) $request->user()?->company_id,
            $type,
            $activeOnly,
        );

        return response()->json([
            'data' => $accounts->map(fn (AccountingChartAccount $account): array => $this->serialize($account))->values(),
            'meta' => ['count' => $accounts->count()],
        ]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $account = $this->findAccount($request, $code);

        return response()->json(['data' => $this->serialize($account)]);
    }

    public function store(StoreChartAccountRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['company_id'] = $request->user()?->company_id;
        $payload['code'] = $this->normalizeCode((string) $payload['code']);
        $payload['is_system'] = false;

        $account = DB::transaction(fn (): AccountingChartAccount => AccountingChartAccount::query()->create($payload));

        return response()->json(['data' => $this->serialize($account)], 201);
    }

    public function update(UpdateChartAccountRequest $request, string $code): JsonResponse
    {
        $account = $this->findAccount($request, $code);

        $payload = $request->validated();
        if (isset($payload['code'])) {
            $payload['code'] = $this->normalizeCode((string) $payload['code']);
        }

        $account->update($payload);

        return response()->json(['data' => $this->serialize($account->fresh())]);
    }

    public function destroy(Request $request, string $code): JsonResponse
    {
        $account = $this->findAccount($request, $code);

        if ($account->is_system) {
            return response()->json([
                'message' => __('accounting.chart_system_account_not_deletable'),
                'code' => 'CHART_SYSTEM_ACCOUNT_NOT_DELETABLE',
            ], 422);
        }

        $hasEntries = $account->journalEntries()->exists();
        if ($hasEntries) {
            return response()->json([
                'message' => __('accounting.chart_account_has_entries'),
                'code' => 'CHART_ACCOUNT_HAS_ENTRIES',
            ], 422);
        }

        $account->delete();

        return response()->json(null, 204);
    }

    private function findAccount(Request $request, string $code): AccountingChartAccount
    {
        $account = AccountingChartAccount::query()
            ->where('company_id', $request->user()?->company_id)
            ->where('code', $this->normalizeCode($code))
            ->first();

        abort_unless($account instanceof AccountingChartAccount, 404);

        return $account;
    }

    private function normalizeCode(string $code): string
    {
        return preg_replace('/[^0-9]/', '', $code) ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AccountingChartAccount $account): array
    {
        return [
            'code' => $account->code,
            'label' => $account->label,
            'type' => $account->type,
            'class' => $account->class,
            'is_system' => $account->is_system,
            'is_active' => $account->is_active,
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }
}
