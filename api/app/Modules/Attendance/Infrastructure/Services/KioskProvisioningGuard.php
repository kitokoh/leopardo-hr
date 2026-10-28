<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Exceptions\KioskProvisioningMismatchException;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\Log;

/**
 * #6678 (extrait de #7962) — garde fail-closed de provisioning kiosque.
 *
 * Extrait de `KioskController::assertKioskProvisionedInPublicContext()`. Vit en
 * Infrastructure : elle a besoin de `Log` et de `TenantManager`, deux
 * dépendances que `check-layer-purity` (#6568) refuse côté Application.
 *
 * ## Le défaut qu'elle empêche
 *
 * Reproduit en production le 2026-09-01 : 1 kiosque sur 3 créé via
 * `POST /kiosks` devenait introuvable — 404 permanent sur `/roster` malgré un
 * `device_code` et un `sync_token` valides. Les routes publiques résolvent le
 * terminal dans `shared_tenants`, mais la ligne venait d'être écrite dans un
 * autre schéma (fuite de `search_path` sur worker partagé, famille
 * #4787/#4798/#4852, ou tenant en mode schéma-par-tenant).
 *
 * ## Pourquoi supprimer la ligne au lieu de la laisser
 *
 * Une ligne de kiosque non résolvable par les routes publiques n'est pas une
 * donnée partielle : c'est un appareil qui existe pour l'administrateur et qui
 * n'existe pas pour le terminal. La laisser en base ferait croire à un parc plus
 * grand qu'il n'est, et l'admin ne pourrait pas la supprimer (son `device_code`
 * ne correspond à rien de servable). On la supprime et on trace : mieux vaut un
 * échec explicite à la création qu'un 404 incompréhensible sur le terrain, une
 * fois l'appareil installé.
 */
final class KioskProvisioningGuard
{
    /** Contexte EXACT des routes publiques kiosque (roster / punch / sync). */
    private const PUBLIC_SEARCH_PATH = 'shared_tenants,public';

    public function __construct(private readonly TenantManager $tenantManager) {}

    /**
     * @throws KioskProvisioningMismatchException quand la ligne n'est pas résolvable
     */
    public function assertResolvable(AttendanceKiosk $kiosk): void
    {
        if ($this->isResolvable($kiosk)) {
            return;
        }

        // On capture ce qu'il faut tracer AVANT la suppression : après, les
        // attributs restent accessibles mais un futur `delete()` en cascade
        // (ou un observer) pourrait les vider.
        $kioskId = $kiosk->id;
        $companyId = $kiosk->company_id;
        $deviceCodeHash = $kiosk->device_code;

        // Fail-closed : ne jamais rendre des credentials inutilisables.
        $kiosk->delete();

        Log::channel('audit')->error('kiosk.provisioning.mismatch', [
            'kiosk_id' => $kioskId,
            'company_id' => $companyId,
            'device_code_hash' => $deviceCodeHash,
        ]);

        throw new KioskProvisioningMismatchException;
    }

    /**
     * Variante non levante, pour les chemins de diagnostic qui doivent
     * continuer malgré un parc partiellement incohérent.
     */
    public function isResolvable(AttendanceKiosk $kiosk): bool
    {
        // Bascule déléguée à TenantManager::withinSearchPath (BOS-019/#8204) :
        // restauration garantie même si la sonde lève une exception.
        return $this->tenantManager->withinSearchPath(
            self::PUBLIC_SEARCH_PATH,
            static fn (): bool => AttendanceKiosk::query()
                ->where('device_code', $kiosk->device_code)
                ->where('status', 'active')
                ->exists(),
        );
    }

    /**
     * `search_path` effectivement appliqué au terminal (historique `schema` vs
     * `shared_tenants`, ADR-0028).
     */
    public function searchPathFor(?Company $company): string
    {
        if ($company instanceof Company
            && $company->tenancy_type === 'schema'
            && trim((string) $company->schema_name) !== ''
        ) {
            return $company->getSafeSearchPath();
        }

        return self::PUBLIC_SEARCH_PATH;
    }
}
