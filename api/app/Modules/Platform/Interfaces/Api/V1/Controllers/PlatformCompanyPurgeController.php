<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\TenantPurgeService;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * #7475 — suppression sûre d'un tenant (super-admin), en deux temps.
 *
 * 1. **Désactivation** (existante, `PATCH /platform/companies/{id}/subscription`
 *    avec `status=suspended`) : l'accès est coupé, les données restent.
 * 2. **Purge ou anonymisation explicite**, sur une société déjà désactivée :
 *    confirmation par ressaisie du **nom exact**, inventaire chiffré, journal.
 *
 * La trace est écrite dans `public.platform_company_purges` — une table
 * **plateforme**, délibérément hors du tenant : `audit_logs` vit dans le schéma
 * du tenant et disparaîtrait avec la purge, alors que le critère d'acceptation
 * exige un résultat **consultable après** l'opération (`GET /platform/company-purges`).
 */
final class PlatformCompanyPurgeController extends Controller
{
    public function __construct(
        private readonly TenantPurgeService $purges,
    ) {}

    /**
     * Inventaire chiffré + éligibilité, avant toute confirmation.
     */
    public function preview(string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);
        $inventory = $this->purges->inventory($company);

        return new JsonResponse([
            'data' => [
                'company' => $this->companyPayload($company, $inventory['schema']),
                'eligible' => $company->status === 'suspended',
                'blocked_reason' => $company->status === 'suspended' ? null : 'COMPANY_NOT_SUSPENDED',
                'required_confirmation' => $company->name,
                'inventory' => $inventory,
                'modes' => [
                    'purge' => 'Détruit toutes les données du tenant (irréversible).',
                    'anonymise' => 'Conserve les données de paie (obligation de conservation) et efface les données identifiantes.',
                ],
                'mode_required' => $inventory['requires_explicit_mode'],
            ],
        ]);
    }

    /**
     * Exécute la purge ou l'anonymisation.
     */
    public function store(Request $request, string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        $validated = $request->validate([
            'confirm_name' => ['required', 'string', 'max:150'],
            'mode' => ['nullable', Rule::in(['purge', 'anonymise'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Critère 1 — aucune suppression sans désactivation préalable.
        if ($company->status !== 'suspended') {
            abort(409, 'COMPANY_NOT_SUSPENDED');
        }

        // Critère 2 — ressaisie du nom exact.
        if (trim((string) $validated['confirm_name']) !== trim((string) $company->name)) {
            abort(422, 'PURGE_CONFIRMATION_MISMATCH');
        }

        $inventory = $this->purges->inventory($company);
        $mode = $validated['mode'] ?? null;

        // Critère 5 — des données de paie ne partent pas sans choix explicite.
        if ($inventory['requires_explicit_mode'] && $mode === null) {
            abort(422, 'PURGE_MODE_REQUIRED');
        }

        $mode ??= 'purge';
        $reason = $validated['reason'] ?? null;
        $operator = $request->user();

        $journalId = $this->openJournal($company, $mode, $reason, $inventory, $operator?->email);

        try {
            $volumes = $mode === 'purge'
                ? $this->purges->purge($company)
                : $this->purges->anonymise($company);
        } catch (Throwable $exception) {
            $this->closeJournal($journalId, 'failed', [], $exception->getMessage());

            throw $exception;
        }

        $this->closeJournal($journalId, 'completed', $volumes, null);

        return new JsonResponse([
            'data' => [
                'journal_id' => $journalId,
                'mode' => $mode,
                'status' => 'completed',
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                ],
                'inventory' => $inventory,
                'volumes' => $volumes,
            ],
        ], 201);
    }

    /**
     * Critère 4 — le résultat est consultable après l'opération.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) min(200, max(1, (int) $request->query('limit', 50)));

        DB::statement('SET search_path TO public');

        $rows = DB::table('public.platform_company_purges')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (int) $row->id,
                'company_id' => (string) $row->company_id,
                'company_name' => (string) $row->company_name,
                'mode' => (string) $row->mode,
                'status' => (string) $row->status,
                'requested_by' => $row->requested_by !== null ? (string) $row->requested_by : null,
                'reason' => $row->reason !== null ? (string) $row->reason : null,
                'volumes' => json_decode((string) $row->volumes, true) ?: [],
                'error' => $row->error !== null ? (string) $row->error : null,
                'created_at' => (string) $row->created_at,
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    /**
     * @param  array<string, mixed>  $inventory
     */
    private function openJournal(
        Company $company,
        string $mode,
        ?string $reason,
        array $inventory,
        ?string $operatorEmail,
    ): int {
        DB::statement('SET search_path TO public');

        return (int) DB::table('public.platform_company_purges')->insertGetId([
            'company_id' => $company->id,
            'company_name' => (string) $company->name,
            'company_slug' => $company->slug,
            'mode' => $mode,
            'status' => 'running',
            'requested_by' => $operatorEmail,
            'requested_by_email' => $operatorEmail,
            'reason' => $reason,
            'volumes' => json_encode($inventory['resources'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ], 'id');
    }

    /**
     * @param  array<string, int>  $volumes
     */
    private function closeJournal(int $journalId, string $status, array $volumes, ?string $error): void
    {
        DB::statement('SET search_path TO public');

        DB::table('public.platform_company_purges')
            ->where('id', $journalId)
            ->update([
                'status' => $status,
                'volumes' => json_encode($volumes, JSON_THROW_ON_ERROR),
                'error' => $error,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company, string $schema): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'status' => $company->status,
            'tenancy_type' => $company->tenancy_type,
            'schema' => $schema,
            'subscription_end' => $company->subscription_end,
        ];
    }
}
