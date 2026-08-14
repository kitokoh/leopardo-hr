<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #1813 — Approbation/rejet des modifications de taux légaux.
 *
 * Réservé au `platform_admin` (guard `super_admin_api`, groupe /api/v1/admin).
 * Un comptable/principal peut créer et soumettre, mais seul un admin
 * plateforme peut approuver ou rejeter.
 */
class RateValidationAdminController extends Controller
{
    public function __construct(private readonly TaxRateValidationService $validation) {}

    /**
     * Liste des lignes en attente de validation (barèmes + cotisations),
     * avec leur entreprise (pour le cockpit admin).
     */
    public function pending(Request $request): JsonResponse
    {
        $table = (string) $request->input('table', 'all');

        $items = [];

        if ($table === 'all' || $table === 'tax_slabs') {
            foreach (TaxSlab::query()->pendingValidation()->orderByDesc('updated_at')->limit(200)->get() as $slab) {
                $items[] = $this->serialize($slab, 'tax_slabs');
            }
        }

        if ($table === 'all' || $table === 'social_contributions') {
            foreach (SocialContribution::query()->pendingValidation()->orderByDesc('updated_at')->limit(200)->get() as $contribution) {
                $items[] = $this->serialize($contribution, 'social_contributions');
            }
        }

        return response()->json(['data' => $items]);
    }

    public function approve(Request $request, string $table, int $id): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var SuperAdmin $admin */
        $admin = $request->user();
        $model = $this->resolveModel($table, $id);

        try {
            $this->validation->approve($model, $admin);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $this->serialize($model->refresh(), $table)]);
    }

    public function reject(Request $request, string $table, int $id): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $reason = (string) $request->input('reason', '');
        if (trim($reason) === '') {
            abort(422, 'Un motif de rejet est obligatoire.');
        }

        $model = $this->resolveModel($table, $id);

        /** @var SuperAdmin $admin */
        $admin = $request->user();

        try {
            $this->validation->reject($model, $admin, $reason);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $this->serialize($model->refresh(), $table)]);
    }

    private function resolveModel(string $table, int $id): TaxSlab|SocialContribution
    {
        if ($table === 'tax_slabs') {
            return TaxSlab::query()->findOrFail($id);
        }

        if ($table === 'social_contributions') {
            return SocialContribution::query()->findOrFail($id);
        }

        abort(404, 'Table inconnue.');
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
    private function serialize(TaxSlab|SocialContribution $model, string $table): array
    {
        return [
            'id' => $model->id,
            'table' => $table,
            'company_id' => $model->company_id,
            'country_code' => $model->country_code,
            'name' => $model->name,
            'rate' => $model->rate,
            'status' => $model->status,
            'submitted_by' => $model->submitted_by,
            'validated_by' => $model->validated_by,
            'validated_at' => $model->validated_at?->toIso8601String(),
            'rejection_reason' => $model->rejection_reason,
            'effective_from' => $model->effective_from->toDateString(),
            'effective_to' => $model->effective_to?->toDateString(),
        ];
    }
}
