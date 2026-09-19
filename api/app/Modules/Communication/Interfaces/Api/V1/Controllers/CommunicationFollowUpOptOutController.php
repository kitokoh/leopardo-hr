<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpOptOut;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opt-outs de relance (BC-29 COMMUNICATION, R4 #7689) — exclusion locale
 * d'un destinataire, a l'echelle du TENANT (UNIQUE company×email).
 *
 * Garde-fou protecteur : tout employe peut lister et AJOUTER une exclusion
 * (upsert idempotent, 200 si deja presente) ; la SUPPRESSION (qui
 * re-autorise les relances) est reservee aux managers principal/rh
 * (`CommunicationFollowUpOptOutPolicy`), cross-tenant = 404.
 */
class CommunicationFollowUpOptOutController extends Controller
{
    use AssertsTenantScope;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationFollowUpOptOut::class);

        $optOuts = CommunicationFollowUpOptOut::query()
            ->orderBy('email')
            ->get()
            ->map(fn (CommunicationFollowUpOptOut $optOut): array => $this->present($optOut));

        return new JsonResponse(['data' => $optOuts->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CommunicationFollowUpOptOut::class);

        /** @var Employee $employee */
        $employee = $request->user();

        /** @var array{email: string} $validated */
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));

        /** @var CommunicationFollowUpOptOut|null $existing */
        $existing = CommunicationFollowUpOptOut::query()
            ->where('email', $email)
            ->first();

        if ($existing !== null) {
            // Idempotent : opt-out deja en place.
            return new JsonResponse(['data' => $this->present($existing)]);
        }

        $optOut = new CommunicationFollowUpOptOut;
        $optOut->forceFill([
            'company_id' => (string) $employee->company_id,
            'email' => $email,
            'source' => CommunicationFollowUpOptOut::SOURCE_MANUAL,
            'created_by' => $employee->id,
        ]);
        $optOut->save();

        return new JsonResponse(['data' => $this->present($optOut)], 201);
    }

    public function destroy(Request $request, CommunicationFollowUpOptOut $optOut): JsonResponse
    {
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (cross-tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $optOut);
        $this->authorize('delete', $optOut);

        $optOut->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationFollowUpOptOut $optOut): array
    {
        return [
            'id' => $optOut->id,
            'email' => $optOut->email,
            'source' => $optOut->source,
            'created_at' => $optOut->created_at?->toIso8601String(),
        ];
    }
}
