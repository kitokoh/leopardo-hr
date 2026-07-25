<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a manager_role (rh, comptable, dept, superviseur,
 * marketing, principal) is assigned to or revoked from an employee.
 *
 * Feeds the immutable audit trail (AuditLogController) so principal
 * managers can review every RH/permission change on their team, per
 * PA2-MOB-007 (Gestion RH mobile — nommer/revoquer RH, permissions, audit).
 */
class EmployeeRoleAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly Employee $actor,
        public readonly ?string $previousRole,
        public readonly ?string $newRole,
    ) {}
}
