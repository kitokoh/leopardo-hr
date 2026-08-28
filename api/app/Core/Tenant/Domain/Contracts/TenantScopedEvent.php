<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Contracts;

/**
 * Contract for events that belong to a tenant context.
 *
 * Any event carrying tenant-scoped data MUST implement this interface and be
 * dispatched through {@see \App\Core\Tenant\Infrastructure\Services\TenantEventDispatcher}
 * (or inside an explicit `TenantManager::withinTenant()` scope). This mirrors
 * {@see \App\Contracts\Queue\TenantScopedJob} for the queue layer: an event
 * without a resolvable tenant context fails closed instead of leaking across
 * companies.
 *
 * @see \App\Core\Tenant\Infrastructure\Services\TenantEventDispatcher
 * @see \App\Core\Tenant\TenantManager::withinTenant()
 */
interface TenantScopedEvent
{
    /**
     * The UUID of the company (tenant) this event belongs to, or null when
     * the event is not attached to a company (platform-wide events).
     */
    public function tenantCompanyId(): ?string;
}
