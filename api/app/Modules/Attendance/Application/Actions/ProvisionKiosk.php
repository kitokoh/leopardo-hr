<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\KioskProvisioningWriter;

/**
 * #7962 (sous-tache) — cas d'usage : provisioning d'un kiosque de pointage.
 *
 * Extrait de `KioskController::doRegister()` (controleur cite par l'audit
 * structurel). Le controleur ne fait plus que l'orchestration HTTP
 * (autorisation, search_path, code de statut) ; la creation, la normalisation
 * de la matrice de methodes et la garde fail-closed vivent ici et dans
 * `KioskProvisioningWriter` (Infrastructure — les facades Laravel y sont
 * autorisees, pas en Application : garde #6568).
 *
 * L'Action ne rend jamais le token en clair au-dela de son retour : c'est le
 * controleur qui decide de l'exposer, et seulement dans la reponse de
 * provisioning.
 */
class ProvisionKiosk
{
    public function __construct(
        private readonly KioskProvisioningWriter $writer,
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * @param  array{name: string, location_label?: string|null, biometric_mode?: string|null, trusted_device_label?: string|null, punch_methods?: array<array-key, mixed>|null, site_id?: int|null}  $attributes
     * @return array{kiosk: AttendanceKiosk, device_code: string, sync_token: string}
     */
    public function execute(Company $company, array $attributes): array
    {
        // Le parametre ci-dessus declare `punch_methods` en
        // `array<array-key, mixed>|null` (ce que le controleur recoit du JSON).
        // Affecter un `list<string>` dans cette cle ne RETRECIT pas le type :
        // la cle garde son type declare, et l'appel a `create()` — qui exige
        // `list<string>|null` — echouait en PHPStan 8. On reconstruit donc un
        // tableau au type exact plutot que de muter l'entree.
        $provisioned = $this->writer->create($company, [
            'name' => $attributes['name'],
            'location_label' => $attributes['location_label'] ?? null,
            'biometric_mode' => $attributes['biometric_mode'] ?? null,
            'trusted_device_label' => $attributes['trusted_device_label'] ?? null,
            'punch_methods' => isset($attributes['punch_methods'])
                ? $this->normalizePunchMethods($attributes['punch_methods'])
                : null,
            'site_id' => $attributes['site_id'] ?? null,
        ]);

        // #6678 — garde fail-closed de provisioning (repro prod 2026-09-01) :
        // ne rendre des credentials QUE si la ligne est resolvable par les
        // routes publiques kiosque (roster/punch/sync). Sinon on supprime la
        // ligne orpheline, on trace l'audit et on echoue.
        $this->assertProvisionedInPublicContext($provisioned['kiosk']);

        return $provisioned;
    }

    /**
     * Normalise la matrice envoyee au provisioning (BIO-006) : `card`
     * (vocabulaire ZKTeco) est stocke `badge` (domaine ATT-002).
     *
     * @param  array<array-key, mixed>  $methods
     * @return list<string>
     */
    private function normalizePunchMethods(array $methods): array
    {
        $normalized = [];
        foreach ($methods as $method) {
            if (! is_string($method)) {
                continue;
            }
            $normalized[] = $method === 'card' ? 'badge' : $method;
        }

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $method): bool => in_array($method, AttendanceKiosk::KIOSK_PUNCH_METHODS_ALL, true)
        )));
    }

    /**
     * #6678 — le kiosque doit etre resolvable dans le contexte EXACT des routes
     * publiques (`shared_tenants,public`), sinon les credentials rendus sont
     * inutilisables sur site.
     *
     * La bascule est deleguee a `TenantManager::withinSearchPath` (BOS-019
     * #8204) : la restauration est garantie meme si la sonde leve.
     */
    private function assertProvisionedInPublicContext(AttendanceKiosk $kiosk): void
    {
        $resolvable = $this->tenantManager->withinSearchPath(
            'shared_tenants,public',
            fn (): bool => $this->writer->isResolvableInPublicContext($kiosk),
        );

        if ($resolvable) {
            return;
        }

        $this->writer->discardOrphan($kiosk);
    }
}
