<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Issue #1815 — Gestion des cotisations sociales nationales (interface admin).
 *
 * Réservé au platform_admin (guard `super_admin_api`, groupe /api/v1/admin).
 * Les lignes nationales (`company_id = null`) sont le référentiel légal par
 * pays ; les overrides par entreprise restent gérés côté tenant.
 *
 * Compatible #1813 : lignes créées `active` (référentiel officiel).
 *
 * Issue #1923 (revue lead) :
 * - chaque mutation (store/update/destroy) est TRACÉE dans
 *   `tax_rate_change_log` (actor_role = platform_admin) et exécutée dans une
 *   transaction ;
 * - garde d'unicité : pas de doublon ACTIF pour la même identité
 *   (pays, code) avec une fenêtre d'effet qui chevauche la nouvelle
 *   (`AbstractCountryRules::resolveContribution()` fait un `->first()` sur le
 *   code : deux lignes actives simultanées rendraient le taux ambigu).
 */
class SocialContributionAdminController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly TaxRateValidationService $validation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $query = SocialContribution::query()->whereNull('company_id');

        if ($request->filled('country_code')) {
            $query->forCountry(strtoupper((string) $request->string('country_code')));
        }
        if ($request->filled('type')) {
            $type = (string) $request->string('type');
            if (in_array($type, ['employee', 'employer'], true)) {
                $query->where('type', $type);
            }
        }

        return response()->json([
            'data' => $query->orderBy('country_code')->orderBy('code')->get()
                ->map(fn (SocialContribution $c): array => $this->serialize($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $validated = $this->validatePayload($request);

        $countryCode = strtoupper($validated['country_code']);
        $effectiveFrom = (string) $validated['effective_from'];
        $effectiveTo = isset($validated['effective_to']) && $validated['effective_to'] !== null
            ? (string) $validated['effective_to']
            : null;

        // Issue #1923 — garde d'unicité avant création (doublon actif).
        $this->assertNoOverlappingActiveContribution(
            $countryCode,
            $validated['code'],
            $effectiveFrom,
            $effectiveTo,
        );

        /** @var SuperAdmin $actor */
        $actor = $request->user();

        $contribution = DB::transaction(function () use ($countryCode, $validated, $effectiveFrom, $effectiveTo, $actor): SocialContribution {
            $contribution = SocialContribution::create([
                'company_id' => null,
                'country_code' => $countryCode,
                'name' => $validated['name'],
                'code' => $validated['code'],
                'type' => $validated['type'],
                'rate' => $validated['rate'],
                'cap' => $validated['cap'] ?? null,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'status' => SocialContribution::STATUS_ACTIVE,
            ]);

            $this->validation->logAdminCreated($contribution, $actor);

            return $contribution;
        });

        return response()->json(['data' => $this->serialize($contribution)], 201);
    }

    public function update(Request $request, int $socialContribution): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var SocialContribution $contribution */
        $contribution = SocialContribution::query()->whereNull('company_id')->findOrFail($socialContribution);

        $validated = $this->validatePayload($request, partial: true);

        // Normalisation pays (le guard d'unicité compare en majuscules).
        if (isset($validated['country_code'])) {
            $validated['country_code'] = strtoupper((string) $validated['country_code']);
        }

        // Issue #1923 — la garde d'unicité porte sur l'identité/window APRÈS
        // fusion (update partiel : les champs absents gardent leur valeur).
        $merged = array_merge([
            'country_code' => $contribution->country_code,
            'code' => $contribution->code,
            'effective_from' => $contribution->effective_from->toDateString(),
            'effective_to' => $contribution->effective_to?->toDateString(),
        ], $validated);

        $this->assertNoOverlappingActiveContribution(
            strtoupper((string) $merged['country_code']),
            (string) $merged['code'],
            (string) $merged['effective_from'],
            isset($merged['effective_to']) && $merged['effective_to'] !== null ? (string) $merged['effective_to'] : null,
            exceptId: (int) $contribution->id,
        );

        /** @var SuperAdmin $actor */
        $actor = $request->user();

        DB::transaction(function () use ($contribution, $validated, $actor): void {
            $previous = TaxRateChangeLog::snapshot($contribution);
            $contribution->update($validated);
            $this->validation->logAdminUpdated($contribution, $actor, $previous);
        });

        return response()->json(['data' => $this->serialize($contribution->refresh())]);
    }

    public function destroy(Request $request, int $socialContribution): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var SocialContribution $contribution */
        $contribution = SocialContribution::query()->whereNull('company_id')->findOrFail($socialContribution);

        /** @var SuperAdmin $actor */
        $actor = $request->user();

        DB::transaction(function () use ($contribution, $actor): void {
            $snapshot = TaxRateChangeLog::snapshot($contribution);
            $contribution->delete();
            $this->validation->logAdminDeleted($contribution, $actor, $snapshot);
        });

        return response()->json(null, 204);
    }

    /**
     * Issue #1923 — garde d'unicité : refuse un doublon ACTIF de même code
     * (pays + code) dont la fenêtre d'effet chevauche
     * [effectiveFrom, effectiveTo] (bornes incluses, null = ouvert).
     */
    private function assertNoOverlappingActiveContribution(
        string $countryCode,
        string $code,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $exceptId = null,
    ): void {
        $query = SocialContribution::query()
            ->whereNull('company_id')
            ->where('country_code', $countryCode)
            ->where('status', SocialContribution::STATUS_ACTIVE)
            ->where('code', $code);

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
     * @return array{country_code: string, name: string, code: string, type: string, rate: float, cap?: float|null, effective_from: string, effective_to?: string|null}
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            // #1951 : contrat partagé du moteur (plus de liste in: hardcodée).
            'country_code' => ['required', 'string', 'size:2', Rule::in($this->payrollCalculator->rulesResolver()->supportedCountryCodes())],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(['employee', 'employer'])],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cap' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];

        if ($partial) {
            $rules = array_map(static fn ($rule): array => ['sometimes', ...(array) $rule], $rules);
        }

        $validated = $request->validate($rules);

        /** @var array{country_code: string, name: string, code: string, type: string, rate: float, cap?: float|null, effective_from: string, effective_to?: string|null} $validated */
        return $validated;
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
    private function serialize(SocialContribution $contribution): array
    {
        /** @var Carbon|null $effectiveTo */
        $effectiveTo = $contribution->effective_to;

        return [
            'id' => $contribution->id,
            'company_id' => $contribution->company_id,
            'country_code' => $contribution->country_code,
            'name' => $contribution->name,
            'code' => $contribution->code,
            'type' => $contribution->type,
            'rate' => $contribution->rate,
            'cap' => $contribution->cap,
            'effective_from' => $contribution->effective_from->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'status' => $contribution->status,
        ];
    }
}
