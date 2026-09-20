<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Actions\ActivateTenantPaymentProfile;
use App\Modules\Billing\Application\Actions\SaveTenantPaymentProfile;
use App\Modules\Billing\Domain\Models\TenantPaymentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * #7727 (BC-21 BILLING) — profils de paiement du tenant : CRUD + activation,
 * réservé au rôle `principal` (routes sous `api.manager:principal`).
 *
 * Contrat de sécurité :
 *  - isolation tenant par `BelongsToCompany` (un tenant ne voit/modifie que
 *    SES profils — 404 cross-tenant, jamais 403 révélant l'existence) ;
 *  - secrets WRITE-ONLY chiffrés au repos : l'API ne renvoie que des masques
 *    (`••••1234`), une valeur vide au PUT conserve le secret en place ;
 *  - le profil `stripe_keys` ACTIF est celui que le routage des encaissements
 *    d'Accounting résout (les paiements des factures clients partent sur le
 *    compte Stripe DU TENANT) ; sans profil, comportement plateforme inchangé.
 */
class TenantPaymentProfileController extends Controller
{
    public function index(): JsonResponse
    {
        $profiles = TenantPaymentProfile::query()
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(static fn (TenantPaymentProfile $profile): array => $profile->toApi())
            ->values();

        return new JsonResponse(['data' => ['items' => $profiles]]);
    }

    public function store(Request $request, SaveTenantPaymentProfile $save): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(TenantPaymentProfile::TYPES)],
            'label' => ['required', 'string', 'max:120'],
            'details' => ['sometimes', 'array'],
            'details.*' => ['nullable', 'string', 'max:255'],
            'secrets' => ['sometimes', 'array'],
            'secrets.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();
        $profile = $save->execute($validated, null, $actorId !== null ? (int) $actorId : null);

        return new JsonResponse(['data' => $profile->toApi()], 201);
    }

    public function update(Request $request, int $id, SaveTenantPaymentProfile $save): JsonResponse
    {
        // findOrFail SOUS scope tenant : 404 cross-tenant garanti.
        /** @var TenantPaymentProfile $profile */
        $profile = TenantPaymentProfile::query()->findOrFail($id);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'],
            'details' => ['sometimes', 'array'],
            'details.*' => ['nullable', 'string', 'max:255'],
            'secrets' => ['sometimes', 'array'],
            'secrets.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = $save->execute($validated, $profile);

        return new JsonResponse(['data' => $profile->toApi()]);
    }

    public function activate(int $id, ActivateTenantPaymentProfile $activate): JsonResponse
    {
        /** @var TenantPaymentProfile $profile */
        $profile = TenantPaymentProfile::query()->findOrFail($id);

        $profile = $activate->execute($profile);

        return new JsonResponse(['data' => $profile->toApi()]);
    }

    public function destroy(int $id): JsonResponse
    {
        /** @var TenantPaymentProfile $profile */
        $profile = TenantPaymentProfile::query()->findOrFail($id);

        $profile->delete();

        return new JsonResponse(null, 204);
    }
}
