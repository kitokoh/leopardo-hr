<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Issue #1814 — Gestion des barèmes fiscaux nationaux (interface admin).
 *
 * Réservé au platform_admin (guard `super_admin_api`, groupe /api/v1/admin).
 * Les lignes nationales (`company_id = null`) sont le référentiel légal par
 * pays ; les overrides par entreprise restent gérés côté tenant (#1813).
 *
 * Compatible avec #1813 : les lignes sont créées `active` (référentiel
 * officiel), le workflow de validation s'applique aux propositions tenant.
 *
 * Issue #1923 (revue lead) :
 * - chaque mutation (store/update/destroy/resetDefaults) est TRACÉE dans
 *   `tax_rate_change_log` (actor_role = platform_admin) et exécutée dans une
 *   transaction — plus de suppression/recréation partielle ;
 * - garde d'unicité : pas de doublon ACTIF pour la même identité
 *   (pays, min/max) avec une fenêtre d'effet qui chevauche la nouvelle
 *   (les doublons actifs rendaient le calcul de paie ambigu).
 */
class TaxSlabAdminController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly TaxRateValidationService $validation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $query = TaxSlab::query()->whereNull('company_id');

        if ($request->filled('country_code')) {
            $query->forCountry(strtoupper((string) $request->string('country_code')));
        }

        return response()->json([
            'data' => $query->orderBy('country_code')->orderBy('min_amount')->get()
                ->map(fn (TaxSlab $slab): array => $this->serialize($slab)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $validated = $this->validatePayload($request);

        $countryCode = strtoupper($validated['country_code']);
        $effectiveFrom = (string) $validated['effective_from'];
        $effectiveTo = isset($validated['effective_to']) ? (string) $validated['effective_to'] : null;

        // Issue #1923 — garde d'unicité avant création (doublon actif).
        $this->assertNoOverlappingActiveSlab(
            $countryCode,
            (float) $validated['min_amount'],
            isset($validated['max_amount']) ? (float) $validated['max_amount'] : null,
            $effectiveFrom,
            $effectiveTo,
        );

        /** @var SuperAdmin $actor */
        $actor = $request->user();

        $slab = DB::transaction(function () use ($countryCode, $validated, $effectiveFrom, $effectiveTo, $actor): TaxSlab {
            $slab = TaxSlab::create([
                'company_id' => null,
                'country_code' => $countryCode,
                'name' => $validated['name'],
                'legal_reference' => $validated['legal_reference'] ?? null,
                'min_amount' => $validated['min_amount'],
                'max_amount' => $validated['max_amount'] ?? null,
                'rate' => $validated['rate'],
                'fixed_deduction' => (float) ($validated['fixed_deduction'] ?? 0),
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'status' => TaxSlab::STATUS_ACTIVE,
            ]);

            $this->validation->logAdminCreated($slab, $actor);

            return $slab;
        });

        return response()->json(['data' => $this->serialize($slab)], 201);
    }

    public function update(Request $request, int $taxSlab): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var TaxSlab $slab */
        $slab = TaxSlab::query()->whereNull('company_id')->findOrFail($taxSlab);

        $validated = $this->validatePayload($request, partial: true);

        // Issue #1923 — la garde d'unicité porte sur l'identité/window APRÈS
        // fusion (update partiel : les champs absents gardent leur valeur).
        $merged = array_merge([
            'country_code' => $slab->country_code,
            'min_amount' => (float) $slab->min_amount,
            'max_amount' => $slab->max_amount === null ? null : (float) $slab->max_amount,
            'effective_from' => $slab->effective_from->toDateString(),
            'effective_to' => $slab->effective_to?->toDateString(),
        ], $validated);

        // Normalisation pays (le guard d'unicité compare en majuscules) —
        // APRÈS fusion : country_code est toujours défini (défaut : valeur
        // courante du barème), pas de lecture directe d'une clé absente.
        $merged['country_code'] = strtoupper((string) $merged['country_code']);

        $this->assertNoOverlappingActiveSlab(
            strtoupper((string) $merged['country_code']),
            (float) $merged['min_amount'],
            $merged['max_amount'] !== null ? (float) $merged['max_amount'] : null,
            (string) $merged['effective_from'],
            $merged['effective_to'] !== null ? (string) $merged['effective_to'] : null,
            exceptId: (int) $slab->id,
        );

        /** @var SuperAdmin $actor */
        $actor = $request->user();

        DB::transaction(function () use ($slab, $validated, $actor): void {
            $previous = TaxRateChangeLog::snapshot($slab);
            $slab->update($validated);
            $this->validation->logAdminUpdated($slab, $actor, $previous);
        });

        return response()->json(['data' => $this->serialize($slab->refresh())]);
    }

    public function destroy(Request $request, int $taxSlab): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var TaxSlab $slab */
        $slab = TaxSlab::query()->whereNull('company_id')->findOrFail($taxSlab);

        /** @var SuperAdmin $actor */
        $actor = $request->user();

        DB::transaction(function () use ($slab, $actor): void {
            $snapshot = TaxRateChangeLog::snapshot($slab);
            $slab->delete();
            $this->validation->logAdminDeleted($slab, $actor, $snapshot);
        });

        return response()->json(null, 204);
    }

    /**
     * Réinitialise les tranches nationales d'un pays avec les valeurs légales
     * par défaut du moteur (`defaultTaxSlabs`), après confirmation.
     *
     * Issue #1923 : suppression + recréation dans UNE transaction, chaque
     * suppression et chaque création tracées dans l'audit trail.
     */
    public function resetDefaults(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $countryCode = strtoupper((string) $request->string('country_code'));
        $this->assertCountry($countryCode);

        /** @var SuperAdmin $actor */
        $actor = $request->user();

        $created = DB::transaction(function () use ($countryCode, $actor): int {
            // Supprime les lignes nationales existantes du pays (référentiel),
            // en traçant chaque suppression.
            $existing = TaxSlab::query()
                ->whereNull('company_id')
                ->where('country_code', $countryCode)
                ->get();

            foreach ($existing as $slab) {
                $snapshot = TaxRateChangeLog::snapshot($slab);
                $slab->delete();
                $this->validation->logAdminDeleted($slab, $actor, $snapshot);
            }

            // Après suppression, taxSlabs() retombe sur les défauts légaux du moteur.
            $defaults = $this->payrollCalculator->getRules($countryCode)->taxSlabs();

            $created = 0;
            foreach ($defaults as $slab) {
                $row = TaxSlab::create([
                    'company_id' => null,
                    'country_code' => $countryCode,
                    'name' => __('payroll.tax_scale_default_name', ['country' => $countryCode, 'year' => (string) now()->year]),
                    'min_amount' => $slab['min'],
                    'max_amount' => $slab['max'],
                    'rate' => $slab['rate'],
                    'fixed_deduction' => $slab['fixed_deduction'],
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'effective_to' => null,
                    'status' => TaxSlab::STATUS_ACTIVE,
                ]);

                $this->validation->logAdminCreated($row, $actor);
                $created++;
            }

            return $created;
        });

        return response()->json(['data' => ['country_code' => $countryCode, 'created' => $created]]);
    }

    /**
     * Issue #1923 — garde d'unicité : refuse un doublon ACTIF de même
     * identité (pays + min/max) dont la fenêtre d'effet chevauche
     * [effectiveFrom, effectiveTo] (bornes incluses, null = ouvert).
     */
    private function assertNoOverlappingActiveSlab(
        string $countryCode,
        float $minAmount,
        ?float $maxAmount,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $exceptId = null,
    ): void {
        $query = TaxSlab::query()
            ->whereNull('company_id')
            ->where('country_code', $countryCode)
            ->where('status', TaxSlab::STATUS_ACTIVE)
            ->where('min_amount', $minAmount)
            ->where('max_amount', $maxAmount);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        // Chevauchement : a1 <= b2 AND b1 <= a2 (b2/a2 null = +∞).
        $query->where(function (Builder $q) use ($effectiveFrom): void {
            $q->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveFrom);
        });

        if ($effectiveTo !== null) {
            $query->where('effective_from', '<=', $effectiveTo);
        }

        if ($query->exists()) {
            abort(422, __('payroll.rate_overlap_conflict'));
        }
    }

    /**
     * @return array{country_code: string, name: string, legal_reference?: string|null, min_amount: float, max_amount?: float|null, rate: float, fixed_deduction?: float, effective_from: string, effective_to?: string|null}
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            // #1951 : contrat partagé du moteur (plus de liste in: hardcodée).
            'country_code' => ['required', 'string', 'size:2', Rule::in($this->payrollCalculator->rulesResolver()->supportedCountryCodes())],
            'name' => ['required', 'string', 'max:150'],
            'legal_reference' => ['nullable', 'string', 'max:200'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed_deduction' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];

        if ($partial) {
            $rules = array_map(static fn ($rule): array => ['sometimes', ...(array) $rule], $rules);
        }

        $validated = $request->validate($rules);

        /** @var array{country_code: string, name: string, legal_reference?: string|null, min_amount: float, max_amount?: float|null, rate: float, fixed_deduction?: float, effective_from: string, effective_to?: string|null} $validated */
        return $validated;
    }

    private function assertCountry(string $countryCode): void
    {
        // #1951 : contrat partagé du moteur.
        if (! in_array($countryCode, $this->payrollCalculator->rulesResolver()->supportedCountryCodes(), true)) {
            abort(422, __('payroll.rate_country_unsupported'));
        }
    }

    private function assertPlatformAdmin(Request $request): void
    {
        if (! $request->user() instanceof SuperAdmin) {
            abort(403, __('errors.FORBIDDEN'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TaxSlab $slab): array
    {
        /** @var Carbon|null $effectiveTo */
        $effectiveTo = $slab->effective_to;

        return [
            'id' => $slab->id,
            'company_id' => $slab->company_id,
            'country_code' => $slab->country_code,
            'name' => $slab->name,
            'legal_reference' => $slab->legal_reference,
            'min_amount' => $slab->min_amount,
            'max_amount' => $slab->max_amount,
            'rate' => $slab->rate,
            'fixed_deduction' => $slab->fixed_deduction,
            'effective_from' => $slab->effective_from->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'status' => $slab->status,
        ];
    }
}
