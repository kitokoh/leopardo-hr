<?php

declare(strict_types=1);

namespace App\Modules\Absence\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Types d'absence disponibles pour l'entreprise du salarié connecté.
 *
 * Endpoint : GET /api/v1/absence-types
 * Utilisé par le formulaire de demande de congé (front/web #5693) et
 * les applications mobiles.
 */
class AbsenceTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $types = AbsenceType::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_paid', 'deducts_leave', 'requires_proof', 'max_days_once']);

        return response()->json([
            'data' => $types->map(fn (AbsenceType $t) => [
                'id'            => $t->id,
                'name'          => $t->name,
                'code'          => $t->code,
                'is_paid'       => (bool) $t->is_paid,
                'deducts_leave' => (bool) $t->deducts_leave,
                'requires_proof'=> (bool) $t->requires_proof,
                'max_days_once' => $t->max_days_once,
            ]),
        ]);
    }
}
