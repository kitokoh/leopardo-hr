<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeeTypeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue des types de frais scolaires — EDU-016 (#5832).
 *
 * Complète `EduFeeTest` (et le parcours client) : le modèle `EduFeeType` et sa
 * requête `StoreEduFeeTypeRequest` existaient, **aucune route ni contrôleur**
 * n'était câblé — `POST /edu-manager/fee-types` répondait 404, rendant la
 * création d'un tarif (scolarité, cantine, transport…) impossible. Un type de
 * frais est le préalable de toute facturation scolaire (`EduFee`).
 *
 * RBAC : direction / RH uniquement (même garde que les autres ressources EDU
 * sans Policy dédiée enregistrée — `EduAccess::isAdmin`).
 */
class EduFeeTypeController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_ADMIN_ONLY');

        $types = EduFeeType::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $types->map(fn (EduFeeType $type): array => $this->payload($type))->all(),
        ]);
    }

    public function store(StoreEduFeeTypeRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_ADMIN_ONLY');

        /** @var EduFeeType $type */
        $type = EduFeeType::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]));

        return response()->json(['data' => $this->payload($type)], 201);
    }

    /**
     * Normalise un montant `decimal` (chaîne côté PHP) en nombre JSON.
     * Les valeurs entières restent des entiers (`50000`, pas `50000.0`) —
     * contrat attendu par les clients web/mobile.
     */
    private function numeric(mixed $raw): int|float|null
    {
        if (! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        return $value === floor($value) ? (int) $value : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduFeeType $type): array
    {
        return [
            'id' => (int) $type->getAttribute('id'),
            'campus_id' => $type->campus_id,
            'code' => (string) $type->code,
            'label' => (string) $type->label,
            // Montants : `decimal` PostgreSQL remonte en chaîne côté PHP —
            // normalisé en nombre pour l'API (contrat mobile/web).
            'amount' => $this->numeric($type->amount),
            'currency' => (string) $type->currency,
            'billing_frequency' => (string) $type->billing_frequency,
            'is_active' => (bool) $type->is_active,
        ];
    }
}
