<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Contracts;

use App\Contracts\Queue\TenantScopedJob;

/**
 * Contract for events that belong to a tenant context.
 *
 * Any event carrying tenant-scoped data MUST implement this interface and be
 * dispatched through {@see TenantEventDispatcher} (or inside an explicit
 * `TenantManager::withinTenant()` scope). This mirrors
 * {@see TenantScopedJob} for the queue layer: an event without a resolvable
 * tenant context fails closed instead of leaking across companies.
 *
 * @see TenantEventDispatcher
 * @see TenantManager::withinTenant()
 */
interface TenantScopedEvent
{
    /**
     * The UUID of the company (tenant) this event belongs to, or null when
     * the event is not attached to a company (platform-wide events).
     */
    public function tenantCompanyId(): ?string;
}
