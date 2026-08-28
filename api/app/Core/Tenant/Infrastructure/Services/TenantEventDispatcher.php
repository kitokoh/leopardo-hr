<?php

declare(strict_types=1);

namespace App\Core\Tenant\Infrastructure\Services;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\Log;

/**
 * TenantEventDispatcher — dispatch d'événements tenant-scopés.
 *
 * Issue #5706 (CRM-V0-02) — les événements métier qui portent des données
 * tenant DOIVENT être dispatchés dans le contexte de leur compagnie :
 *
 *   - le tenant est résolu (paramètre explicite, sinon compagnie courante) ;
 *   - l'absence de tenant échoue immédiatement (fail-closed, même principe
 *     que TenantContextGuard) ;
 *   - l'événement est dispatché dans `TenantManager::withinTenant()`, donc
 *     `search_path` et `current_company` sont corrects pour les listeners.
 *
 * Usage :
 *
 *   app(TenantEventDispatcher::class)->dispatch(new MyTenantEvent($companyId));
 *
 * Contrat : l'événement implémente {@see TenantScopedEvent}.
 */
final class TenantEventDispatcher
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    /**
     * Dispatch un événement tenant-scopé dans le contexte de sa compagnie.
     *
     * @template T of TenantScopedEvent
     *
     * @param  T  $event
     * @param  Company|null  $company  Tenant explicite ; si null, la compagnie
     *                                 courante est utilisée (fail-closed sinon).
     * @return T L'événement dispatché (pratique pour les enchaînements).
     *
     * @throws TenantContextMissingException si aucun tenant n'est résolvable.
     */
    public function dispatch(TenantScopedEvent $event, ?Company $company = null): TenantScopedEvent
    {
        $company ??= $this->tenantManager->current();

        if (! $company instanceof Company) {
            Log::channel('structured')->warning('event.tenant_context.missing', [
                'event' => $event::class,
            ]);

            throw new TenantContextMissingException;
        }

        $this->tenantManager->withinTenant($company, static function () use ($event): void {
            event($event);
        });

        return $event;
    }
}
